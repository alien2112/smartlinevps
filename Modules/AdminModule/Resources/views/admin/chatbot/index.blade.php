@section('title', translate('AI_Chatbot_Manager'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <h2 class="fs-22 text-capitalize">{{ translate('AI_Chatbot_Manager') }}</h2>
                <a href="{{ route('admin.chatbot.logs') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history"></i> {{ translate('View_All_Logs') }}
                </a>
            </div>

            {{-- Stats Cards --}}
            @if(isset($stats))
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title opacity-75">{{ translate('Total_Conversations') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_conversations'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title opacity-75">{{ translate('Total_Messages') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_messages'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title opacity-75">{{ translate('Today_Messages') }}</h6>
                            <h2 class="mb-0">{{ number_format($stats['today_messages'] ?? 0) }}</h2>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ translate('Configuration') }}</h5>
                            <span id="health-status" class="badge bg-secondary">{{ translate('Checking') }}...</span>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.chatbot.update') }}" method="POST">
                                @csrf
                                <div class="mb-4 d-flex justify-content-between align-items-center border rounded p-3">
                                    <div>
                                        <h5 class="mb-1">{{ translate('Enable_AI_Chatbot') }}</h5>
                                        <p class="fs-12 mb-0 text-muted">{{ translate('Turn_on_to_allow_users_to_chat_with_AI') }}</p>
                                    </div>
                                    <label class="switcher">
                                        <input class="switcher_input" type="checkbox" name="ai_chatbot_enable" value="1" 
                                            {{ isset($isEnabled) && $isEnabled->value == 1 ? 'checked' : '' }}>
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>

                                <div class="mb-4">
                                    <label for="prompt" class="form-label fw-bold">{{ translate('Personality_/_System_Prompt') }}</label>
                                    <textarea class="form-control" id="prompt" name="ai_chatbot_prompt" rows="10" 
                                        placeholder="You are a helpful assistant for SmartLine..." required
                                        minlength="10" maxlength="5000">{{ isset($prompt) ? $prompt->value : '' }}</textarea>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">{{ translate('Define_how_the_AI_should_behave_and_what_it_knows_about_your_business.') }}</small>
                                        <small class="text-muted"><span id="char-count">0</span>/5000</small>
                                    </div>
                                </div>

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label for="rate_limit" class="form-label">{{ translate('Rate_Limit_Per_Minute') }}</label>
                                        <input type="number" class="form-control" id="rate_limit" name="ai_chatbot_rate_limit" 
                                            value="{{ isset($rateLimitPerMinute) ? $rateLimitPerMinute->value : 10 }}" 
                                            min="1" max="100" placeholder="10">
                                        <small class="text-muted">{{ translate('Maximum_messages_per_user_per_minute') }}</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="max_tokens" class="form-label">{{ translate('Max_Response_Tokens') }}</label>
                                        <input type="number" class="form-control" id="max_tokens" name="ai_chatbot_max_tokens" 
                                            value="{{ isset($maxTokens) ? $maxTokens->value : 300 }}" 
                                            min="50" max="1000" placeholder="300">
                                        <small class="text-muted">{{ translate('Maximum_AI_response_length') }}</small>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">{{ translate('Save_Changes') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ translate('Recent_Chat_Logs') }}</h5>
                            <a href="{{ route('admin.chatbot.logs') }}" class="fs-12">{{ translate('View_All') }}</a>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            @if(isset($logs) && count($logs) > 0)
                                @foreach($logs as $log)
                                    <div class="border-bottom p-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-bold fs-12">
                                                <a href="{{ route('admin.chatbot.logs', ['user_id' => $log->user_id]) }}">
                                                    {{ translate('User') }} #{{ substr($log->user_id, 0, 8) }}...
                                                </a>
                                            </span>
                                            <span class="fs-10 text-muted">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</span>
                                        </div>
                                        <p class="fs-12 mb-0 text-truncate" title="{{ $log->content }}">{{ Str::limit($log->content, 60) }}</p>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center p-4">
                                    <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}" alt="" class="mb-3" width="80">
                                    <p class="text-muted">{{ translate('No_logs_available_yet') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Character counter for prompt
        document.getElementById('prompt').addEventListener('input', function() {
            document.getElementById('char-count').textContent = this.value.length;
        });
        document.getElementById('char-count').textContent = document.getElementById('prompt').value.length;

        // Health check
        fetch('{{ route("admin.chatbot.health") }}')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('health-status');
                if (data.chatbot === 'ok') {
                    badge.className = 'badge bg-success';
                    badge.textContent = '{{ translate("Online") }}';
                } else {
                    badge.className = 'badge bg-danger';
                    badge.textContent = '{{ translate("Offline") }}';
                }
            })
            .catch(() => {
                const badge = document.getElementById('health-status');
                badge.className = 'badge bg-warning';
                badge.textContent = '{{ translate("Unknown") }}';
            });
    </script>
@endsection
