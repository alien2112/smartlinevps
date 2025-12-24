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
    .btn-pay {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 25px;
        font-size: 18px;
        cursor: pointer;
        width: 100%;
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none;
        display: block;
        text-align: center;
    }
    .btn-pay:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }
    .loading-spinner {
        text-align: center;
        margin: 20px 0;
    }
    .loading-spinner .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p>{{ __('Redirecting to Kashier checkout...') }}</p>
    </div>

    <form id="kashierForm" action="{{ $mode === 'live' ? 'https://checkout.kashier.io' : 'https://checkout.kashier.io' }}" method="GET" style="display: none;">
        <input type="hidden" name="merchantId" value="{{ $merchantId }}">
        <input type="hidden" name="orderId" value="{{ $orderId }}">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="hash" value="{{ $hash }}">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="merchantRedirect" value="{{ $callbackUrl }}">
        <input type="hidden" name="serverWebhook" value="{{ $webhookUrl }}">
        <input type="hidden" name="display" value="{{ $display }}">
        <input type="hidden" name="type" value="external">
        <input type="hidden" name="redirectMethod" value="get">
        <input type="hidden" name="failureRedirect" value="true">

        <button type="submit" class="btn-pay">
            {{ __('Pay Now') }} ({{ $currency }} {{ number_format($amount, 2) }})
        </button>
    </form>

    <div class="secure-badge">
        <i class="fas fa-lock"></i> {{ __('Secured by Kashier') }}
    </div>
</div>

<script>
(function() {
    // Auto-submit form after a brief delay to show loading state
    setTimeout(function() {
        var form = document.getElementById('kashierForm');
        form.style.display = 'block';
        document.getElementById('loadingSpinner').style.display = 'none';

        // Auto-submit after showing the button briefly
        setTimeout(function() {
            form.submit();
        }, 500);
    }, 1000);
})();
</script>
@endsection
