<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Services\Email\EmailTemplateService;
use App\Support\EmailTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailSettingController extends Controller
{
    public function index()
    {
        abort_unless(
            auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()),
            403
        );

        $user = auth()->user();
        $canManageEmailServer = $user->isSuperuser();
        $settings = $canManageEmailServer ? EmailSetting::getSettings() : null;
        $organizationIds = $user->isSuperuser()
            ? Organization::active()->pluck('id')
            : collect([(int) $user->organization_id]);
        foreach ($organizationIds as $organizationId) {
            app(EmailTemplateService::class)->syncDefaults((int) $organizationId);
        }
        $templates = EmailTemplate::query()
            ->with('organization:id,name')
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');
        $templateDefinitions = EmailTemplateCatalog::definitions();

        return view('settings.email', compact('settings', 'templates', 'templateDefinitions', 'canManageEmailServer'));
    }

    public function update(Request $request)
    {
        $this->serverSettingsOnly();

        $validated = $request->validate([
            'mailer' => ['required', 'string', 'in:smtp,sendmail,log,array'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (($validated['mailer'] ?? null) === 'smtp') {
            $request->validate([
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'min:1', 'max:65535'],
                'username' => ['required', 'string', 'max:255'],
                'from_address' => ['required', 'email', 'max:255'],
            ]);
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $settings = EmailSetting::getSettings();
        $settings->update($validated);

        return redirect()
            ->route('settings.email')
            ->with('success', 'Email server settings updated successfully.');
    }

    public function testConnection(Request $request)
    {
        $this->serverSettingsOnly();

        $validated = $request->validate([
            'mailer' => ['required', 'string', 'in:smtp,sendmail,log,array'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if (($validated['mailer'] ?? 'smtp') !== 'smtp') {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection testing is only available for SMTP mailer.',
                ], 422);
            }

            if (empty($validated['username'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP username is required before testing the connection.',
                ], 422);
            }

            $host = $validated['host'] ?? null;
            $port = (int) ($validated['port'] ?? 587);
            $encryption = $validated['encryption'] ?? 'tls';

            if (empty($host)) {
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP host is not configured.',
                ], 422);
            }

            $timeout = 10;
            $useImplicitTls = ($encryption === 'ssl') || ($port === 465);
            $remote = $useImplicitTls ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

            $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

            if (! $socket) {
                return response()->json([
                    'success' => false,
                    'message' => "Could not connect to {$host}:{$port}. {$errstr}",
                ], 422);
            }

            stream_set_timeout($socket, $timeout);
            $response = fgets($socket, 515);

            if ($response === false || substr($response, 0, 3) !== '220') {
                fclose($socket);

                return response()->json([
                    'success' => false,
                    'message' => 'SMTP server connected but did not return a valid greeting.',
                ], 422);
            }

            fclose($socket);

            return response()->json([
                'success' => true,
                'message' => "Successfully connected to {$host}:{$port}.",
            ]);
        } catch (\Throwable $e) {
            Log::error('Email connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => app()->environment('local')
                    ? ('Connection failed: ' . $e->getMessage())
                    : 'Connection failed. Please review the settings and check the application logs.',
            ], 422);
        }
    }

    public function sendTestEmail(Request $request)
    {
        $this->serverSettingsOnly();

        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $settings = EmailSetting::getSettings();
        $testEmail = (string) $request->input('test_email');

        try {
            Config::set('mail.default', $settings->mailer);

            if ($settings->mailer === 'smtp') {
                if (blank($settings->host) || blank($settings->username) || blank($settings->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SMTP host, username, and password must all be configured before sending a test email.',
                    ], 422);
                }

                $port = (int) ($settings->port ?: 587);
                $scheme = ($settings->encryption === 'ssl' || $port === 465) ? 'smtps' : 'smtp';

                Config::set('mail.mailers.smtp.host', $settings->host);
                Config::set('mail.mailers.smtp.port', $port);
                Config::set('mail.mailers.smtp.username', $settings->username);
                Config::set('mail.mailers.smtp.password', $settings->password);
                Config::set('mail.mailers.smtp.scheme', $scheme);
                Config::set('mail.mailers.smtp.timeout', 15);
                Config::set(
                    'mail.mailers.smtp.local_domain',
                    parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'
                );
            }

            Config::set('mail.from.address', $settings->from_address ?: 'noreply@example.com');
            Config::set('mail.from.name', $settings->from_name ?: config('app.name', 'Chipper Farms'));

            app('mail.manager')->forgetMailers();

            Mail::mailer($settings->mailer)->raw(
                'This is a test email from ' . config('app.name') . '. If you received this, your email configuration is working correctly.',
                function ($message) use ($testEmail) {
                    $message->to($testEmail)
                        ->subject('Test Email from ' . config('app.name'));
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail . '.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Email settings test send failed', [
                'mailer' => $settings->mailer,
                'host' => $settings->host,
                'port' => $settings->port,
                'username' => $settings->username,
                'from_address' => $settings->from_address,
                'error' => $e->getMessage(),
            ]);

            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'badcredentials') || str_contains(strtolower($message), 'authentication required')) {
                $message = 'SMTP authentication failed. Verify the username and password or app password configured for the selected mail server.';
            }

            return response()->json([
                'success' => false,
                'message' => app()->environment('local')
                    ? ('Failed to send test email: ' . $message)
                    : 'Failed to send the test email. Please verify the email configuration and check the application logs for details.',
            ], 422);
        }
    }

    public function updateTemplate(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        abort_unless(
            auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()),
            403
        );
        abort_unless(
            auth()->user()->isSuperuser()
            || (int) $emailTemplate->organization_id === (int) auth()->user()->organization_id,
            404
        );

        $definition = EmailTemplateCatalog::definitions()[$emailTemplate->key] ?? null;
        abort_unless($definition !== null, 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'action_label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allowed = array_flip($definition['variables']);
        foreach ([$validated['subject'], $validated['body']] as $content) {
            preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $content, $matches);
            foreach ($matches[1] as $variable) {
                if (! isset($allowed[$variable])) {
                    return back()->withErrors([
                        "template_{$emailTemplate->id}" => "Unknown variable {{$variable}} in {$emailTemplate->name}.",
                    ])->withInput();
                }
            }
        }

        $emailTemplate->update([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'action_label' => $validated['action_label'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('settings.email', ['tab' => 'templates'])
            ->with('success', "{$emailTemplate->name} template updated.");
    }

    private function serverSettingsOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isSuperuser(), 403);
    }
}
