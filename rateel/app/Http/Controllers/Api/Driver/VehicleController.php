<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\VehicleManagement\Entities\Vehicle;

class VehicleController extends Controller
{
    /**
     * Get insurance status
     * GET /api/driver/auth/vehicle/insurance-status
     */
    public function insuranceStatus(Request $request): JsonResponse
    {
        $driver = auth('api')->user();
        // Allow specifying vehicle_id, otherwise use primary vehicle
        $vehicleId = $request->input('vehicle_id');
        $vehicle = $vehicleId 
            ? Vehicle::where('driver_id', $driver->id)->find($vehicleId)
            : $driver->primaryVehicle;

        if (!$vehicle) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_404',
                'message' => translate('No vehicle found'),
            ]), 404);
        }

        $status = 'unknown';
        $daysRemaining = null;
        $isExpired = false;

        if ($vehicle->insurance_expiry_date) {
            $expiryDate = \Carbon\Carbon::parse($vehicle->insurance_expiry_date);
            $daysRemaining = now()->diffInDays($expiryDate, false);
            $isExpired = $daysRemaining < 0;

            if ($isExpired) {
                $status = 'expired';
            } elseif ($daysRemaining <= 7) {
                $status = 'critical';
            } elseif ($daysRemaining <= 30) {
                $status = 'warning';
            } else {
                $status = 'valid';
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'insurance' => [
                'expiry_date' => $vehicle->insurance_expiry_date,
                'company' => $vehicle->insurance_company,
                'policy_number' => $vehicle->insurance_policy_number,
                'status' => $status,
                'days_remaining' => $daysRemaining,
                'is_expired' => $isExpired,
                'needs_renewal' => $daysRemaining !== null && $daysRemaining <= 30,
            ],
        ]));
    }

    /**
     * Update insurance information
     * POST /api/driver/auth/vehicle/insurance-update
     */
    public function updateInsurance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expiry_date' => 'required|date|after:today',
            'company' => 'sometimes|string|max:255',
            'policy_number' => 'sometimes|string|max:255',
            'vehicle_id' => 'sometimes|uuid|exists:vehicles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        // Allow specifying vehicle_id, otherwise use primary vehicle
        $vehicleId = $request->input('vehicle_id');
        $vehicle = $vehicleId 
            ? Vehicle::where('driver_id', $driver->id)->find($vehicleId)
            : $driver->primaryVehicle;

        if (!$vehicle) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_404',
                'message' => translate('No vehicle found'),
            ]), 404);
        }

        $vehicle->update([
            'insurance_expiry_date' => $request->expiry_date,
            'insurance_company' => $request->input('company'),
            'insurance_policy_number' => $request->input('policy_number'),
            'insurance_reminder_sent' => false, // Reset reminder
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'insurance_updated_200',
            'message' => translate('Insurance information updated successfully'),
        ]));
    }

    /**
     * Get inspection status
     * GET /api/driver/auth/vehicle/inspection-status
     */
    public function inspectionStatus(Request $request): JsonResponse
    {
        $driver = auth('api')->user();
        // Allow specifying vehicle_id, otherwise use primary vehicle
        $vehicleId = $request->input('vehicle_id');
        $vehicle = $vehicleId 
            ? Vehicle::where('driver_id', $driver->id)->find($vehicleId)
            : $driver->primaryVehicle;

        if (!$vehicle) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_404',
                'message' => translate('No vehicle found'),
            ]), 404);
        }

        $status = 'unknown';
        $daysRemaining = null;
        $isOverdue = false;

        if ($vehicle->next_inspection_due) {
            $dueDate = \Carbon\Carbon::parse($vehicle->next_inspection_due);
            $daysRemaining = now()->diffInDays($dueDate, false);
            $isOverdue = $daysRemaining < 0;

            if ($isOverdue) {
                $status = 'overdue';
            } elseif ($daysRemaining <= 7) {
                $status = 'critical';
            } elseif ($daysRemaining <= 30) {
                $status = 'warning';
            } else {
                $status = 'current';
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'inspection' => [
                'last_inspection_date' => $vehicle->last_inspection_date,
                'next_due_date' => $vehicle->next_inspection_due,
                'certificate_number' => $vehicle->inspection_certificate_number,
                'status' => $status,
                'days_remaining' => $daysRemaining,
                'is_overdue' => $isOverdue,
                'needs_inspection' => $daysRemaining !== null && $daysRemaining <= 30,
            ],
        ]));
    }

    /**
     * Update inspection information
     * POST /api/driver/auth/vehicle/inspection-update
     */
    public function updateInspection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'inspection_date' => 'required|date|before_or_equal:today',
            'next_due_date' => 'required|date|after:today',
            'certificate_number' => 'sometimes|string|max:255',
            'vehicle_id' => 'sometimes|uuid|exists:vehicles,id',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        // Allow specifying vehicle_id, otherwise use primary vehicle
        $vehicleId = $request->input('vehicle_id');
        $vehicle = $vehicleId 
            ? Vehicle::where('driver_id', $driver->id)->find($vehicleId)
            : $driver->primaryVehicle;

        if (!$vehicle) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_404',
                'message' => translate('No vehicle found'),
            ]), 404);
        }

        $vehicle->update([
            'last_inspection_date' => $request->inspection_date,
            'next_inspection_due' => $request->next_due_date,
            'inspection_certificate_number' => $request->input('certificate_number'),
            'inspection_reminder_sent' => false, // Reset reminder
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'inspection_updated_200',
            'message' => translate('Inspection information updated successfully'),
        ]));
    }

    /**
     * Get all vehicle reminders
     * GET /api/driver/auth/vehicle/reminders
     */
    public function getReminders(Request $request): JsonResponse
    {
        $driver = auth('api')->user();
        // Get reminders for all vehicles or specific vehicle
        $vehicleId = $request->input('vehicle_id');
        
        if ($vehicleId) {
            $vehicles = collect([Vehicle::where('driver_id', $driver->id)->find($vehicleId)])->filter();
        } else {
            // Get reminders for all active vehicles
            $vehicles = $driver->activeVehicles;
        }

        if ($vehicles->isEmpty()) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_404',
                'message' => translate('No vehicle found'),
            ]), 404);
        }

        $reminders = [];

        // Check reminders for each vehicle
        foreach ($vehicles as $vehicle) {
            $vehicleInfo = [
                'vehicle_id' => $vehicle->id,
                'licence_plate' => $vehicle->licence_plate_number,
            ];

            // Insurance reminder
            if ($vehicle->insurance_expiry_date) {
                $daysUntilExpiry = now()->diffInDays($vehicle->insurance_expiry_date, false);
                if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= 30) {
                    $reminders[] = array_merge($vehicleInfo, [
                        'type' => 'insurance',
                        'title' => translate('Insurance Renewal Due'),
                        'message' => translate('Vehicle :plate insurance expires in :days days', [
                            'plate' => $vehicle->licence_plate_number,
                            'days' => $daysUntilExpiry
                        ]),
                        'days_remaining' => $daysUntilExpiry,
                        'due_date' => $vehicle->insurance_expiry_date,
                        'priority' => $daysUntilExpiry <= 7 ? 'high' : 'medium',
                        'action' => 'renew_insurance',
                    ]);
                }
            }

            // Inspection reminder
            if ($vehicle->next_inspection_due) {
                $daysUntilDue = now()->diffInDays($vehicle->next_inspection_due, false);
                if ($daysUntilDue >= -7 && $daysUntilDue <= 30) {
                    $reminders[] = array_merge($vehicleInfo, [
                        'type' => 'inspection',
                        'title' => $daysUntilDue < 0 ? translate('Inspection Overdue') : translate('Inspection Due'),
                        'message' => $daysUntilDue < 0
                            ? translate('Vehicle :plate inspection is overdue by :days days', [
                                'plate' => $vehicle->licence_plate_number,
                                'days' => abs($daysUntilDue)
                            ])
                            : translate('Vehicle :plate inspection is due in :days days', [
                                'plate' => $vehicle->licence_plate_number,
                                'days' => $daysUntilDue
                            ]),
                        'days_remaining' => $daysUntilDue,
                        'due_date' => $vehicle->next_inspection_due,
                        'priority' => $daysUntilDue <= 0 ? 'urgent' : ($daysUntilDue <= 7 ? 'high' : 'medium'),
                        'action' => 'schedule_inspection',
                    ]);
                }
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'reminders' => $reminders,
            'count' => count($reminders),
            'has_urgent' => collect($reminders)->where('priority', 'urgent')->isNotEmpty(),
        ]));
    }
}
