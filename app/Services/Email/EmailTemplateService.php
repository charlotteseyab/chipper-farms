<?php

namespace App\Services\Email;

use App\Mail\TemplatedEmail;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Support\EmailTemplateCatalog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplateService
{
    public function syncDefaults(int $organizationId): void
    {
        foreach (EmailTemplateCatalog::definitions() as $key => $definition) {
            EmailTemplate::query()->firstOrCreate(
                ['organization_id' => $organizationId, 'key' => $key],
                [
                    'name' => $definition['name'],
                    'category' => $definition['category'],
                    'audience' => $definition['audience'],
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                    'action_label' => $definition['actionLabel'],
                    'is_active' => true,
                ],
            );
        }
    }

    public function send(int $organizationId, string $key, ?string $recipient, array $variables = []): bool
    {
        $recipient = trim((string) $recipient);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        try {
            $template = EmailTemplate::query()
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->first();

            // Existing installations lazily receive newly introduced module
            // templates. Normal sends stay one lookup rather than running the
            // entire catalogue through firstOrCreate on every event.
            if (! $template) {
                $this->syncDefaults($organizationId);
                $template = EmailTemplate::query()
                    ->where('organization_id', $organizationId)
                    ->where('key', $key)
                    ->first();
            }

            if (! $template || ! $template->is_active) {
                return false;
            }

            $settings = $this->configureTransport();
            $subject = $this->render($template->subject, $variables);
            $body = $this->render($template->body, $variables);
            $actionUrl = trim((string) ($variables['action_url'] ?? ''));

            Mail::mailer($settings->mailer)
                ->to($recipient)
                ->send(new TemplatedEmail(
                    emailSubject: $subject,
                    body: $body,
                    actionUrl: filter_var($actionUrl, FILTER_VALIDATE_URL) ? $actionUrl : null,
                    actionLabel: $template->action_label,
                    storeName: $variables['store_name'] ?? $settings->from_name ?? config('app.name'),
                    supportEmail: $variables['support_email'] ?? null,
                    supportPhone: $variables['support_phone'] ?? null,
                    secondaryActionUrl: filter_var($variables['create_account_url'] ?? null, FILTER_VALIDATE_URL) ? $variables['create_account_url'] : null,
                    secondaryActionLabel: filled($variables['create_account_url'] ?? null) ? 'Create buyer account' : null,
                ));

            return true;
        } catch (\Throwable $e) {
            // Email delivery must never roll back an order or farm operation.
            Log::error('Templated email delivery failed', [
                'organization_id' => $organizationId,
                'template_key' => $key,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function render(string $value, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $match) use ($variables): string {
            $replacement = $variables[$match[1]] ?? $match[0];

            return is_scalar($replacement) ? (string) $replacement : $match[0];
        }, $value) ?? $value;
    }

    protected function configureTransport(): EmailSetting
    {
        $settings = EmailSetting::getSettings();

        Config::set('mail.default', $settings->mailer);
        Config::set('mail.from.address', $settings->from_address ?: 'noreply@example.com');
        Config::set('mail.from.name', $settings->from_name ?: config('app.name', 'Chipper Farms'));

        if ($settings->mailer === 'smtp') {
            $port = (int) ($settings->port ?: 587);
            Config::set('mail.mailers.smtp.host', $settings->host);
            Config::set('mail.mailers.smtp.port', $port);
            Config::set('mail.mailers.smtp.username', $settings->username);
            Config::set('mail.mailers.smtp.password', $settings->password);
            Config::set('mail.mailers.smtp.scheme', ($settings->encryption === 'ssl' || $port === 465) ? 'smtps' : 'smtp');
        }

        app('mail.manager')->forgetMailers();

        return $settings;
    }
}
