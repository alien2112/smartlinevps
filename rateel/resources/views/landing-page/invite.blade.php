@extends('landing-page.layouts.master')
@section('title', translate('Join Smart line'))

@section('content')
<style>
    .invite-section {
        min-height: 80vh;
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 80px 0;
    }
    .invite-card {
        background: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        text-align: center;
        max-width: 600px;
        margin: 0 auto;
    }
    .invite-title {
        font-size: 2.5rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 20px;
    }
    .invite-subtitle {
        font-size: 1.2rem;
        color: #666;
        margin-bottom: 30px;
    }
    .bonus-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin: 30px 0;
    }
    .bonus-amount {
        font-size: 4rem;
        font-weight: bold;
        margin-bottom: 10px;
    }
    .bonus-label {
        font-size: 1.3rem;
        opacity: 0.9;
    }
    .referral-code {
        background: #f8f9fa;
        padding: 15px 25px;
        border-radius: 10px;
        font-size: 1.5rem;
        font-weight: bold;
        color: #667eea;
        margin: 20px 0;
        display: inline-block;
        letter-spacing: 2px;
    }
    .download-section {
        margin-top: 40px;
        padding-top: 30px;
        border-top: 2px solid #e9ecef;
    }
    .download-title {
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 20px;
    }
    .app-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .app-button {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 25px;
        background: #000;
        color: white;
        border-radius: 10px;
        text-decoration: none;
        transition: transform 0.2s;
    }
    .app-button:hover {
        transform: translateY(-2px);
        color: white;
    }
    .app-button img {
        width: 30px;
        height: 30px;
    }
</style>

<!-- Invite Section Start -->
<section class="invite-section">
    <div class="container">
        <div class="invite-card">
            <h1 class="invite-title">{{ translate('You\'ve Been Invited!') }}</h1>
            <p class="invite-subtitle">
                {{ $user?->first_name ?? translate('Someone') }} {{ translate('has invited you to join') }} {{ $businessName }}
            </p>
            
            <div class="bonus-box">
                <div class="bonus-amount">{{ $bonusPoints }}</div>
                <div class="bonus-label">{{ translate('Bonus Points') }}</div>
            </div>
            
            <p style="font-size: 1.1rem; color: #666; margin: 20px 0;">
                {{ translate('Sign up with code') }} <span class="referral-code">{{ $code }}</span> {{ translate('to earn your reward!') }}
            </p>
            
            <div class="download-section">
                <h2 class="download-title">{{ translate('Download the App') }}</h2>
                <p style="color: #666; margin-bottom: 20px;">
                    {{ translate('Get started by downloading our app and use referral code:') }} <strong style="color: #667eea;">{{ $code }}</strong>
                </p>
                
                <div class="app-buttons">
                    @if($cta?->value && isset($cta->value['play_store']['user_download_link']) && $cta->value['play_store']['user_download_link'])
                        <a href="{{ $cta->value['play_store']['user_download_link'] }}" class="app-button" target="_blank">
                            <img src="{{ asset('public/landing-page/assets/img/play-fav.png') }}" alt="Google Play">
                            <span>{{ translate('GET IT ON') }} {{ translate('Google Play') }}</span>
                        </a>
                    @endif
                    
                    @if($cta?->value && isset($cta->value['app_store']['user_download_link']) && $cta->value['app_store']['user_download_link'])
                        <a href="{{ $cta->value['app_store']['user_download_link'] }}" class="app-button" target="_blank">
                            <img src="{{ asset('public/landing-page/assets/img/apple-fav.png') }}" alt="App Store">
                            <span>{{ translate('DOWNLOAD ON THE') }} {{ translate('App Store') }}</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Invite Section End -->
@endsection