<?php

namespace App\Services\Finance\BankStatements\Parsers;

/**
 * Registry of all known bank statement parsers.
 *
 * Add a new parser by:
 *   1. Implementing BankStatementParserInterface (extending
 *      AbstractCsvParser is recommended for the helpers)
 *   2. Calling register() in this class' ctor or via service provider.
 *
 * Lookup is by:
 *   - explicit code (preferred)
 *   - auto-detect (falls back to scanning each parser's detect()
 *     against a sample of the file)
 *   - generic_csv as last resort
 */
class BankStatementParserRegistry
{
    /** @var array<string, BankStatementParserInterface> */
    protected array $parsers = [];

    public function __construct()
    {
        // Default registrations. Add new parsers here OR via register()
        // in a service provider.
        $this->register(new GcbCsvParser());
        $this->register(new GenericCsvParser());
    }

    public function register(BankStatementParserInterface $parser): void
    {
        $this->parsers[$parser->code()] = $parser;
    }

    /**
     * @return array<int, BankStatementParserInterface>
     */
    public function all(): array
    {
        return array_values($this->parsers);
    }

    public function get(string $code): ?BankStatementParserInterface
    {
        return $this->parsers[$code] ?? null;
    }

    /**
     * Pick the best parser for a file. Strategy:
     *   1. If $hint is supplied AND known, use it directly.
     *   2. Otherwise, try detect() on each non-generic parser. First
     *      true match wins.
     *   3. Fall back to generic_csv.
     */
    public function detect(string $sample, ?string $hint = null): BankStatementParserInterface
    {
        if ($hint && isset($this->parsers[$hint])) {
            return $this->parsers[$hint];
        }
        // Specific parsers first.
        foreach ($this->parsers as $code => $parser) {
            if ($code === 'generic_csv') continue;
            if ($parser->detect($sample)) {
                return $parser;
            }
        }
        // Generic fallback.
        if (isset($this->parsers['generic_csv'])) {
            return $this->parsers['generic_csv'];
        }
        throw new \RuntimeException(
            'No bank statement parser registered (not even generic_csv).'
        );
    }

    /**
     * @return array<string, string>  code => label
     */
    public function picker(): array
    {
        $out = [];
        foreach ($this->parsers as $code => $parser) {
            $out[$code] = $parser->label();
        }
        return $out;
    }
}
