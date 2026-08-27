<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oldBody = "Hello {{ recipient_name }},\n\nA staff account has been created for you. Use the button below to sign in and set up your access.";
        $newBody = "Hello {{ recipient_name }},\n\nAn account has been created for you at {{ organization_name }} with the role {{ role_name }}.\n\nLogin page: {{ login_url }}\nLogin email: {{ login_email }}\nTemporary password: {{ temporary_password }}\n\nFor security, use the button below to replace the temporary password with one only you know. This password-change link expires in 60 minutes.";

        // Update only the shipped default. Farm-specific edits and the active
        // state are deliberately left untouched.
        DB::table('email_templates')
            ->where('key', 'users.account_created')
            ->where('body', $oldBody)
            ->update([
                'subject' => 'Your {{ organization_name }} account is ready',
                'body' => $newBody,
                'action_label' => 'Choose my password',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Email content may have been edited after deployment. Reverting it
        // automatically would destroy that administrator-owned content.
    }
};
