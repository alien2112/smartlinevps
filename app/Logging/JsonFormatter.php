<?php

namespace App\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Custom JSON formatter for structured logging
 * Formats log records as single-line JSON for easy parsing by log shippers
 */
class JsonFormatter extends NormalizerFormatter
{
    /**
     * Format a log record into a structured JSON string
     *
     * @param LogRecord $record
     * @return string
     */
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalize($record);

        $output = [
            '@timestamp' => $normalized['datetime'],
            'level' => strtolower($normalized['level_name']),
            'message' => $normalized['message'],
            'channel' => $normalized['channel'],
            'context' => $normalized['context'] ?? [],
            'extra' => $normalized['extra'] ?? [],
        ];

        // Add exception details if present
        if (isset($normalized['context']['exception']) && $normalized['context']['exception'] instanceof \Throwable) {
            $output['exception'] = [
                'class' => get_class($normalized['context']['exception']),
                'message' => $normalized['context']['exception']->getMessage(),
                'code' => $normalized['context']['exception']->getCode(),
                'file' => $normalized['context']['exception']->getFile(),
                'line' => $normalized['context']['exception']->getLine(),
                'trace' => $normalized['context']['exception']->getTraceAsString(),
            ];
            unset($output['context']['exception']);
        }

        // Flatten context if it exists
        if (!empty($output['context'])) {
            foreach ($output['context'] as $key => $value) {
                if (!isset($output[$key])) {
                    $output[$key] = $value;
                }
            }
        }
        unset($output['context']);

        // Remove empty extra field
        if (empty($output['extra'])) {
            unset($output['extra']);
        }

        return json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Format multiple log records
     *
     * @param array $records
     * @return string
     */
    public function formatBatch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }
        return $message;
    }

    /**
     * Normalize data for JSON encoding
     *
     * @param mixed $data
     * @param int $depth
     * @return mixed
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        // Prevent circular references
        if ($depth > 9) {
            return 'Over 9 levels deep, aborting normalization';
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format('Y-m-d H:i:s.u');
        }

        if (is_array($data)) {
            $normalized = [];
            foreach ($data as $key => $value) {
                $normalized[$key] = $this->normalize($value, $depth + 1);
            }
            return $normalized;
        }

        if (is_object($data)) {
            if ($data instanceof \Throwable) {
                return $data;
            }

            if (method_exists($data, 'toArray')) {
                return $this->normalize($data->toArray(), $depth + 1);
            }

            if (method_exists($data, '__toString')) {
                return (string) $data;
            }

            return sprintf('[object] (%s)', get_class($data));
        }

        if (is_resource($data)) {
            return sprintf('[resource] (%s)', get_resource_type($data));
        }

        return $data;
    }
}
