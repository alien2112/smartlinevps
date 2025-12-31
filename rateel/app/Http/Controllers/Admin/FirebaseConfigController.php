<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\BusinessManagement\Entities\BusinessSetting;

class FirebaseConfigController extends Controller
{
    /**
     * Display the Firebase configuration form
     */
    public function index()
    {
        $settings = BusinessSetting::where('settings_type', NOTIFICATION_SETTINGS)->get();

        return view('admin.firebase-config.index', compact('settings'));
    }

    /**
     * Store or update Firebase configuration
     */
    public function store(Request $request)
    {
        $request->validate([
            'server_key' => 'nullable|string',
            'api_key' => 'nullable|string',
            'auth_domain' => 'nullable|string',
            'project_id' => 'nullable|string',
            'storage_bucket' => 'nullable|string',
            'messaging_sender_id' => 'nullable|string',
            'app_id' => 'nullable|string',
            'measurement_id' => 'nullable|string',
        ]);

        try {
            // Update or create each setting
            foreach ($request->only([
                'server_key',
                'api_key',
                'auth_domain',
                'project_id',
                'storage_bucket',
                'messaging_sender_id',
                'app_id',
                'measurement_id'
            ]) as $key => $value) {
                if ($value) {
                    BusinessSetting::updateOrCreate(
                        [
                            'key_name' => $key,
                            'settings_type' => NOTIFICATION_SETTINGS
                        ],
                        [
                            'value' => $value,
                            'settings_type' => NOTIFICATION_SETTINGS
                        ]
                    );
                }
            }

            // Generate firebase-messaging-sw.js file
            $this->generateFirebaseServiceWorker();

            Toastr::success('Firebase configuration updated successfully!');
            return redirect()->back();

        } catch (\Exception $e) {
            Log::error('Firebase config update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Toastr::error('Failed to update Firebase configuration: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    /**
     * Generate the firebase-messaging-sw.js file
     */
    private function generateFirebaseServiceWorker()
    {
        $apiKey = BusinessSetting::where('key_name', 'api_key')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $authDomain = BusinessSetting::where('key_name', 'auth_domain')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $projectId = BusinessSetting::where('key_name', 'project_id')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $storageBucket = BusinessSetting::where('key_name', 'storage_bucket')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $messagingSenderId = BusinessSetting::where('key_name', 'messaging_sender_id')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $appId = BusinessSetting::where('key_name', 'app_id')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $measurementId = BusinessSetting::where('key_name', 'measurement_id')
            ->where('settings_type', NOTIFICATION_SETTINGS)
            ->value('value') ?? '';

        $filePath = base_path('firebase-messaging-sw.js');

        try {
            if (file_exists($filePath) && !is_writable($filePath)) {
                if (!chmod($filePath, 0644)) {
                    throw new \Exception('File is not writable and permission change failed: ' . $filePath);
                }
            }

            $fileContent = <<<JS
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "$apiKey",
    authDomain: "$authDomain",
    projectId: "$projectId",
    storageBucket: "$storageBucket",
    messagingSenderId: "$messagingSenderId",
    appId: "$appId",
    measurementId: "$measurementId"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});
JS;

            if (file_put_contents($filePath, $fileContent) === false) {
                throw new \Exception('Failed to write to file: ' . $filePath);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate Firebase service worker', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test Firebase configuration
     */
    public function test()
    {
        $settings = BusinessSetting::where('settings_type', NOTIFICATION_SETTINGS)
            ->whereIn('key_name', ['server_key', 'api_key', 'project_id'])
            ->get();

        $hasRequiredSettings = $settings->count() >= 3;

        if ($hasRequiredSettings) {
            return response()->json([
                'success' => true,
                'message' => 'Firebase configuration appears to be set up correctly.',
                'settings' => $settings->pluck('value', 'key_name')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Firebase configuration is incomplete. Please fill all required fields.',
            'settings_count' => $settings->count()
        ], 400);
    }
}
