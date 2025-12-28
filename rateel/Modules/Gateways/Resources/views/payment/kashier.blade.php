@extends('Gateways::payment.layouts.master')

@section('title', 'Kashier Payment')

@section('content')
<style>
    .payment-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 30px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .payment-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .payment-header h2 {
        color: #333;
        margin-bottom: 10px;
    }
    .payment-amount {
        font-size: 2em;
        color: #2ecc71;
        font-weight: bold;
    }
    .payment-info {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .payment-info p {
        margin: 8px 0;
        color: #555;
    }
    .payment-info strong {
        color: #333;
    }
    .secure-badge {
        text-align: center;
        margin-top: 20px;
        color: #888;
        font-size: 14px;
    }
    .secure-badge i {
        color: #2ecc71;
        margin-right: 5px;
    }
    #kashier-iframe-container {
        min-height: 400px;
    }
</style>

<div class="payment-container">
    <div class="payment-header">
        <h2>{{ __('Complete Your Payment') }}</h2>
        <div class="payment-amount">{{ $currency }} {{ number_format($amount, 2) }}</div>
    </div>

    <div class="payment-info">
        <p><strong>{{ __('Order ID') }}:</strong> {{ $orderId }}</p>
        <p><strong>{{ __('Customer') }}:</strong> {{ $payer->name ?? 'N/A' }}</p>
        <p><strong>{{ __('Email') }}:</strong> {{ $payer->email ?? 'N/A' }}</p>
    </div>

    <!-- Kashier Payment iframe container -->
    <div id="kashier-iframe-container"></div>

    <div class="secure-badge">
        <i class="fas fa-lock"></i> {{ __('Secured by Kashier') }}
    </div>
</div>

<!-- Kashier Payment SDK -->
<script 
    id="kashier-iFrame" 
    src="{{ $mode === 'live' ? 'https://checkout.kashier.io/kashier-checkout.js' : 'https://test-checkout.kashier.io/kashier-checkout.js' }}"
    data-amount="{{ $amount }}"
    data-hash="{{ $hash }}"
    data-currency="{{ $currency }}"
    data-orderId="{{ $orderId }}"
    data-merchantId="{{ $merchantId }}"
    data-mode="{{ $mode }}"
    data-merchantRedirect="{{ urlencode($callbackUrl) }}"
    data-serverWebhook="{{ urlencode($webhookUrl) }}"
    data-redirectMethod="get"
    data-failureRedirect="true"
    data-type="external"
    data-display="{{ $display }}"
    data-allowedMethods="card,wallet"
></script>
@endsection
