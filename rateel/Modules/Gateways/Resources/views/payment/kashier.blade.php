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
    }
    .btn-pay:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    .btn-pay:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .loading-spinner {
        display: none;
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
    .kashier-iframe-container {
        display: none;
        width: 100%;
        min-height: 500px;
        border: none;
    }
</style>

<div class="payment-container">
    <div class="payment-header">
        <h2>Complete Your Payment</h2>
        <div class="payment-amount">{{ $currency }} {{ number_format($amount, 2) }}</div>
    </div>

    <div class="payment-info">
        <p><strong>Order ID:</strong> {{ $orderId }}</p>
        <p><strong>Customer:</strong> {{ $payer->name ?? 'N/A' }}</p>
        <p><strong>Email:</strong> {{ $payer->email ?? 'N/A' }}</p>
    </div>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
        <p>Redirecting to Kashier...</p>
    </div>

    <div id="paymentFormContainer">
        <form id="kashierPaymentForm" method="POST" action="https://checkout.kashier.io/">
            <input type="hidden" name="merchantId" value="{{ $merchantId }}">
            <input type="hidden" name="orderId" value="{{ $orderId }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="hash" value="{{ $hash }}">
            <input type="hidden" name="mode" value="{{ config('app.env') === 'production' ? 'live' : 'test' }}">
            <input type="hidden" name="merchantRedirect" value="{{ $redirectUrl }}">
            <input type="hidden" name="serverWebhook" value="{{ route('kashier.webhook') }}">
            <input type="hidden" name="allowedMethods" value="card,wallet">
            <input type="hidden" name="display" value="en">
            <input type="hidden" name="brandColor" value="#667eea">
            
            @if(isset($payer->email))
            <input type="hidden" name="customerEmail" value="{{ $payer->email }}">
            @endif
            @if(isset($payer->phone))
            <input type="hidden" name="customerPhone" value="{{ $payer->phone }}">
            @endif
            @if(isset($payer->name))
            <input type="hidden" name="customerName" value="{{ $payer->name }}">
            @endif

            <button type="submit" class="btn-pay" id="payButton">
                Pay Now ({{ $currency }} {{ number_format($amount, 2) }})
            </button>
        </form>
    </div>

    <!-- Alternative: Kashier iFrame Container -->
    <div id="kashierIframeContainer" class="kashier-iframe-container"></div>

    <div class="secure-badge">
        <i class="fas fa-lock"></i> Secured by Kashier
    </div>
</div>

<script>
document.getElementById('kashierPaymentForm').addEventListener('submit', function(e) {
    document.getElementById('payButton').disabled = true;
    document.getElementById('loadingSpinner').style.display = 'block';
});
</script>
@endsection
