@section('title', translate('AI_Chatbot_Logs'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <h2 class="fs-22 text-capitalize">{{ translate('AI_Chatbot_Logs') }}</h2>
                <a href="{{ route('admin.chatbot.index') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left"></i> {{ translate('Back_to_Config') }}
                </a>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <form action="{{ route('admin.chatbot.logs') }}" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('User_ID') }}</label>
                            <input type="text" name="user_id" class="form-control" placeholder="UUID" value="{{ request('user_id') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('From_Date') }}</label>
                            <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('To_Date') }}</label>
                            <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">{{ translate('Filter') }}</button>
                            <a href="{{ route('admin.chatbot.logs') }}" class="btn btn-outline-secondary">{{ translate('Reset') }}</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Chat_History') }} ({{ $logs->total() }} {{ translate('records') }})</h5>
                </div>
                <div class="card-body p-0">
                    @if($logs->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ translate('User_ID') }}</th>
                                        <th>{{ translate('Role') }}</th>
                                        <th>{{ translate('Content') }}</th>
                                        <th>{{ translate('Action') }}</th>
                                        <th>{{ translate('Date') }}</th>
                                        <th class="text-center">{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.chatbot.logs', ['user_id' => $log->user_id]) }}" class="text-primary">
                                                    {{ Str::limit($log->user_id, 8) }}...
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge {{ $log->role === 'user' ? 'bg-info' : 'bg-success' }}">
                                                    {{ $log->role }}
                                                </span>
                                            </td>
                                            <td class="text-truncate" style="max-width: 300px;" title="{{ $log->content }}">
                                                {{ Str::limit($log->content, 80) }}
                                            </td>
                                            <td>
                                                @if($log->action_type)
                                                    <code>{{ $log->action_type }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}</td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="clearUserHistory('{{ $log->user_id }}')"
                                                    title="{{ translate('Clear_User_History') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3">
                            {{ $logs->withQueryString()->links() }}
                        </div>
                    @else
                        <div class="text-center p-5">
                            <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}" alt="" class="mb-3" width="100">
                            <p class="text-muted">{{ translate('No_chat_logs_found') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
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
                    toastr.success('{{ translate("Chat_history_cleared") }}');
                    location.reload();
                } else {
                    toastr.error(data.error || '{{ translate("Failed_to_clear_history") }}');
                }
            })
            .catch(error => {
                toastr.error('{{ translate("An_error_occurred") }}');
            });
        }
    </script>
@endsection
