<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Entities\TrustedContact;
use Modules\TripManagement\Entities\TripShare;
use Modules\TripManagement\Entities\EmergencyAlert;
use Modules\TripManagement\Entities\TripMonitoring;
use Modules\TripManagement\Entities\TripRequest;

class SafetyCenterController extends Controller
{
    // ============================================================
    // TRUSTED CONTACTS
    // ============================================================

    /**
     * Get all trusted contacts
     * GET /api/driver/safety/trusted-contacts
     */
    public function getTrustedContacts(): JsonResponse
    {
        $driver = auth('api')->user();

        $contacts = TrustedContact::where('user_id', $driver->id)
            ->active()
            ->byPriority()
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'relationship' => $contact->relationship,
                    'priority' => $contact->priority,
                    'is_active' => $contact->is_active,
                    'created_at' => $contact->created_at->toIso8601String(),
                ];
            });

        return response()->json(responseFormatter(DEFAULT_200, [
            'contacts' => $contacts,
            'total' => $contacts->count(),
            'max_contacts' => 5, // Business rule: max 5 trusted contacts
        ]));
    }

    /**
     * Add trusted contact
     * POST /api/driver/safety/trusted-contacts
     */
    public function addTrustedContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'relationship' => 'nullable|in:family,friend,colleague,other',
            'priority' => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        // Check max contacts limit
        $contactCount = TrustedContact::where('user_id', $driver->id)->active()->count();
        if ($contactCount >= 5) {
            return response()->json(responseFormatter([
                'response_code' => 'max_contacts_reached_400',
                'message' => 'You can only have up to 5 trusted contacts',
            ]), 400);
        }

        $contact = TrustedContact::create([
            'user_id' => $driver->id,
            'name' => $request->name,
            'phone' => $request->phone,
            'relationship' => $request->relationship ?? 'other',
            'priority' => $request->priority ?? ($contactCount + 1),
            'is_active' => true,
        ]);

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'relationship' => $contact->relationship,
                'priority' => $contact->priority,
            ],
        ]));
    }

    /**
     * Update trusted contact
     * PUT /api/driver/safety/trusted-contacts/{id}
     */
    public function updateTrustedContact(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'relationship' => 'sometimes|in:family,friend,colleague,other',
            'priority' => 'sometimes|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        $contact = TrustedContact::where('user_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $contact->update($request->only(['name', 'phone', 'relationship', 'priority']));

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'relationship' => $contact->relationship,
                'priority' => $contact->priority,
            ],
        ]));
    }

    /**
     * Delete trusted contact
     * DELETE /api/driver/safety/trusted-contacts/{id}
     */
    public function deleteTrustedContact(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $contact = TrustedContact::where('user_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $contact->update(['is_active' => false]);

        return response()->json(responseFormatter(DEFAULT_DELETE_200));
    }

    // ============================================================
    // TRIP SHARING
    // ============================================================

    /**
     * Share current trip
     * POST /api/driver/safety/share-trip
     */
    public function shareTrip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|uuid|exists:trip_requests,id',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'uuid|exists:trusted_contacts,id',
            'share_method' => 'required|in:sms,whatsapp,link,auto',
            'expires_in_hours' => 'nullable|integer|min:1|max:24',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        // Verify trip belongs to driver
        $trip = TripRequest::where('id', $request->trip_id)
            ->where('driver_id', $driver->id)
            ->whereIn('current_status', ['accepted', 'ongoing', 'arrived'])
            ->first();

        if (!$trip) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_trip_400',
                'message' => 'Trip not found or not in shareable status',
            ]), 400);
        }

        DB::beginTransaction();

        $shares = [];
        $contactIds = $request->contact_ids ?? [];
        $expiresAt = $request->expires_in_hours ? now()->addHours($request->expires_in_hours) : null;

        // If auto method, share with all active contacts
        if ($request->share_method === 'auto' && empty($contactIds)) {
            $contactIds = TrustedContact::where('user_id', $driver->id)
                ->active()
                ->pluck('id')
                ->toArray();
        }

        // Create shares for each contact
        foreach ($contactIds as $contactId) {
            $share = TripShare::create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'shared_with_contact_id' => $contactId,
                'share_method' => $request->share_method,
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]);

            $contact = TrustedContact::find($contactId);

            // Send notification based on method
            if ($request->share_method === 'sms' || $request->share_method === 'auto') {
                $this->sendSMS($contact->phone, $share->getShareUrl(), $driver->first_name);
            }

            if ($request->share_method === 'whatsapp' || $request->share_method === 'auto') {
                // WhatsApp link generation
                $whatsappUrl = $this->generateWhatsAppLink($contact->phone, $share->getShareUrl(), $driver->first_name);
            }

            $shares[] = [
                'id' => $share->id,
                'contact_name' => $contact->name,
                'share_url' => $share->getShareUrl(),
                'whatsapp_url' => $whatsappUrl ?? null,
                'expires_at' => $share->expires_at?->toIso8601String(),
            ];
        }

        // Create a general link share
        if ($request->share_method === 'link' || empty($contactIds)) {
            $linkShare = TripShare::create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'share_method' => 'link',
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]);

            $shares[] = [
                'id' => $linkShare->id,
                'contact_name' => null,
                'share_url' => $linkShare->getShareUrl(),
                'expires_at' => $linkShare->expires_at?->toIso8601String(),
            ];
        }

        DB::commit();

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'shares' => $shares,
            'total_shared' => count($shares),
            'message' => 'Trip shared successfully',
        ]));
    }

    /**
     * Get shared trips
     * GET /api/driver/safety/shared-trips
     */
    public function getSharedTrips(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        $shares = TripShare::where('driver_id', $driver->id)
            ->active()
            ->with(['trip:id,ref_id,current_status', 'trustedContact:id,name,phone'])
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 20))
            ->get()
            ->map(function ($share) {
                return [
                    'id' => $share->id,
                    'trip_ref' => $share->trip->ref_id,
                    'trip_status' => $share->trip->current_status,
                    'shared_with' => $share->trustedContact ? [
                        'name' => $share->trustedContact->name,
                        'phone' => $share->trustedContact->phone,
                    ] : null,
                    'share_url' => $share->getShareUrl(),
                    'share_method' => $share->share_method,
                    'access_count' => $share->access_count,
                    'expires_at' => $share->expires_at?->toIso8601String(),
                    'created_at' => $share->created_at->toIso8601String(),
                ];
            });

        return response()->json(responseFormatter(DEFAULT_200, [
            'shares' => $shares,
            'total' => $shares->count(),
        ]));
    }

    /**
     * Stop sharing trip
     * DELETE /api/driver/safety/share-trip/{id}
     */
    public function stopSharingTrip(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $share = TripShare::where('driver_id', $driver->id)
            ->where('id', $id)
            ->first();

        if (!$share) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        $share->update(['is_active' => false]);

        return response()->json(responseFormatter(DEFAULT_DELETE_200, [
            'message' => 'Trip sharing stopped',
        ]));
    }

    // ============================================================
    // TRIP MONITORING
    // ============================================================

    /**
     * Enable trip monitoring
     * POST /api/driver/safety/enable-monitoring
     */
    public function enableMonitoring(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|uuid|exists:trip_requests,id',
            'auto_alert_enabled' => 'nullable|boolean',
            'alert_delay_minutes' => 'nullable|integer|min:5|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        // Verify trip belongs to driver
        $trip = TripRequest::where('id', $request->trip_id)
            ->where('driver_id', $driver->id)
            ->whereIn('current_status', ['accepted', 'ongoing', 'arrived'])
            ->first();

        if (!$trip) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_trip_400',
                'message' => 'Trip not found or not in active status',
            ]), 400);
        }

        $monitoring = TripMonitoring::updateOrCreate(
            ['trip_id' => $trip->id],
            [
                'driver_id' => $driver->id,
                'is_enabled' => true,
                'auto_alert_enabled' => $request->auto_alert_enabled ?? true,
                'alert_delay_minutes' => $request->alert_delay_minutes ?? 15,
                'monitoring_started_at' => now(),
            ]
        );

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'monitoring' => [
                'id' => $monitoring->id,
                'trip_id' => $monitoring->trip_id,
                'is_enabled' => $monitoring->is_enabled,
                'auto_alert_enabled' => $monitoring->auto_alert_enabled,
                'alert_delay_minutes' => $monitoring->alert_delay_minutes,
                'started_at' => $monitoring->monitoring_started_at->toIso8601String(),
            ],
            'message' => 'Trip monitoring enabled',
        ]));
    }

    /**
     * Get monitoring status
     * GET /api/driver/safety/monitoring-status
     */
    public function getMonitoringStatus(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        $tripId = $request->get('trip_id');

        $query = TripMonitoring::where('driver_id', $driver->id)->enabled();

        if ($tripId) {
            $query->where('trip_id', $tripId);
        }

        $monitoring = $query->with('trip:id,ref_id,current_status')->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'trip_ref' => $m->trip->ref_id,
                'trip_status' => $m->trip->current_status,
                'is_enabled' => $m->is_enabled,
                'auto_alert_enabled' => $m->auto_alert_enabled,
                'alert_delay_minutes' => $m->alert_delay_minutes,
                'alert_triggered' => $m->alert_triggered,
                'last_location_update' => $m->last_location_update?->toIso8601String(),
                'started_at' => $m->monitoring_started_at?->toIso8601String(),
            ];
        });

        return response()->json(responseFormatter(DEFAULT_200, [
            'monitoring' => $monitoring,
            'active_count' => $monitoring->count(),
        ]));
    }

    /**
     * Update monitoring location
     * POST /api/driver/safety/update-monitoring-location
     */
    public function updateMonitoringLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|uuid|exists:trip_requests,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        $monitoring = TripMonitoring::where('trip_id', $request->trip_id)
            ->where('driver_id', $driver->id)
            ->enabled()
            ->first();

        if (!$monitoring) {
            return response()->json(responseFormatter([
                'response_code' => 'monitoring_not_enabled_400',
                'message' => 'Trip monitoring is not enabled for this trip',
            ]), 400);
        }

        $monitoring->updateLocation($request->latitude, $request->longitude);

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, [
            'message' => 'Location updated',
            'last_update' => $monitoring->last_location_update->toIso8601String(),
        ]));
    }

    // ============================================================
    // EMERGENCY ALERTS
    // ============================================================

    /**
     * Trigger emergency alert
     * POST /api/driver/safety/emergency-alert
     */
    public function triggerEmergencyAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alert_type' => 'required|in:panic,police,medical,accident,harassment',
            'trip_id' => 'nullable|uuid|exists:trip_requests,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driver = auth('api')->user();

        DB::beginTransaction();

        $alert = EmergencyAlert::create([
            'user_id' => $driver->id,
            'user_type' => 'driver',
            'trip_id' => $request->trip_id,
            'alert_type' => $request->alert_type,
            'status' => EmergencyAlert::STATUS_ACTIVE,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'notes' => $request->notes,
        ]);

        // Notify all trusted contacts
        $contacts = TrustedContact::where('user_id', $driver->id)->active()->get();
        foreach ($contacts as $contact) {
            $this->sendEmergencySMS($contact->phone, $driver->first_name, $request->alert_type, $request->latitude, $request->longitude);
        }

        // Notify admin/support team
        // dispatch(new \App\Jobs\NotifyAdminEmergencyAlertJob($alert));

        // If trip monitoring exists, trigger alert
        if ($request->trip_id) {
            $monitoring = TripMonitoring::where('trip_id', $request->trip_id)->first();
            if ($monitoring) {
                $monitoring->triggerAlert();
            }
        }

        DB::commit();

        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'alert' => [
                'id' => $alert->id,
                'alert_type' => $alert->alert_type,
                'status' => $alert->status,
                'created_at' => $alert->created_at->toIso8601String(),
            ],
            'message' => 'Emergency alert triggered. Help is on the way.',
            'emergency_numbers' => $this->getEmergencyNumbers(),
        ]));
    }

    /**
     * Get emergency contacts
     * GET /api/driver/safety/emergency-contacts
     */
    public function getEmergencyContacts(): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'emergency_numbers' => $this->getEmergencyNumbers(),
            'message' => 'In case of emergency, call these numbers immediately',
        ]));
    }

    /**
     * Get my emergency alerts
     * GET /api/driver/safety/my-alerts
     */
    public function getMyAlerts(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        $alerts = EmergencyAlert::where('user_id', $driver->id)
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 20))
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'alert_type' => $alert->alert_type,
                    'status' => $alert->status,
                    'trip_id' => $alert->trip_id,
                    'location' => $alert->latitude && $alert->longitude ? [
                        'latitude' => (float) $alert->latitude,
                        'longitude' => (float) $alert->longitude,
                    ] : null,
                    'notes' => $alert->notes,
                    'resolved_at' => $alert->resolved_at?->toIso8601String(),
                    'created_at' => $alert->created_at->toIso8601String(),
                ];
            });

        return response()->json(responseFormatter(DEFAULT_200, [
            'alerts' => $alerts,
            'total' => $alerts->count(),
        ]));
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function sendSMS(string $phone, string $url, string $driverName): void
    {
        // Implement SMS sending logic
        // Example: Use Twilio, Nexmo, or local SMS gateway
        $message = "تتبع رحلة {$driverName} الآن: {$url}";
        // SMS::send($phone, $message);
    }

    private function sendEmergencySMS(string $phone, string $driverName, string $alertType, $lat, $lng): void
    {
        $message = "⚠️ تنبيه طوارئ من {$driverName}! نوع التنبيه: {$alertType}";
        if ($lat && $lng) {
            $message .= " الموقع: https://maps.google.com/?q={$lat},{$lng}";
        }
        // SMS::send($phone, $message);
    }

    private function generateWhatsAppLink(string $phone, string $url, string $driverName): string
    {
        $message = urlencode("تتبع رحلة {$driverName} الآن: {$url}");
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        return "https://wa.me/{$cleanPhone}?text={$message}";
    }

    private function getEmergencyNumbers(): array
    {
        return [
            [
                'service' => 'police',
                'name' => 'Police',
                'name_ar' => 'الشرطة',
                'number' => '122',
                'icon' => 'police',
            ],
            [
                'service' => 'ambulance',
                'name' => 'Ambulance',
                'name_ar' => 'الإسعاف',
                'number' => '123',
                'icon' => 'medical',
            ],
            [
                'service' => 'traffic',
                'name' => 'Traffic Police',
                'name_ar' => 'مرور',
                'number' => '128',
                'icon' => 'traffic',
            ],
            [
                'service' => 'fire',
                'name' => 'Fire Department',
                'name_ar' => 'الإطفاء',
                'number' => '180',
                'icon' => 'fire',
            ],
        ];
    }
}
