<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        $requestHash = $this->generateRequestHash($request);

        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Idempotency key reused with different request payload'
                ], 422);
            }

            return response()->json(
                $existing->response_body,
                $existing->response_code
            );
        }

        DB::beginTransaction();
        try {
            $lock = DB::table('idempotency_keys')
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($lock) {
                DB::rollBack();
                return response()->json(
                    json_decode($lock->response_body, true),
                    $lock->response_code
                );
            }

            $response = $next($request);

            $resourceType = $this->extractResourceType($request);
            $resourceId = $this->extractResourceId($request, $response);

            IdempotencyKey::create([
                'key' => $idempotencyKey,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'request_hash' => $requestHash,
                'response_code' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
            ]);

            DB::commit();

            return $response;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function generateRequestHash(Request $request): string
    {
        $payload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'body' => $request->except(['_token']),
            'user_id' => auth()->id(),
        ];

        return hash('sha256', json_encode($payload));
    }

    private function extractResourceType(Request $request): ?string
    {
        if (str_contains($request->path(), 'trip')) {
            return 'trip_request';
        }
        return null;
    }

    private function extractResourceId(Request $request, Response $response): ?string
    {
        $content = json_decode($response->getContent(), true);

        if (isset($content['data']['id'])) {
            return $content['data']['id'];
        }

        if ($request->has('trip_request_id')) {
            return $request->input('trip_request_id');
        }

        return null;
    }
}
