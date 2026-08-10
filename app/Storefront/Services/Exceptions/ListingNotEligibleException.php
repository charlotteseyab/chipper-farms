<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * An animal cannot be offered for sale (Phase 11, blueprint §35 step 2).
 *
 * Carries every reason rather than the first one. A staff member standing in a
 * barn should learn everything that is wrong in one go — finding out about the
 * withdrawal period, fixing it a fortnight later, and only then being told
 * about the missing weight is the same information delivered as badly as
 * possible.
 */
class ListingNotEligibleException extends RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly array $reasons,
    ) {
        parent::__construct(
            $reasons === []
                ? 'This pig cannot be listed for sale.'
                : 'This pig cannot be listed for sale: ' . implode(' ', $reasons),
        );
    }
}
