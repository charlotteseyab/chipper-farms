<?php

/**
 * Centralised finance module configuration.
 *
 * Introduced in Phase 2d. Subsequent finance phases will add keys here
 * rather than scattering settings across env vars.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Where to send alerts from finance commands (reconciliation drift,
    | backfill failures, scheduled-job errors). Comma-separated list of
    | email addresses, or null to disable.
    */
    'alert_email' => env('FINANCE_ALERT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    'attachments' => [

        // The filesystem disk for finance attachments (cheques, MoMo screenshots,
        // signed delivery notes, supplier invoices, etc.).
        // Production: should be 's3' or another object store.
        // Dev/staging: 'local' is acceptable.
        'disk' => env('FINANCE_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),

        // Maximum upload size in MB. Reject larger uploads at the app layer
        // before they hit disk.
        'max_size_mb' => env('FINANCE_ATTACHMENTS_MAX_MB', 20),

        // Allowed MIME types. Office formats deliberately excluded.
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
        ],

        // Signed URL expiry for downloads, in minutes.
        // Short = harder to share by accident; long = less friction for users.
        'signed_url_minutes' => env('FINANCE_ATTACHMENTS_URL_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | AR Ageing
    |--------------------------------------------------------------------------
    */
    'ageing' => [

        // The four buckets in days, exclusive upper bounds.
        // 'current' = invoices not yet due
        // bucket_1  = 1-30 days overdue
        // bucket_2  = 31-60 days overdue
        // bucket_3  = 61-90 days overdue
        // bucket_4  = 90+ days overdue
        // The buckets are configurable per industry expectation;
        // defaults match the standard "current/30/60/90/90+" pattern.
        'bucket_days' => [30, 60, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Statements
    |--------------------------------------------------------------------------
    */
    'statements' => [

        // Default period for activity statements when start_date is omitted.
        // 'current_month'  | 'last_30_days' | 'last_60_days' | 'last_90_days'
        'default_period' => env('FINANCE_STATEMENT_DEFAULT_PERIOD', 'current_month'),

        // Company branding shown in the statement header.
        // Do not call config() inside config files; these load before
        // the config repository is fully bound in the container.
        'company_name'    => env('FINANCE_COMPANY_NAME', env('APP_NAME', 'Laravel')),
        'company_address' => env('FINANCE_COMPANY_ADDRESS'),
        'company_phone'   => env('FINANCE_COMPANY_PHONE'),
        'company_email'   => env('FINANCE_COMPANY_EMAIL'),
        'company_tin'     => env('FINANCE_COMPANY_TIN'),

        // Footer text shown on every statement (terms reminder, banking details).
        'footer_text' => env('FINANCE_STATEMENT_FOOTER',
            'All amounts in GHS unless otherwise stated. Please remit to the bank account on your invoice. ' .
            'For queries contact accounts within 14 days of receipt.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Control
    |--------------------------------------------------------------------------
    */
    'credit_control' => [

        // Default credit_hold_mode for new customers.
        // Options: 'none' | 'warn' | 'block'
        'default_hold_mode' => env('FINANCE_DEFAULT_CREDIT_HOLD', 'warn'),

        // Whether the pig sale form should call CreditControlService.
        // Off by default during 2d rollout — operations decides when to enable
        // the warning UX. Phase 6+ will tighten this.
        'enforce_in_pig_sales' => env('FINANCE_CREDIT_HOLD_ENFORCE', false),
    ],

    'use_vendor_invoices_for_purchase_requests' => env('FINANCE_USE_VENDOR_INVOICES_FOR_PRS', false),

    'vendor_invoices' => [
        // Three-way matching tolerances (Decision 26)
        'match_tolerance_qty_pct'   => env('FINANCE_VI_MATCH_QTY_TOL', 5.0),
        'match_tolerance_price_pct' => env('FINANCE_VI_MATCH_PRICE_TOL', 2.0),

        // Two-person rule (Decision 30)
        // When true, the user who created a VI cannot also be the user who
        // approves and posts it. Enforced by VendorInvoiceController::post().
        'enforce_two_person_rule' => env('FINANCE_VI_TWO_PERSON_RULE', true),

        // Future: auto-approval for clean three-way matches
        // (not used in Phase 3b — every VI requires manual approval)
        'auto_approve_clean_match' => env('FINANCE_VI_AUTO_APPROVE_CLEAN', false),
    ],

];
