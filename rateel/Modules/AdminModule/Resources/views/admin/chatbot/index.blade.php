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
                {{-- Main Configuration --}}
                <div class="col-lg-8">
                    <div class="card mb-3">
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

                    {{-- Prompt Embedding Templates --}}
                    @if(isset($promptTemplates) && count($promptTemplates) > 0)
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-puzzle me-2"></i>{{ translate('Prompt_Templates') }}
                            </h5>
                            <small class="text-muted">{{ translate('Click_on_a_template_to_insert_it_into_your_prompt') }}</small>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="promptTemplatesAccordion">
                                @foreach($promptTemplates as $index => $category)
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading{{ $index }}">
                                        <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}"
                                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="collapse{{ $index }}">
                                            {{ translate($category['name']) }}
                                            <span class="badge bg-secondary ms-2">{{ count($category['snippets']) }}</span>
                                        </button>
                                    </h2>
                                    <div id="collapse{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                                        aria-labelledby="heading{{ $index }}" data-bs-parent="#promptTemplatesAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach($category['snippets'] as $snippet)
                                                <button type="button" class="btn btn-outline-primary btn-sm prompt-snippet"
                                                    data-snippet="{{ $snippet['text'] }}"
                                                    title="{{ $snippet['text'] }}">
                                                    <i class="bi bi-plus-circle me-1"></i>{{ translate($snippet['label']) }}
                                                </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-3 p-2 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    {{ translate('Tip_Combine_multiple_templates_to_create_a_comprehensive_prompt') }}
                                </small>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Chat History Sidebar --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>{{ translate('Chat_History') }}</h5>
                            <a href="{{ route('admin.chatbot.logs') }}" class="fs-12">{{ translate('View_All') }}</a>
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            @if(isset($chatHistory) && count($chatHistory) > 0)
                                @foreach($chatHistory as $userId => $messages)
                                    <div class="border-bottom">
                                        {{-- Conversation Header --}}
                                        <div class="px-3 py-2 bg-light d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="{{ route('admin.chatbot.logs', ['user_id' => $userId]) }}" class="fw-bold fs-12 text-primary">
                                                    <i class="bi bi-person-circle me-1"></i>{{ translate('User') }} #{{ substr($userId, 0, 8) }}...
                                                </a>
                                                <span class="badge bg-secondary ms-1">{{ count($messages) }} {{ translate('msgs') }}</span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                                onclick="clearUserHistory('{{ $userId }}')" title="{{ translate('Clear_History') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        {{-- Conversation Messages --}}
                                        <div class="px-3 py-2">
                                            @foreach($messages->take(4) as $message)
                                                <div class="mb-2 {{ $message->role === 'user' ? 'text-start' : 'text-end' }}">
                                                    <div class="d-inline-block p-2 rounded-3 {{ $message->role === 'user' ? 'bg-primary-subtle' : 'bg-success-subtle' }}"
                                                        style="max-width: 90%;">
                                                        <div class="d-flex align-items-center gap-1 mb-1">
                                                            @if($message->role === 'user')
                                                                <i class="bi bi-person fs-10"></i>
                                                            @else
                                                                <i class="bi bi-robot fs-10"></i>
                                                            @endif
                                                            <span class="fs-10 text-muted">
                                                                {{ $message->role === 'user' ? translate('User') : translate('AI') }}
                                                            </span>
                                                            @if($message->action_type)
                                                                <span class="badge bg-warning fs-8">{{ $message->action_type }}</span>
                                                            @endif
                                                        </div>
                                                        <p class="fs-12 mb-0" title="{{ $message->content }}">
                                                            {{ Str::limit($message->content, 80) }}
                                                        </p>
                                                        <small class="fs-10 text-muted">
                                                            {{ \Carbon\Carbon::parse($message->created_at)->diffForHumans() }}
                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                            @if(count($messages) > 4)
                                                <div class="text-center">
                                                    <a href="{{ route('admin.chatbot.logs', ['user_id' => $userId]) }}" class="fs-12 text-muted">
                                                        +{{ count($messages) - 4 }} {{ translate('more_messages') }}...
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center p-4">
                                    <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}" alt="" class="mb-3" width="80">
                                    <p class="text-muted">{{ translate('No_chat_history_available_yet') }}</p>
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

        // Prompt snippet embedding
        document.querySelectorAll('.prompt-snippet').forEach(button => {
            button.addEventListener('click', function() {
                const snippet = this.dataset.snippet;
                const promptTextarea = document.getElementById('prompt');
                const currentValue = promptTextarea.value;

                // Add snippet with newline if there's existing content
                if (currentValue.trim()) {
                    promptTextarea.value = currentValue.trim() + '\n\n' + snippet;
                } else {
                    promptTextarea.value = snippet;
                }

                // Update character count
                document.getElementById('char-count').textContent = promptTextarea.value.length;

                // Highlight the textarea briefly
                promptTextarea.classList.add('border-success');
                setTimeout(() => promptTextarea.classList.remove('border-success'), 1000);

                // Scroll to textarea
                promptTextarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                promptTextarea.focus();

                // Show success toast
                if (typeof toastr !== 'undefined') {
                    toastr.success('{{ translate("Template_added_to_prompt") }}');
                }
            });
        });

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

        // Clear user history
        function clearUserHistory(userId) {
            if (!confirm('{{ translate("Are_you_sure_you_want_to_clear_this_users_chat_history") }}?')) {
                return;
            }

            fetch('{{ route("admin.chatbot.clear-history") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success('{{ translate("Chat_history_cleared") }}');
                    }
                    location.reload();
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(data.error || '{{ translate("Failed_to_clear_history") }}');
                    }
                }
            })
            .catch(error => {
                if (typeof toastr !== 'undefined') {
                    toastr.error('{{ translate("An_error_occurred") }}');
                }
            });
        }
    </script>

    <style>
        .prompt-snippet {
            transition: all 0.2s ease;
        }
        .prompt-snippet:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .bg-primary-subtle {
            background-color: rgba(13, 110, 253, 0.1) !important;
        }
        .bg-success-subtle {
            background-color: rgba(25, 135, 84, 0.1) !important;
        }
        #prompt {
            transition: border-color 0.3s ease;
        }
        #prompt.border-success {
            border-color: #198754 !important;
            border-width: 2px;
        }
    </style>
@endsection
