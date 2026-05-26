<?php

namespace Tests\Feature\Finance\Customers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifies that the Phase 2a migrations have actually added the new
 * columns and tables. These tests are a tripwire: if the migrations
 * silently fail or get partially applied on production, this catches
 * it before the controllers explode at runtime.
 */
class CounterpartyExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_categories_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('customer_categories'));
        foreach (['code', 'name', 'revenue_account_id', 'ar_account_id',
                  'default_payment_terms_days', 'default_credit_limit',
                  'default_discount_percent', 'is_active'] as $col) {
            $this->assertTrue(Schema::hasColumn('customer_categories', $col), "customer_categories.{$col} missing");
        }
    }

    public function test_counterparty_addresses_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('counterparty_addresses'));
        foreach (['counterparty_id', 'address_type', 'address_line_1',
                  'is_default', 'ghana_post_gps', 'latitude', 'longitude'] as $col) {
            $this->assertTrue(Schema::hasColumn('counterparty_addresses', $col), "counterparty_addresses.{$col} missing");
        }
    }

    public function test_counterparty_contacts_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('counterparty_contacts'));
        foreach (['counterparty_id', 'name', 'role', 'phone', 'email',
                  'whatsapp', 'is_primary'] as $col) {
            $this->assertTrue(Schema::hasColumn('counterparty_contacts', $col), "counterparty_contacts.{$col} missing");
        }
    }

    public function test_customer_profiles_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('customer_profiles'));
        foreach (['counterparty_id', 'user_id', 'email_subscribed',
                  'sms_subscribed', 'whatsapp_subscribed',
                  'first_order_at', 'lifetime_value'] as $col) {
            $this->assertTrue(Schema::hasColumn('customer_profiles', $col), "customer_profiles.{$col} missing");
        }
    }

    public function test_counterparty_documents_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('counterparty_documents'));
        foreach (['counterparty_id', 'document_type', 'expiry_date',
                  'storage_path', 'mime_type'] as $col) {
            $this->assertTrue(Schema::hasColumn('counterparty_documents', $col), "counterparty_documents.{$col} missing");
        }
    }

    public function test_counterparties_extended_columns_exist(): void
    {
        foreach (['customer_category_id', 'currency_code', 'discount_percent',
                  'on_credit_hold', 'credit_hold_reason', 'internal_notes',
                  'first_sale_at', 'last_sale_at', 'total_sales_value'] as $col) {
            $this->assertTrue(Schema::hasColumn('counterparties', $col), "counterparties.{$col} missing");
        }
    }
}
