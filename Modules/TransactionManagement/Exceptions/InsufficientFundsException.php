<?php

namespace Modules\TransactionManagement\Exceptions;

use Exception;

/**
 * Exception thrown when a wallet transaction fails due to insufficient funds.
 * 
 * This exception is used for atomic wallet operations where the balance
 * is re-checked inside a database lock to prevent race conditions.
 */
class InsufficientFundsException extends Exception
{
    protected $message = 'Insufficient wallet balance for this transaction';
    
    public function __construct(string $message = null, float $required = 0, float $available = 0)
    {
        $this->message = $message ?? $this->message;
        
        if ($required > 0 && $available >= 0) {
            $this->message .= sprintf('. Required: %.2f, Available: %.2f', $required, $available);
        }
        
        parent::__construct($this->message);
    }
}
