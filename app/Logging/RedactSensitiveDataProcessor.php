<?php

namespace App\Logging;

use App\Services\LogRedactionService;
use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Laravel Logger Tap that adds a Monolog processor for redacting sensitive data.
 * Used in config/logging.php 'tap' configuration.
 */
class RedactSensitiveDataProcessor
{
    /**
     * Customize the given logger instance (Laravel tap interface).
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new RedactProcessor());
        }
    }
}

/**
 * Monolog Processor that redacts sensitive data from log records.
 */
class RedactProcessor implements ProcessorInterface
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
        $context = $record->context;
        $extra = $record->extra;
        $message = $record->message;

        // Redact context data
        if (!empty($context)) {
            $context = $this->redactionService->redactArray($context);
        }

        // Redact extra data
        if (!empty($extra)) {
            $extra = $this->redactionService->redactArray($extra);
        }

        // Redact message if it contains sensitive patterns
        if (is_string($message)) {
            $message = $this->redactSensitivePatterns($message);
        }

        return new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $message,
            $context,
            $extra,
            $record->formatted
        );
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
