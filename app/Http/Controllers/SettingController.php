<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FirmSetting;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use App\Services\FirmConfigService;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly WhatsAppProviderInterface $whatsApp,
        private readonly BackupService $backupService
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $settings = FirmSetting::allAsMap();
        if (empty($settings['cron_secret_token'])) {
            $token = Str::random(32);
            FirmSetting::setValue('cron_secret_token', $token, 'security');
            $settings['cron_secret_token'] = $token;
        }

        $logs = ActivityLog::with('user')->latest()->paginate(20);
        $whatsAppConfigured = $this->whatsApp->isConfigured();
        $rzp = FirmConfigService::razorpay();
        $razorpayConfigured = (bool) $rzp['enabled'] && ! empty($rzp['key_id']) && ! empty($rzp['key_secret']);
        $backups = $this->backupService->listBackups();

        return view('settings.index', compact(
            'settings',
            'logs',
            'whatsAppConfigured',
            'razorpayConfigured',
            'backups'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $validated = $request->validate([
            'firm_name' => ['required', 'string', 'max:255'],
            'firm_tagline' => ['nullable', 'string', 'max:255'],
            'firm_email' => ['nullable', 'email', 'max:255'],
            'firm_phone' => ['nullable', 'string', 'max:40'],
            'firm_address' => ['nullable', 'string', 'max:1000'],
            'firm_pan' => ['nullable', 'string', 'max:20'],
            'firm_gstin' => ['nullable', 'string', 'max:20'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'default_cgst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'default_sgst_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'default_igst_rate' => ['required', 'numeric', 'min:0', 'max:50'],

            // Meta WhatsApp Cloud API
            'whatsapp_enabled' => ['nullable', 'boolean'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],

            // SMTP
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],

            // Razorpay
            'razorpay_enabled' => ['nullable', 'boolean'],
            'razorpay_key_id' => ['nullable', 'string', 'max:150'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],

            // Security & Cron
            'enforce_2fa' => ['nullable', 'boolean'],
            'cron_secret_token' => ['nullable', 'string', 'max:100'],
        ]);

        $firmKeys = [
            'firm_name', 'firm_tagline', 'firm_email', 'firm_phone', 'firm_address',
            'firm_pan', 'firm_gstin', 'invoice_prefix', 'default_cgst_rate',
            'default_sgst_rate', 'default_igst_rate',
        ];

        foreach ($firmKeys as $key) {
            FirmSetting::setValue($key, $validated[$key] ?? null, 'firm');
        }

        // Integration toggles
        FirmSetting::setValue('whatsapp_enabled', $request->boolean('whatsapp_enabled') ? '1' : '0', 'whatsapp');
        FirmSetting::setValue('whatsapp_phone_number_id', $validated['whatsapp_phone_number_id'] ?? null, 'whatsapp');

        FirmSetting::setValue('mail_host', $validated['mail_host'] ?? null, 'mail');
        FirmSetting::setValue('mail_port', $validated['mail_port'] ?? '587', 'mail');
        FirmSetting::setValue('mail_username', $validated['mail_username'] ?? null, 'mail');
        FirmSetting::setValue('mail_encryption', $validated['mail_encryption'] ?? 'tls', 'mail');
        FirmSetting::setValue('mail_from_address', $validated['mail_from_address'] ?? null, 'mail');
        FirmSetting::setValue('mail_from_name', $validated['mail_from_name'] ?? null, 'mail');

        FirmSetting::setValue('razorpay_enabled', $request->boolean('razorpay_enabled') ? '1' : '0', 'razorpay');
        FirmSetting::setValue('razorpay_key_id', $validated['razorpay_key_id'] ?? null, 'razorpay');

        FirmSetting::setValue('enforce_2fa', $request->boolean('enforce_2fa') ? '1' : '0', 'security');
        if (! empty($validated['cron_secret_token'])) {
            FirmSetting::setValue('cron_secret_token', $validated['cron_secret_token'], 'security');
        }

        // Only overwrite encrypted secrets when a new non-empty value is provided
        $secretMap = [
            'whatsapp_access_token' => 'whatsapp',
            'mail_password' => 'mail',
            'razorpay_key_secret' => 'razorpay',
            'razorpay_webhook_secret' => 'razorpay',
        ];

        foreach ($secretMap as $secretKey => $group) {
            if (! empty($validated[$secretKey])) {
                FirmSetting::setValue($secretKey, $validated[$secretKey], $group);
            }
        }

        $this->logger->log('settings', 'updated', 'Updated CA firm profile, integrations & security settings');

        return back()->with('success', 'Firm & integration settings saved.');
    }

    public function testWhatsApp(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $validated = $request->validate([
            'test_phone' => ['required', 'string', 'max:30'],
        ]);

        $result = $this->whatsApp->sendTextMessage(
            $validated['test_phone'],
            'Test message from Uptoware Audit: Your Meta WhatsApp Cloud API integration is active.'
        );

        if ($result['status'] === 'sent') {
            return back()->with('success', 'WhatsApp test message sent successfully.');
        }

        return back()->with('warning', 'WhatsApp test: '.$result['message']);
    }

    public function testEmail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            FirmConfigService::applyMailConfig();
            Mail::raw(
                'This is a test email from Uptoware Audit confirming your SMTP configuration is working.',
                function ($message) use ($validated) {
                    $message->to($validated['test_email'])
                        ->subject('Uptoware Audit — SMTP Test Email');
                }
            );

            return back()->with('success', "Test email dispatched to {$validated['test_email']}.");
        } catch (\Throwable $e) {
            return back()->with('warning', 'SMTP test failed: '.$e->getMessage());
        }
    }

    public function testRazorpay(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $cfg = FirmConfigService::razorpay();
        if (empty($cfg['key_id']) || empty($cfg['key_secret'])) {
            return back()->with('warning', 'Razorpay Key ID and Key Secret are required before testing.');
        }

        try {
            $response = Http::withBasicAuth((string) $cfg['key_id'], (string) $cfg['key_secret'])
                ->timeout(10)
                ->get('https://api.razorpay.com/v1/payments', ['count' => 1]);

            if ($response->successful()) {
                return back()->with('success', 'Razorpay API connection verified successfully.');
            }

            return back()->with('warning', 'Razorpay API returned HTTP '.$response->status().': '.($response->json('error.description') ?? 'Authentication failed.'));
        } catch (\Throwable $e) {
            return back()->with('warning', 'Razorpay connection error: '.$e->getMessage());
        }
    }

    public function runCronNow(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        Artisan::call('app:generate-recurring-deadlines');
        Artisan::call('app:send-compliance-reminders');
        Artisan::call('schedule:run');

        $this->logger->log('cron', 'manual_run', 'Manually triggered scheduled jobs from Settings.');

        return back()->with('success', 'Scheduled jobs (deadlines, reminders & queue) executed.');
    }

    public function downloadBackup(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->isAdminOrPartner(), 403);

        $zipPath = $this->backupService->createFullBackup();
        $this->logger->log('backup', 'downloaded', 'Generated and downloaded full database + documents backup.');

        return response()->download($zipPath, basename($zipPath));
    }
}
