@extends('adminmodule::layouts.master')

@section('title', 'Firebase Configuration')

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">Firebase Configuration</h2>
            <button class="btn btn-outline-primary btn-sm" id="testConfigBtn">
                <i class="bi bi-check-circle"></i> Test Configuration
            </button>
        </div>

        <div class="card border-0 mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div>
                    <h5 class="text-capitalize mb-2">Push Notification Setup</h5>
                    <div class="fs-12 text-muted">
                        Configure Firebase Cloud Messaging (FCM) for push notifications
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#instructionsModal">
                    <i class="bi bi-question-circle"></i> How it works
                </button>
            </div>

            <div class="card-body p-30">
                <form action="{{ route('admin.firebase-config.store') }}" method="POST">
                    @csrf

                    <!-- Service Account Content -->
                    <div class="mb-4">
                        <label for="server_key" class="form-label fw-semibold">
                            Service Account Content (JSON)
                            <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip"
                               title="Paste the entire content of your Firebase service account JSON file here"></i>
                        </label>
                        <textarea
                            name="server_key"
                            id="server_key"
                            class="form-control font-monospace"
                            rows="8"
                            placeholder='{"type": "service_account", "project_id": "your-project-id", ...}'
                        >{{ $settings->firstWhere('key_name', 'server_key')?->value }}</textarea>
                        <div class="form-text">
                            <strong>How to get:</strong> Firebase Console → Project Settings → Service Accounts → Generate New Private Key
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Firebase Web App Configuration</h6>
                    <div class="row">
                        <!-- API Key -->
                        <div class="col-md-6 mb-3">
                            <label for="api_key" class="form-label">API Key *</label>
                            <input
                                type="text"
                                name="api_key"
                                id="api_key"
                                class="form-control"
                                placeholder="AIzaSyAhMz6lR******Phf4KE9raM87"
                                value="{{ $settings->firstWhere('key_name', 'api_key')?->value }}"
                            >
                        </div>

                        <!-- Auth Domain -->
                        <div class="col-md-6 mb-3">
                            <label for="auth_domain" class="form-label">Auth Domain *</label>
                            <input
                                type="text"
                                name="auth_domain"
                                id="auth_domain"
                                class="form-control"
                                placeholder="your-project.firebaseapp.com"
                                value="{{ $settings->firstWhere('key_name', 'auth_domain')?->value }}"
                            >
                        </div>

                        <!-- Project ID -->
                        <div class="col-md-6 mb-3">
                            <label for="project_id" class="form-label">Project ID *</label>
                            <input
                                type="text"
                                name="project_id"
                                id="project_id"
                                class="form-control"
                                placeholder="your-project-12345"
                                value="{{ $settings->firstWhere('key_name', 'project_id')?->value }}"
                            >
                        </div>

                        <!-- Storage Bucket -->
                        <div class="col-md-6 mb-3">
                            <label for="storage_bucket" class="form-label">Storage Bucket</label>
                            <input
                                type="text"
                                name="storage_bucket"
                                id="storage_bucket"
                                class="form-control"
                                placeholder="your-project.appspot.com"
                                value="{{ $settings->firstWhere('key_name', 'storage_bucket')?->value }}"
                            >
                        </div>

                        <!-- Messaging Sender ID -->
                        <div class="col-md-4 mb-3">
                            <label for="messaging_sender_id" class="form-label">Messaging Sender ID *</label>
                            <input
                                type="text"
                                name="messaging_sender_id"
                                id="messaging_sender_id"
                                class="form-control"
                                placeholder="123456789012"
                                value="{{ $settings->firstWhere('key_name', 'messaging_sender_id')?->value }}"
                            >
                        </div>

                        <!-- App ID -->
                        <div class="col-md-4 mb-3">
                            <label for="app_id" class="form-label">App ID *</label>
                            <input
                                type="text"
                                name="app_id"
                                id="app_id"
                                class="form-control"
                                placeholder="1:123456789012:web:abc123def456"
                                value="{{ $settings->firstWhere('key_name', 'app_id')?->value }}"
                            >
                        </div>

                        <!-- Measurement ID -->
                        <div class="col-md-4 mb-3">
                            <label for="measurement_id" class="form-label">Measurement ID</label>
                            <input
                                type="text"
                                name="measurement_id"
                                id="measurement_id"
                                class="form-control"
                                placeholder="G-XXXXXXXXXX"
                                value="{{ $settings->firstWhere('key_name', 'measurement_id')?->value }}"
                            >
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Where to find these values:</strong><br>
                        Firebase Console → Project Settings → General → Your apps → Web app → SDK setup and configuration
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Configuration Status -->
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0">Configuration Status</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    @php
                        $requiredFields = ['server_key', 'api_key', 'project_id', 'auth_domain', 'messaging_sender_id', 'app_id'];
                        $configuredCount = 0;
                        foreach ($requiredFields as $field) {
                            if ($settings->firstWhere('key_name', $field)?->value) {
                                $configuredCount++;
                            }
                        }
                        $percentage = ($configuredCount / count($requiredFields)) * 100;
                    @endphp

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Configuration Progress</label>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar {{ $percentage == 100 ? 'bg-success' : 'bg-warning' }}"
                                     role="progressbar"
                                     style="width: {{ $percentage }}%">
                                    {{ round($percentage) }}%
                                </div>
                            </div>
                            <small class="text-muted">{{ $configuredCount }} of {{ count($requiredFields) }} required fields configured</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Service Worker Status</label>
                        <div>
                            @if(file_exists(base_path('firebase-messaging-sw.js')))
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> File Generated
                                </span>
                                <small class="text-muted d-block mt-1">
                                    Last modified: {{ date('Y-m-d H:i:s', filemtime(base_path('firebase-messaging-sw.js'))) }}
                                </small>
                            @else
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle"></i> Not Generated
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Instructions Modal -->
<div class="modal fade" id="instructionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Firebase Setup Instructions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>Step 1: Create Firebase Project</h6>
                <ol>
                    <li>Go to <a href="https://console.firebase.google.com" target="_blank">Firebase Console</a></li>
                    <li>Click "Add project" or select existing project</li>
                    <li>Follow the setup wizard</li>
                </ol>

                <h6 class="mt-4">Step 2: Get Service Account Key</h6>
                <ol>
                    <li>In Firebase Console, go to Project Settings (gear icon)</li>
                    <li>Navigate to "Service Accounts" tab</li>
                    <li>Click "Generate New Private Key"</li>
                    <li>Copy the entire JSON content and paste in the "Service Account Content" field</li>
                </ol>

                <h6 class="mt-4">Step 3: Get Web App Configuration</h6>
                <ol>
                    <li>In Firebase Console, go to Project Settings → General</li>
                    <li>Scroll to "Your apps" section</li>
                    <li>If no web app exists, click "Add app" → Web</li>
                    <li>Copy the configuration values (apiKey, authDomain, etc.)</li>
                    <li>Paste them in the corresponding fields above</li>
                </ol>

                <h6 class="mt-4">Step 4: Enable Cloud Messaging</h6>
                <ol>
                    <li>In Firebase Console, go to Project Settings → Cloud Messaging</li>
                    <li>Enable Cloud Messaging API if not already enabled</li>
                    <li>Note down your Server Key (used in Service Account)</li>
                </ol>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
document.getElementById('testConfigBtn').addEventListener('click', function() {
    fetch('{{ route('admin.firebase-config.test') }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success(data.message);
            } else {
                toastr.error(data.message);
            }
        })
        .catch(error => {
            toastr.error('Failed to test configuration');
        });
});

// Initialize Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>
@endpush
