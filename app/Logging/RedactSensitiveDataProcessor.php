<?php

namespace App\Logging;

use App\Services\LogRedactionService;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    protected LogRedactionService $redactionService;

    public function __construct()
    {
        $this->redactionService = new LogRedactionService();
    }

    /**
     * Process log record and redact sensitive data
     *
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Redact context data
        if (!empty($record->context)) {
            $record->context = $this->redactionService->redactArray($record->context);
        }

        // Redact extra data
        if (!empty($record->extra)) {
            $record->extra = $this->redactionService->redactArray($record->extra);
        }

        // Redact message if it contains sensitive patterns
        if (is_string($record->message)) {
            $record->message = $this->redactSensitivePatterns($record->message);
        }

        return $record;
    }

    /**
     * Redact sensitive patterns from message string
     *
     * @param string $message
     * @return string
     */
    protected function redactSensitivePatterns(string $message): string
    {
        $patterns = [
            // Password in URLs or strings
            '/password[=:]\s*[^\s&]+/i' => 'password=***REDACTED***',
            // API keys
            '/api[_-]?key[=:]\s*[^\s&]+/i' => 'api_key=***REDACTED***',
            // Tokens
            '/token[=:]\s*[^\s&]+/i' => 'token=***REDACTED***',
            // Credit cards
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '****-****-****-****',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        return $message;
    }
}
