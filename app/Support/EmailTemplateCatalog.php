<?php

namespace App\Support;

final class EmailTemplateCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $orderVars = ['customer_name', 'order_reference', 'order_total', 'payment_status', 'order_status', 'fulfilment_method', 'store_name', 'support_email', 'support_phone', 'action_url', 'create_account_url'];

        $definitions = [
            'storefront.order_placed' => self::item('Order received', 'Storefront orders', 'customer',
                'We received order {{ order_reference }}',
                "Hello {{ customer_name }},\n\nWe have received your order {{ order_reference }} for {{ order_total }}. Its current status is {{ order_status }} and payment is {{ payment_status }}.\n\nYou can track the order using the button below. If you checked out as a guest, you can also create a buyer account from the tracking page.",
                'Track order', $orderVars),
            'storefront.payment_confirmed' => self::item('Payment confirmed', 'Storefront orders', 'customer',
                'Payment confirmed for {{ order_reference }}',
                "Hello {{ customer_name }},\n\nYour payment for order {{ order_reference }} has been received, and your order is reserved for you. We're getting it ready and will let you know as soon as it is.",
                'View order', $orderVars),
            'storefront.order_confirmed' => self::item('Order confirmed', 'Storefront orders', 'customer',
                'Order {{ order_reference }} is confirmed',
                "Hello {{ customer_name }},\n\nYour order {{ order_reference }} has been confirmed and is being prepared for {{ fulfilment_method }}.",
                'View order', $orderVars),
            'storefront.ready_for_pickup' => self::item('Ready for pickup', 'Storefront orders', 'customer',
                'Order {{ order_reference }} is ready for pickup',
                "Hello {{ customer_name }},\n\nYour order {{ order_reference }} is ready for pickup. Contact the farm if you need help arranging collection.",
                'View pickup details', $orderVars),
            'storefront.out_for_delivery' => self::item('Out for delivery', 'Storefront orders', 'customer',
                'Order {{ order_reference }} is out for delivery',
                "Hello {{ customer_name }},\n\nYour order {{ order_reference }} is now out for delivery.",
                'Track order', $orderVars),
            'storefront.order_completed' => self::item('Order completed', 'Storefront orders', 'customer',
                'Order {{ order_reference }} completed',
                "Hello {{ customer_name }},\n\nOrder {{ order_reference }} has been completed. Thank you for buying from {{ store_name }}.",
                'View order', $orderVars),
            'storefront.order_cancelled' => self::item('Order cancelled', 'Storefront orders', 'customer',
                'Order {{ order_reference }} was cancelled',
                "Hello {{ customer_name }},\n\nOrder {{ order_reference }} has been cancelled. If payment had already been confirmed, contact the farm about the refund status.",
                'View order', $orderVars),
            'storefront.payment_failed' => self::item('Payment unsuccessful', 'Storefront orders', 'customer',
                'Payment was not completed for {{ order_reference }}',
                "Hello {{ customer_name }},\n\nPayment for order {{ order_reference }} was not completed. The order will not be fulfilled unless a new checkout succeeds.",
                'Return to shop', $orderVars),
            'storefront.buyer_welcome' => self::item('Buyer account welcome', 'Buyer accounts', 'customer',
                'Welcome to {{ store_name }}',
                "Hello {{ customer_name }},\n\nYour buyer account is ready. Order {{ order_reference }} has been saved to your account and future signed-in orders will appear in the same place.",
                'View my orders', $orderVars),
            'storefront.quote_received' => self::item('Quote request received', 'Storefront quotes', 'customer',
                'We received quote request {{ quote_reference }}',
                "Hello {{ customer_name }},\n\nWe received your quote request {{ quote_reference }}. The farm will review it and contact you with pricing and availability.",
                null, ['customer_name', 'quote_reference', 'store_name', 'support_email', 'support_phone']),
            'storefront.quote_priced' => self::item('Quote price sent', 'Storefront quotes', 'customer',
                'Your quote {{ quote_reference }} is ready',
                "Hello {{ customer_name }},\n\nYour quote {{ quote_reference }} is ready. The quoted total is {{ quoted_total }} and it is valid until {{ valid_until }}. Sign in to your buyer account to monitor its progress.",
                'View my quote', ['customer_name', 'quote_reference', 'quoted_total', 'valid_until', 'action_url', 'store_name', 'support_email', 'support_phone']),
            'operations.gestation_due' => self::item('Gestation reminder', 'Farm reminders', 'staff',
                'Gestation reminder: {{ pig_tag }}',
                "{{ pig_tag }} has a gestation activity due on {{ due_date }}. Open the farm system to review the animal and record the activity.",
                'Review animal', ['pig_tag', 'due_date', 'action_url', 'store_name']),
            'operations.weaning_due' => self::item('Weaning reminder', 'Farm reminders', 'staff',
                'Weaning reminder: {{ pig_tag }}',
                "A litter for {{ pig_tag }} is due for weaning on {{ due_date }}. Open the farm system to review and record the activity.",
                'Review litter', ['pig_tag', 'due_date', 'action_url', 'store_name']),
            'operations.weight_due' => self::item('Weight reminder', 'Farm reminders', 'staff',
                'Weight reminder: {{ pig_tag }}',
                "A weight entry for {{ pig_tag }} is due on {{ due_date }}. Open the farm system to record the latest weight.",
                'Record weight', ['pig_tag', 'due_date', 'action_url', 'store_name']),
            'storefront.quote_staff_alert' => self::item('New quote staff alert', 'Staff alerts', 'staff',
                'New storefront quote {{ quote_reference }}',
                "A new quote request {{ quote_reference }} was submitted by {{ customer_name }}. Open the storefront workspace to review it.",
                'Review quote', ['quote_reference', 'customer_name', 'action_url', 'store_name']),
        ];

        return array_merge($definitions, self::moduleDefinitions());
    }

    /** @return array<string, array<string, mixed>> */
    private static function moduleDefinitions(): array
    {
        $staff = ['recipient_name', 'record_reference', 'status', 'due_date', 'action_url', 'store_name', 'actor_name', 'notes'];
        $finance = array_merge($staff, ['customer_name', 'supplier_name', 'document_number', 'amount', 'balance_due']);

        return [
            // Pig operations and facilities.
            'pigs.stock_intake_received' => self::item('Purchased stock received', 'Pig operations', 'staff', 'Stock intake {{ record_reference }} received', "Hello {{ recipient_name }},\n\nPurchased stock intake {{ record_reference }} has been received. Open the record to review the animals and intake details.", 'View intake', $staff),
            'pigs.transfer_completed' => self::item('Pig transfer completed', 'Pig operations', 'staff', 'Pig transfer {{ record_reference }} completed', "Pig transfer {{ record_reference }} has been completed by {{ actor_name }}. Review the animal location if follow-up is required.", 'View pig', $staff),
            'pigs.archived' => self::item('Pig archived', 'Pig operations', 'staff', 'Pig {{ record_reference }} archived', "Pig {{ record_reference }} has been archived. Reason or notes: {{ notes }}", 'View audit trail', $staff),
            'pigs.death_recorded' => self::item('Death recorded', 'Pig operations', 'staff', 'Death recorded for {{ record_reference }}', "A death has been recorded for {{ record_reference }} by {{ actor_name }}. Review the record and any required follow-up.", 'Review record', $staff),
            'facilities.capacity_alert' => self::item('Room capacity alert', 'Structures and rooms', 'staff', 'Capacity alert: {{ record_reference }}', "{{ record_reference }} has reached or exceeded its configured capacity. Review room allocation before moving more animals.", 'Review room', $staff),

            // Breeding and lifecycle.
            'breeding.mating_scheduled' => self::item('Mating scheduled', 'Breeding and lifecycle', 'staff', 'Mating scheduled for {{ record_reference }}', "A mating has been scheduled for {{ record_reference }}. The next required date is {{ due_date }}.", 'View mating', $staff),
            'breeding.pregnancy_check_due' => self::item('Pregnancy check due', 'Breeding and lifecycle', 'staff', 'Pregnancy check due for {{ record_reference }}', "A pregnancy check for {{ record_reference }} is due on {{ due_date }}.", 'Record check', $staff),
            'breeding.farrowing_due' => self::item('Farrowing due', 'Breeding and lifecycle', 'staff', 'Farrowing due for {{ record_reference }}', "Expected farrowing for {{ record_reference }} is due on {{ due_date }}. Review the sow and prepare the required facilities.", 'Review gestation', $staff),
            'breeding.farrowing_recorded' => self::item('Farrowing recorded', 'Breeding and lifecycle', 'staff', 'Farrowing recorded for {{ record_reference }}', "A farrowing record has been completed for {{ record_reference }} by {{ actor_name }}.", 'View record', $staff),
            'breeding.weaning_completed' => self::item('Weaning completed', 'Breeding and lifecycle', 'staff', 'Weaning completed for {{ record_reference }}', "Weaning has been completed for {{ record_reference }}. Review the litter and post-weaning records.", 'View weaning', $staff),

            // Monitoring, health, and weights.
            'health.condition_alert' => self::item('Health condition alert', 'Health and medication', 'staff', 'Health alert for {{ record_reference }}', "A health condition requiring attention was recorded for {{ record_reference }}. Notes: {{ notes }}", 'Review health record', $staff),
            'health.treatment_due' => self::item('Treatment due', 'Health and medication', 'staff', 'Treatment due for {{ record_reference }}', "A treatment for {{ record_reference }} is due on {{ due_date }}. Open the health record before administering or recording treatment.", 'Review treatment', $staff),
            'health.withdrawal_ending' => self::item('Withdrawal period ending', 'Health and medication', 'staff', 'Withdrawal period update for {{ record_reference }}', "The recorded medication withdrawal period for {{ record_reference }} ends on {{ due_date }}.", 'View medication record', $staff),
            'health.medication_low_stock' => self::item('Medication stock alert', 'Health and medication', 'staff', 'Medication stock alert: {{ record_reference }}', "Medication {{ record_reference }} is at or below its reorder threshold.", 'Review medication', $staff),
            'weights.abnormal_gain' => self::item('Abnormal weight change', 'Weight management', 'staff', 'Weight alert for {{ record_reference }}', "The latest weight for {{ record_reference }} is outside the expected range. Review the measurement and animal health.", 'Review weight', $staff),
            'weights.entry_corrected' => self::item('Weight entry corrected', 'Weight management', 'staff', 'Weight record corrected for {{ record_reference }}', "A weight record for {{ record_reference }} was corrected by {{ actor_name }}. Notes: {{ notes }}", 'View audit', $staff),

            // Feed, procurement, and inventory.
            'procurement.request_submitted' => self::item('Purchase request submitted', 'Feed and procurement', 'staff', 'Purchase request {{ record_reference }} submitted', "Purchase request {{ record_reference }} has been submitted and is waiting for review.", 'Review request', $staff),
            'procurement.request_approved' => self::item('Purchase request approved', 'Feed and procurement', 'staff', 'Purchase request {{ record_reference }} approved', "Purchase request {{ record_reference }} has been approved by {{ actor_name }}.", 'View request', $staff),
            'procurement.request_declined' => self::item('Purchase request declined', 'Feed and procurement', 'staff', 'Purchase request {{ record_reference }} declined', "Purchase request {{ record_reference }} was declined. Notes: {{ notes }}", 'View request', $staff),
            'procurement.request_paid' => self::item('Purchase request paid', 'Feed and procurement', 'staff', 'Purchase request {{ record_reference }} marked paid', "Payment for purchase request {{ record_reference }} has been recorded.", 'View request', $staff),
            'feed.raw_material_low' => self::item('Raw material stock alert', 'Feed and procurement', 'staff', 'Low raw material: {{ record_reference }}', "Raw material {{ record_reference }} is at or below its reorder level.", 'Review stock', $staff),
            'feed.production_completed' => self::item('Feed production completed', 'Feed and procurement', 'staff', 'Feed production {{ record_reference }} completed', "Feed production batch {{ record_reference }} has been completed by {{ actor_name }}.", 'View production', $staff),
            'feed.issue_reported' => self::item('Feed issue reported', 'Feed and procurement', 'staff', 'Feed issue {{ record_reference }} requires attention', "A feed issue was reported. Status: {{ status }}. Notes: {{ notes }}", 'Review issue', $staff),
            'inventory.low_stock' => self::item('Inventory low-stock alert', 'Inventory and costing', 'staff', 'Low inventory: {{ record_reference }}', "Inventory item {{ record_reference }} is at or below its configured threshold.", 'Review inventory', $staff),
            'inventory.valuation_exception' => self::item('Inventory valuation exception', 'Inventory and costing', 'staff', 'Inventory valuation needs attention', "Inventory record {{ record_reference }} could not be valued or posted correctly. Notes: {{ notes }}", 'Review exception', $staff),

            // Sales and finance.
            'sales.pig_sale_recorded' => self::item('Pig sale recorded', 'Sales', 'staff', 'Pig sale {{ record_reference }} recorded', "Pig sale {{ record_reference }} has been recorded for {{ amount }}. Status: {{ status }}.", 'View sale', $finance),
            'finance.invoice_issued' => self::item('Customer invoice issued', 'Finance', 'customer', 'Invoice {{ document_number }} from {{ store_name }}', "Hello {{ customer_name }},\n\nInvoice {{ document_number }} for {{ amount }} has been issued. The due date is {{ due_date }}.", 'View invoice', $finance),
            'finance.invoice_due' => self::item('Invoice payment reminder', 'Finance', 'customer', 'Invoice {{ document_number }} is due', "Hello {{ customer_name }},\n\nInvoice {{ document_number }} has a balance of {{ balance_due }} due on {{ due_date }}.", 'View invoice', $finance),
            'finance.invoice_overdue' => self::item('Overdue invoice reminder', 'Finance', 'customer', 'Invoice {{ document_number }} is overdue', "Hello {{ customer_name }},\n\nInvoice {{ document_number }} is overdue with {{ balance_due }} outstanding. Contact us if payment has already been made.", 'View invoice', $finance),
            'finance.receipt_issued' => self::item('Payment receipt', 'Finance', 'customer', 'Receipt {{ document_number }} from {{ store_name }}', "Hello {{ customer_name }},\n\nWe recorded your payment of {{ amount }}. Receipt number: {{ document_number }}.", 'View receipt', $finance),
            'finance.credit_note_issued' => self::item('Credit note issued', 'Finance', 'customer', 'Credit note {{ document_number }} issued', "Hello {{ customer_name }},\n\nCredit note {{ document_number }} for {{ amount }} has been issued to your account.", 'View credit note', $finance),
            'finance.refund_processed' => self::item('Refund processed', 'Finance', 'customer', 'Refund {{ document_number }} processed', "Hello {{ customer_name }},\n\nYour refund of {{ amount }} has been processed. Reference: {{ document_number }}.", 'View refund', $finance),
            'finance.vendor_invoice_due' => self::item('Vendor invoice due', 'Finance', 'staff', 'Vendor invoice {{ document_number }} is due', "Vendor invoice {{ document_number }} from {{ supplier_name }} has {{ balance_due }} due on {{ due_date }}.", 'Review payable', $finance),
            'finance.payment_posted' => self::item('Payment posted', 'Finance', 'staff', 'Payment {{ document_number }} posted', "Payment {{ document_number }} for {{ amount }} has been posted by {{ actor_name }}.", 'View payment', $finance),
            'finance.bank_reconciliation_exception' => self::item('Bank reconciliation exception', 'Finance', 'staff', 'Bank reconciliation needs attention', "Bank reconciliation {{ record_reference }} has an unresolved difference or exception. Notes: {{ notes }}", 'Review reconciliation', $finance),
            'finance.period_close_completed' => self::item('Period close completed', 'Finance', 'staff', 'Finance period {{ record_reference }} closed', "Finance period {{ record_reference }} was closed by {{ actor_name }}.", 'View close', $finance),
            'finance.period_close_failed' => self::item('Period close blocked', 'Finance', 'staff', 'Finance period {{ record_reference }} could not close', "Finance period {{ record_reference }} could not be closed. Notes: {{ notes }}", 'Review blockers', $finance),
            'finance.loan_payment_due' => self::item('Loan payment due', 'Finance', 'staff', 'Loan payment due: {{ record_reference }}', "A payment for loan {{ record_reference }} is due on {{ due_date }}. Amount: {{ amount }}.", 'Review loan', $finance),
            'finance.investor_distribution_posted' => self::item('Investor distribution posted', 'Finance', 'staff', 'Investor distribution {{ document_number }} posted', "Investor distribution {{ document_number }} for {{ amount }} has been posted.", 'View distribution', $finance),
            'finance.tax_deadline_due' => self::item('Tax deadline reminder', 'Finance', 'staff', 'Tax deadline due on {{ due_date }}', "A tax filing or payment deadline is due on {{ due_date }}. Reference: {{ record_reference }}.", 'Review tax', $finance),

            // Users, security, imports, and system operations.
            'users.organization_admin_welcome' => self::item('New organization administrator', 'Users and security', 'staff', 'Your {{ organization_name }} administrator account is ready', "Hello {{ recipient_name }},\n\n{{ organization_name }} has been created and your administrator account is ready.\n\nLogin page: {{ login_url }}\nLogin email: {{ login_email }}\nTemporary password: {{ temporary_password }}\n\nFor security, use the button below to replace the temporary password with one only you know. This password-change link expires in 60 minutes.", 'Choose my password', ['recipient_name', 'organization_name', 'login_url', 'login_email', 'temporary_password', 'action_url', 'store_name', 'support_email', 'support_phone']),
            'users.account_created' => self::item('Staff account created', 'Users and security', 'staff', 'Your {{ organization_name }} account is ready', "Hello {{ recipient_name }},\n\nAn account has been created for you at {{ organization_name }} with the role {{ role_name }}.\n\nLogin page: {{ login_url }}\nLogin email: {{ login_email }}\nTemporary password: {{ temporary_password }}\n\nFor security, use the button below to replace the temporary password with one only you know. This password-change link expires in 60 minutes.", 'Choose my password', ['recipient_name', 'organization_name', 'role_name', 'login_url', 'login_email', 'temporary_password', 'action_url', 'store_name', 'support_email', 'support_phone']),
            'users.role_changed' => self::item('Role or access changed', 'Users and security', 'staff', 'Your system access has changed', "Hello {{ recipient_name }},\n\nYour role or permissions were changed by {{ actor_name }}. Sign in to review the modules available to you.", 'Open system', $staff),
            'users.account_deactivated' => self::item('Account deactivated', 'Users and security', 'staff', 'Your system account was deactivated', "Hello {{ recipient_name }},\n\nYour account has been deactivated. Contact an administrator if you believe this was unexpected.", null, $staff),
            'system.import_completed' => self::item('Import completed', 'System operations', 'staff', 'Import {{ record_reference }} completed', "Import {{ record_reference }} has completed. Status: {{ status }}. Notes: {{ notes }}", 'View results', $staff),
            'system.report_ready' => self::item('Report ready', 'System operations', 'staff', 'Report {{ record_reference }} is ready', "Your requested report {{ record_reference }} is ready to view or download.", 'View report', $staff),
            'system.background_job_failed' => self::item('System job failed', 'System operations', 'staff', 'System action {{ record_reference }} failed', "A background action failed and may require attention. Status: {{ status }}. Notes: {{ notes }}", 'Review system', $staff),
        ];
    }

    private static function item(string $name, string $category, string $audience, string $subject, string $body, ?string $actionLabel, array $variables): array
    {
        return compact('name', 'category', 'audience', 'subject', 'body', 'actionLabel', 'variables');
    }
}
