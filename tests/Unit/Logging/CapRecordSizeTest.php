<?php

namespace Tests\Unit\Logging;

use App\Logging\CapRecordSize;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/** One log entry cannot be megabytes long (the 61 GB laravel.log incident). */
class CapRecordSizeTest extends TestCase
{
    public function test_huge_messages_and_exceptions_are_cut(): void
    {
        $huge = str_repeat('x', 500000);
        $record = new LogRecord(new \DateTimeImmutable(), 'local', Level::Error, $huge, [
            'exception' => new \RuntimeException('insert into failed_jobs ' . $huge),
            'sql' => $huge,
            'small' => 'kept',
        ]);

        $capped = (new CapRecordSize())->cap($record);

        $this->assertLessThan(CapRecordSize::MAX_CHARS + 100, mb_strlen($capped->message));
        $this->assertStringEndsWith('[cut from 500000 characters]', $capped->message);
        $this->assertIsString($capped->context['exception']);
        $this->assertStringStartsWith('RuntimeException: insert into failed_jobs', $capped->context['exception']);
        $this->assertLessThan(CapRecordSize::MAX_CHARS + 300, mb_strlen($capped->context['exception']));
        $this->assertLessThan(CapRecordSize::MAX_CHARS + 100, mb_strlen($capped->context['sql']));
        $this->assertSame('kept', $capped->context['small']);
    }

    public function test_ordinary_entries_are_untouched(): void
    {
        $e = new \RuntimeException('short');
        $record = new LogRecord(new \DateTimeImmutable(), 'local', Level::Error, 'hello', ['exception' => $e]);

        $capped = (new CapRecordSize())->cap($record);

        $this->assertSame('hello', $capped->message);
        $this->assertSame($e, $capped->context['exception']);
    }
}
