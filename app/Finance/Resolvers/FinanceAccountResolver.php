<?php

namespace App\Finance\Resolvers;

use App\Models\Finance\Account;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Log;

/**
 * Resolves an account by its COA code, with caching, for subscribers.
 *
 * Subscribers don't hardcode account ids — they ask the resolver for an
 * account by code ('1010', '4010', etc). The resolver caches lookups for
 * the duration of the request to avoid hammering the DB during a batch
 * of postings.
 *
 * Why a separate service vs querying Account directly?
 *   - Caching. A subscriber posting 50 events in a batch shouldn't run
 *     50 SELECT queries for the same account.
 *   - Org-aware lookup. Resolver applies CurrentOrganization scope by default.
 *   - Hint resolution. PaymentMethod stores `gl_account_hint` as a symbolic
 *     string ('CASH_ON_HAND', 'MOMO_MTN'). Resolver translates these to
 *     COA codes via a lookup table.
 *   - Missing-account handling. A misconfigured COA (account doesn't exist
 *     for the code we ask for) is a permanent failure that should surface
 *     loudly. Resolver throws a typed exception so subscribers can catch
 *     and report cleanly.
 */
class FinanceAccountResolver
{
    /** @var array<string, Account|null> */
    protected array $cache = [];

    /**
     * Symbolic hint → COA code map. Used by PaymentMethod resolution
     * (PaymentMethodSeeder stores symbolic hints, not real codes).
     */
    protected const HINT_TO_CODE = [
        'CASH_ON_HAND'        => '1010',
        'PETTY_CASH'          => '1020',
        'BANK_MAIN'           => '1030',
        'BANK_SAVINGS'        => '1040',
        'BANK_FOREIGN'        => '1050',
        'MOMO_MTN'            => '1060',
        'MOMO_ATGO'           => '1061',
        'MOMO_VODA'           => '1062',

        'AR_PIG_SALES'        => '1110',
        'AR_FEED_SALES'       => '1120',
        'AR_OTHER'            => '1130',

        'AP_TRADE'            => '2010',
        'AP_FEED'             => '2020',
        'AP_VET'              => '2030',
        'AP_OTHER'            => '2090',

        'VAT_CONTROL'         => '2210',
        'VAT_INPUT'           => '2211',
        'NHIL_PAYABLE'        => '2220',
        'GETFUND_PAYABLE'     => '2230',
        'COVID_LEVY_PAYABLE'  => '2240',
        'WHT_PAYABLE'         => '2250',
        'PAYE_PAYABLE'        => '2260',
        'SSNIT_PAYABLE'       => '2270',

        'CUSTOMER_PREPAYMENT_PIG'   => '2310',
        'CUSTOMER_PREPAYMENT_FEED'  => '2320',
        'CUSTOMER_PREPAYMENT_OTHER' => '2390',

        'SUSPENSE_RECEIPT'    => '1910',
        'SUSPENSE_PAYMENT'    => '2910',
    ];

    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * Resolve an account by its COA code. Throws if not found or inactive.
     */
    public function byCode(string $code): Account
    {
        $organizationId = $this->currentOrganization->id();
        $cacheKey = "code:{$code}:org:" . ($organizationId ?? 'null');

        if (array_key_exists($cacheKey, $this->cache)) {
            $cached = $this->cache[$cacheKey];
            if ($cached === null) {
                throw new MissingAccountException($code);
            }
            return $cached;
        }

        $account = Account::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('code', $code)
            ->first();

        $this->cache[$cacheKey] = $account;

        if (! $account) {
            Log::error("FinanceAccountResolver: account code '{$code}' not found", [
                'organization_id' => $organizationId,
            ]);
            throw new MissingAccountException($code);
        }

        if (! $account->is_active) {
            Log::error("FinanceAccountResolver: account code '{$code}' is inactive", [
                'organization_id' => $organizationId,
                'account_id'      => $account->id,
            ]);
            throw new MissingAccountException($code, isInactive: true);
        }

        return $account;
    }

    /**
     * Resolve an account by symbolic hint (e.g. 'CASH_ON_HAND').
     */
    public function byHint(string $hint): Account
    {
        $code = self::HINT_TO_CODE[$hint] ?? null;

        if (! $code) {
            throw new MissingAccountException("hint '{$hint}' (no code mapping)");
        }

        return $this->byCode($code);
    }

    /**
     * Reset the in-memory cache. Used by tests; rarely needed in production.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
