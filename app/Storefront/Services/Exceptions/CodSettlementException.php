<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Raised when a cash-on-delivery order cannot be settled, so it must not be
 * marked paid or completed (CF-P0-009).
 *
 * OrderService::complete() used to call settleCodPayments() and then set
 * payment_status = 'paid' UNCONDITIONALLY. settleCodPayments() skipped any line
 * without a posted sale, counted them, and wrote a note — after which completion
 * carried on regardless. An order every one of whose lines failed to settle
 * still ended up 'paid' and 'completed'.
 *
 * The note was the tell: the code knew something had gone wrong and recorded it,
 * but recorded it in a place nothing acts on. A note saying a line was skipped is
 * not settlement, and the difference is cash — the business believes it has been
 * paid, no receipt exists, and the receivable is closed against nothing.
 *
 * Settlement failure is now fatal to completion. The order stays where it is,
 * the failure is recorded as a visible event, and staff can retry once the
 * underlying sale exists (the outbox posts sales asynchronously, so "not yet"
 * is a normal, recoverable state rather than an error).
 */
class CodSettlementException extends RuntimeException
{
    /** @var list<string> Human-readable reasons, one per unsettleable line. */
    protected array $reasons;

    /**
     * @param  list<string>  $reasons
     */
    public function __construct(string $message, array $reasons = [])
    {
        $this->reasons = $reasons;

        parent::__construct($message);
    }

    /** @return list<string> */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
