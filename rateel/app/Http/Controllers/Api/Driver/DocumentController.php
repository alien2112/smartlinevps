<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Get document expiry status
     * GET /api/driver/auth/documents/expiry-status
     */
    public function expiryStatus(): JsonResponse
    {
        $driver = auth('api')->user();

        $documents = DB::table('driver_documents')
            ->where('driver_id', $driver->id)
            ->whereNotNull('expiry_date')
            ->get();

        $expiringDocuments = [];
        $expiredDocuments = [];

        foreach ($documents as $doc) {
            $expiryDate = \Carbon\Carbon::parse($doc->expiry_date);
            $daysRemaining = now()->diffInDays($expiryDate, false);

            $docInfo = [
                'id' => $doc->id,
                'type' => $doc->type,
                'expiry_date' => $doc->expiry_date,
                'days_remaining' => $daysRemaining,
                'verified' => (bool) $doc->verified,
            ];

            if ($daysRemaining < 0) {
                $docInfo['status'] = 'expired';
                $docInfo['days_expired'] = abs($daysRemaining);
                $expiredDocuments[] = $docInfo;
            } elseif ($daysRemaining <= 30) {
                $docInfo['status'] = $daysRemaining <= 7 ? 'critical' : 'warning';
                $expiringDocuments[] = $docInfo;
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'documents' => [
                'total' => $documents->count(),
                'expiring_soon' => $expiringDocuments,
                'expired' => $expiredDocuments,
            ],
            'alerts' => [
                'has_expired' => count($expiredDocuments) > 0,
                'has_expiring' => count($expiringDocuments) > 0,
                'total_alerts' => count($expiredDocuments) + count($expiringDocuments),
            ],
        ]));
    }

    /**
     * Update document expiry date
     * POST /api/driver/auth/documents/{id}/update-expiry
     */
    public function updateExpiry(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expiry_date' => 'required|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $document = DB::table('driver_documents')
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$document) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        DB::table('driver_documents')
            ->where('id', $id)
            ->update([
                'expiry_date' => $request->expiry_date,
                'reminder_sent' => false,
                'reminder_sent_at' => null,
                'updated_at' => now(),
            ]);

        return response()->json(responseFormatter([
            'response_code' => 'expiry_updated_200',
            'message' => translate('Document expiry date updated successfully'),
        ]));
    }
}
