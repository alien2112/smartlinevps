@section('title', translate('AI_Chatbot_Manager'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <h2 class="fs-22 text-capitalize">{{ translate('AI_Chatbot_Manager') }}</h2>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">{{ translate('Configuration') }}</h5>
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
                                        placeholder="You are a helpful assistant for SmartLine..." required>{{ isset($prompt) ? $prompt->value : '' }}</textarea>
                                    <small class="text-muted">{{ translate('Define_how_the_AI_should_behave_and_what_it_knows_about_your_business.') }}</small>
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
                            <a href="#" class="fs-12">{{ translate('View_All') }}</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="text-center p-4">
                                <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}" alt="" class="mb-3" width="80">
                                <p class="text-muted">{{ translate('No_logs_available_yet') }}</p>
                            </div>
                            {{-- 
                            Example log item structure:
                            <div class="border-bottom p-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold fs-12">User #123</span>
                                    <span class="fs-10 text-muted">2 mins ago</span>
                                </div>
                                <p class="fs-12 mb-0 text-truncate">How do I book a ride to the airport?</p>
                            </div> 
                            --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
