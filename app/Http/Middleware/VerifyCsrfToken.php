<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * NOTE: API endpoints should use API token authentication (Sanctum/Passport)
     * instead of being exempted from CSRF protection.
     * CSRF exemptions are only for webhook callbacks from external services.
     *
     * @var array<int, string>
     */
    protected $except = [
        // REMOVED: '/admin/auth/external-login-from-mart' - Use proper authentication instead
        // REMOVED: '/api/customer/update-customer-data' - Protected by API authentication
        // REMOVED: '/api/store-configurations' - Protected by API authentication
        // Add webhook URLs here if needed (e.g., payment gateway callbacks)
    ];
}
