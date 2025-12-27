<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Brian2694\Toastr\Facades\Toastr;

class SettingsController extends Controller
{
    use AuthorizesRequests;

    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Display settings page with tabs
     */
    public function index(Request $request): View
    {
        $this->authorize('settings_view');

        $activeTab = $request->get('tab', 'tracking');
        $groups = $this->settingsService->getGroups();

        $settings = [];
        foreach ($groups as $group) {
            $settings[$group] = AppSetting::where('group', $group)
                ->orderBy('id')
                ->get();
        }

        $loadPreview = $this->settingsService->calculateExpectedLoad();

        return view('adminmodule::app-settings', [
            'activeTab' => $activeTab,
            'groups' => $groups,
            'settings' => $settings,
            'loadPreview' => $loadPreview,
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('settings_edit');

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        $errors = [];
        $updated = [];

        foreach ($validated['settings'] as $item) {
            $key = $item['key'];
            $value = $item['value'];

            // Validate the setting
            $setting = AppSetting::where('key', $key)->first();
            if (!$setting) {
                $errors[$key] = 'Setting not found';
                continue;
            }

            $validationErrors = $setting->validateValue($value);
            if (!empty($validationErrors)) {
                $errors[$key] = $validationErrors;
                continue;
            }

            // Update the setting
            $success = $this->settingsService->set($key, $value, auth()->id());
            if ($success) {
                $updated[$key] = $value;
            } else {
                $errors[$key] = 'Failed to update';
            }
        }

        if (count($errors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Some settings failed validation',
                'errors' => $errors,
                'updated' => $updated,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'updated' => $updated,
            'loadPreview' => $this->settingsService->calculateExpectedLoad(),
        ]);
    }

    /**
     * Update a single setting
     */
    public function updateSingle(Request $request): JsonResponse
    {
        $this->authorize('settings_edit');

        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'required',
        ]);

        $setting = AppSetting::where('key', $validated['key'])->first();
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
            ], 404);
        }

        $validationErrors = $setting->validateValue($validated['value']);
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validationErrors,
            ], 422);
        }

        $success = $this->settingsService->set($validated['key'], $validated['value'], auth()->id());

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting updated successfully',
            'loadPreview' => $this->settingsService->calculateExpectedLoad(),
        ]);
    }

    /**
     * Reset a setting to default
     */
    public function resetToDefault(Request $request): JsonResponse
    {
        $this->authorize('settings_edit');

        $validated = $request->validate([
            'key' => 'required|string',
        ]);

        $success = $this->settingsService->resetToDefault($validated['key'], auth()->id());

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset setting',
            ], 500);
        }

        $setting = AppSetting::where('key', $validated['key'])->first();

        return response()->json([
            'success' => true,
            'message' => 'Setting reset to default',
            'value' => $setting?->typed_value,
        ]);
    }

    /**
     * Reset all settings in a group to defaults
     */
    public function resetGroupToDefaults(Request $request): JsonResponse
    {
        $this->authorize('settings_edit');

        $validated = $request->validate([
            'group' => 'required|string|in:tracking,dispatch,travel,map',
        ]);

        $results = $this->settingsService->resetGroupToDefaults($validated['group'], auth()->id());

        $allSuccess = !in_array(false, $results, true);

        if (!$allSuccess) {
            return response()->json([
                'success' => false,
                'message' => 'Some settings failed to reset',
                'results' => $results,
            ], 500);
        }

        // Get fresh settings
        $settings = AppSetting::where('group', $validated['group'])->get();

        return response()->json([
            'success' => true,
            'message' => 'All settings in group reset to defaults',
            'settings' => $settings->mapWithKeys(fn ($s) => [$s->key => $s->typed_value]),
        ]);
    }

    /**
     * Get current load preview
     */
    public function loadPreview(): JsonResponse
    {
        $this->authorize('settings_view');

        return response()->json([
            'success' => true,
            'loadPreview' => $this->settingsService->calculateExpectedLoad(),
        ]);
    }

    /**
     * Get all settings as JSON (for API consumers)
     */
    public function getSettingsJson(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => $this->settingsService->getAsKeyValueArray(),
        ]);
    }

    /**
     * Get settings for a specific group
     */
    public function getGroupSettings(string $group): JsonResponse
    {
        $this->authorize('settings_view');

        if (!in_array($group, $this->settingsService->getGroups())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid group',
            ], 400);
        }

        $settings = $this->settingsService->getGroup($group);

        return response()->json([
            'success' => true,
            'group' => $group,
            'settings' => $settings,
        ]);
    }
}
