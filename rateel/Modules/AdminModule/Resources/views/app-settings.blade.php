@extends('adminmodule::layouts.master')

@section('title', translate('App_Settings'))

@push('css_or_js')
<style>
    .setting-card {
        transition: all 0.3s ease;
    }
    .setting-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .setting-input-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .setting-description {
        font-size: 12px;
        color: #6c757d;
        margin-top: 4px;
    }
    .validation-hint {
        font-size: 11px;
        color: #868e96;
    }
    .load-preview-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .load-preview-card .stat-value {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .nav-tabs .nav-link.active {
        background-color: var(--bs-primary);
        color: white;
    }
    .nav-tabs .nav-link {
        color: var(--bs-primary);
        border: 1px solid var(--bs-primary);
        margin-right: 5px;
    }
    .reset-btn {
        opacity: 0.7;
        transition: opacity 0.3s;
    }
    .reset-btn:hover {
        opacity: 1;
    }
    .updated-indicator {
        font-size: 10px;
        color: #28a745;
    }
</style>
@endpush

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
            <h2 class="fs-22 text-capitalize">{{ translate('app_settings') }}</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" id="refreshLoadPreview">
                    <i class="bi bi-arrow-clockwise"></i> {{ translate('refresh_preview') }}
                </button>
            </div>
        </div>

        <!-- Load Preview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card load-preview-card">
                    <div class="card-body">
                        <h5 class="text-white mb-3">{{ translate('expected_system_load') }}</h5>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="stat-value" id="updatesPerMinute">{{ $loadPreview['updates_per_minute_per_driver'] ?? 0 }}</div>
                                <div class="small">{{ translate('updates_per_min_per_driver') }}</div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stat-value" id="totalUpdates">{{ number_format($loadPreview['total_updates_per_minute'] ?? 0) }}</div>
                                <div class="small">{{ translate('total_updates_per_min') }}</div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stat-value" id="redisOps">{{ number_format($loadPreview['redis_operations_per_minute'] ?? 0) }}</div>
                                <div class="small">{{ translate('redis_ops_per_min') }}</div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stat-value">{{ $loadPreview['assumed_active_drivers'] ?? 1000 }}</div>
                                <div class="small">{{ translate('assumed_active_drivers') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                    @foreach($groups as $group)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $activeTab === $group ? 'active' : '' }}"
                                    id="{{ $group }}-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#{{ $group }}"
                                    type="button"
                                    role="tab">
                                <i class="bi bi-{{ $group === 'tracking' ? 'geo-alt' : ($group === 'dispatch' ? 'send' : ($group === 'travel' ? 'airplane' : 'map')) }} me-1"></i>
                                {{ translate(ucfirst($group)) }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card-body">
                <div class="tab-content" id="settingsTabContent">
                    @foreach($groups as $group)
                        <div class="tab-pane fade {{ $activeTab === $group ? 'show active' : '' }}"
                             id="{{ $group }}"
                             role="tabpanel">

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">{{ translate(ucfirst($group) . '_Settings') }}</h5>
                                <button type="button" class="btn btn-outline-warning btn-sm reset-btn"
                                        onclick="resetGroup('{{ $group }}')">
                                    <i class="bi bi-arrow-counterclockwise"></i> {{ translate('reset_all_to_defaults') }}
                                </button>
                            </div>

                            <div class="row">
                                @foreach($settings[$group] ?? [] as $setting)
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card setting-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <label class="form-label fw-bold mb-0" for="setting_{{ $setting->key }}">
                                                        {{ $setting->label ?? $setting->key }}
                                                    </label>
                                                    <button type="button" class="btn btn-link btn-sm p-0 reset-btn"
                                                            onclick="resetSetting('{{ $setting->key }}')"
                                                            title="{{ translate('reset_to_default') }}">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </div>

                                                @if($setting->type === 'boolean')
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input setting-input"
                                                               type="checkbox"
                                                               id="setting_{{ $setting->key }}"
                                                               data-key="{{ $setting->key }}"
                                                               data-type="boolean"
                                                               {{ filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="setting_{{ $setting->key }}">
                                                            {{ translate('enabled') }}
                                                        </label>
                                                    </div>
                                                @else
                                                    <div class="setting-input-group">
                                                        <input type="{{ $setting->type === 'integer' || $setting->type === 'float' ? 'number' : 'text' }}"
                                                               class="form-control setting-input"
                                                               id="setting_{{ $setting->key }}"
                                                               data-key="{{ $setting->key }}"
                                                               data-type="{{ $setting->type }}"
                                                               value="{{ $setting->value }}"
                                                               @if($setting->validation_rules)
                                                                   @if(isset($setting->validation_rules['min']))
                                                                       min="{{ $setting->validation_rules['min'] }}"
                                                                   @endif
                                                                   @if(isset($setting->validation_rules['max']))
                                                                       max="{{ $setting->validation_rules['max'] }}"
                                                                   @endif
                                                               @endif
                                                               {{ $setting->type === 'float' ? 'step="0.01"' : '' }}>
                                                    </div>
                                                    @if($setting->validation_rules)
                                                        <div class="validation-hint">
                                                            @if(isset($setting->validation_rules['min']) && isset($setting->validation_rules['max']))
                                                                {{ translate('range') }}: {{ $setting->validation_rules['min'] }} - {{ $setting->validation_rules['max'] }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endif

                                                @if($setting->description)
                                                    <div class="setting-description">{{ $setting->description }}</div>
                                                @endif

                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <span class="text-muted small">
                                                        {{ translate('default') }}: {{ $setting->default_value }}
                                                    </span>
                                                    @if($setting->updated_at)
                                                        <span class="updated-indicator">
                                                            {{ translate('updated') }}: {{ $setting->updated_at->diffForHumans() }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <button type="button" class="btn btn-primary" onclick="saveGroupSettings('{{ $group }}')">
                                    <i class="bi bi-check-lg"></i> {{ translate('save_changes') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    // Debounce function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Update single setting
    const updateSetting = debounce(function(key, value, type) {
        // Convert value based on type
        if (type === 'boolean') {
            value = value ? 'true' : 'false';
        }

        $.ajax({
            url: '{{ route("admin.app-settings.update-single") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                key: key,
                value: value
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('{{ translate("setting_updated") }}');
                    updateLoadPreview(response.loadPreview);
                } else {
                    toastr.error(response.message || '{{ translate("update_failed") }}');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                toastr.error(response?.message || '{{ translate("update_failed") }}');
            }
        });
    }, 500);

    // Attach change listeners
    $('.setting-input').on('change input', function() {
        const key = $(this).data('key');
        const type = $(this).data('type');
        let value;

        if (type === 'boolean') {
            value = $(this).is(':checked');
        } else {
            value = $(this).val();
        }

        updateSetting(key, value, type);
    });

    // Save all settings in a group
    function saveGroupSettings(group) {
        const settings = [];

        $(`#${group} .setting-input`).each(function() {
            const key = $(this).data('key');
            const type = $(this).data('type');
            let value;

            if (type === 'boolean') {
                value = $(this).is(':checked') ? 'true' : 'false';
            } else {
                value = $(this).val();
            }

            settings.push({ key, value });
        });

        $.ajax({
            url: '{{ route("admin.app-settings.update") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                settings: settings
            },
            success: function(response) {
                if (response.success) {
                    toastr.success('{{ translate("all_settings_saved") }}');
                    updateLoadPreview(response.loadPreview);
                } else {
                    toastr.warning('{{ translate("some_settings_failed") }}');
                    console.log(response.errors);
                }
            },
            error: function(xhr) {
                toastr.error('{{ translate("save_failed") }}');
            }
        });
    }

    // Reset single setting
    function resetSetting(key) {
        if (!confirm('{{ translate("reset_setting_confirm") }}')) {
            return;
        }

        $.ajax({
            url: '{{ route("admin.app-settings.reset") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                key: key
            },
            success: function(response) {
                if (response.success) {
                    // Update input value
                    const input = $(`[data-key="${key}"]`);
                    const type = input.data('type');

                    if (type === 'boolean') {
                        input.prop('checked', response.value === true || response.value === 'true');
                    } else {
                        input.val(response.value);
                    }

                    toastr.success('{{ translate("setting_reset") }}');
                }
            },
            error: function() {
                toastr.error('{{ translate("reset_failed") }}');
            }
        });
    }

    // Reset entire group
    function resetGroup(group) {
        if (!confirm('{{ translate("reset_group_confirm") }}')) {
            return;
        }

        $.ajax({
            url: '{{ route("admin.app-settings.reset-group") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                group: group
            },
            success: function(response) {
                if (response.success) {
                    // Update all input values
                    $.each(response.settings, function(key, value) {
                        const input = $(`[data-key="${key}"]`);
                        const type = input.data('type');

                        if (type === 'boolean') {
                            input.prop('checked', value === true || value === 'true');
                        } else {
                            input.val(value);
                        }
                    });

                    toastr.success('{{ translate("group_reset") }}');
                }
            },
            error: function() {
                toastr.error('{{ translate("reset_failed") }}');
            }
        });
    }

    // Update load preview
    function updateLoadPreview(data) {
        if (data) {
            $('#updatesPerMinute').text(data.updates_per_minute_per_driver);
            $('#totalUpdates').text(numberWithCommas(data.total_updates_per_minute));
            $('#redisOps').text(numberWithCommas(data.redis_operations_per_minute));
        }
    }

    // Number formatting
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Refresh load preview
    $('#refreshLoadPreview').on('click', function() {
        $.ajax({
            url: '{{ route("admin.app-settings.load-preview") }}',
            method: 'GET',
            success: function(response) {
                updateLoadPreview(response.loadPreview);
                toastr.success('{{ translate("preview_refreshed") }}');
            }
        });
    });
</script>
@endpush
