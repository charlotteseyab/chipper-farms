<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class EmailSettingController extends Controller
{
    /**
     * Show the email settings page
     */
    public function index()
    {
        // Restrict to superuser or admin (grouped correctly to prevent null errors)
        abort_unless(auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()), 403);

        $settings = EmailSetting::getSettings();
        
        return view('settings.email', compact('settings'));
    }

    /**
     * Update email server settings
     */
    public function update(Request $request)
    {
        // Restrict to superuser or admin
        abort_unless(auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()), 403);

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

        // If password is empty, don't update it (keep existing password)
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $settings = EmailSetting::getSettings();
        $settings->update($validated);

        return redirect()->route('settings.email')->with('success', 'Email server settings updated successfully.');
    }

    /**
     * Test SMTP server connection
     */
    public function testConnection(Request $request)
    {
        // Restrict to superuser or admin
        abort_unless(auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()), 403);

        $settings = EmailSetting::getSettings();

        try {
            // Only test SMTP connections
            if ($settings->mailer !== 'smtp') {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection testing is only available for SMTP mailer.',
                ]);
            }

            if (empty($settings->host)) {
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP host is not configured.',
                ]);
            }

            // Test SMTP connection using socket connection
            $host = $settings->host;
            $port = $settings->port ?? 587;
            $timeout = 10;

            /**
             * SMTP nuance:
             * - Port 587 typically uses STARTTLS (plain TCP greeting, then upgrade).
             * - Port 465 typically uses implicit TLS (SSL handshake first).
             * If encryption is "ssl" (or port is 465), we must use an SSL socket.
             */
            $useImplicitTls = ($settings->encryption === 'ssl') || ((int) $port === 465);
            $remote = $useImplicitTls ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

            // Attempt to connect to SMTP server (with proper transport)
            $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
            
            if (!$socket) {
                return response()->json([
                    'success' => false,
                    'message' => "Could not connect to SMTP server {$host}:{$port}. Error: {$errstr} ({$errno})",
                ]);
            }

            // Read initial response
            stream_set_timeout($socket, $timeout);
            $response = fgets($socket, 515);
            if ($response === false) {
                fclose($socket);
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP server did not send a greeting (no response). Check host/port/encryption.',
                ]);
            }
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP server did not respond correctly. Response: ' . trim($response),
                ]);
            }

            // Close connection
            fclose($socket);

            return response()->json([
                'success' => true,
                'message' => "Successfully connected to SMTP server.",  //{$host}:{$port}
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a test email
     */
    public function sendTestEmail(Request $request)
    {
        // Restrict to superuser or admin
        abort_unless(auth()->check() && (auth()->user()->isSuperuser() || auth()->user()->isAdmin()), 403);

        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $settings = EmailSetting::getSettings();
        $testEmail = $request->input('test_email');

        try {
            // Configure mail settings dynamically
            Config::set('mail.default', $settings->mailer);
            
            if ($settings->mailer === 'smtp') {
                Config::set('mail.mailers.smtp.host', $settings->host);
                Config::set('mail.mailers.smtp.port', $settings->port ?? 587);
                Config::set('mail.mailers.smtp.username', $settings->username);
                Config::set('mail.mailers.smtp.password', $settings->password);
                Config::set('mail.mailers.smtp.encryption', $settings->encryption);
            }

            Config::set('mail.from.address', $settings->from_address ?? 'noreply@example.com');
            Config::set('mail.from.name', $settings->from_name ?? 'Test Email');

            // Send test email
            Mail::raw('This is a test email from ' . config('app.name') . '. If you received this, your email configuration is working correctly.', function ($message) use ($testEmail, $settings) {
                $message->to($testEmail)
                        ->subject('Test Email from ' . config('app.name'));
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $testEmail . '.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ]);
        }
    }
}