<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Caps the size of one log entry. When failed_jobs could not be written
 * (disk full), Laravel logged the whole failed INSERT, with its serialized
 * notification, over and over: laravel.log reached 61 GB and filled the
 * disk. A message or exception longer than the cap is cut, and says so.
 */
class CapRecordSize
{
    public const MAX_CHARS = 20000;

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(fn (LogRecord $record) => $this->cap($record));
        }
    }

    public function cap(LogRecord $record): LogRecord
    {
        $context = $record->context;
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable && mb_strlen($value->getMessage()) > self::MAX_CHARS) {
                $context[$key] = sprintf('%s: %s [cut from %d characters] at %s:%d', $value::class,
                    mb_substr($value->getMessage(), 0, self::MAX_CHARS), mb_strlen($value->getMessage()), $value->getFile(), $value->getLine());
            } elseif (is_string($value) && mb_strlen($value) > self::MAX_CHARS) {
                $context[$key] = $this->cut($value);
            }
        }

        return $record->with(message: $this->cut($record->message), context: $context);
    }

    private function cut(string $text): string
    {
        return mb_strlen($text) > self::MAX_CHARS
            ? mb_substr($text, 0, self::MAX_CHARS) . ' [cut from ' . mb_strlen($text) . ' characters]'
            : $text;
    }
}
