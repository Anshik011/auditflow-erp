<?php

namespace App\Services;

use App\Models\FirmSetting;
use Illuminate\Support\Facades\Mail;

/**
 * Resolves runtime integration settings from FirmSetting (DB) with .env/config fallback.
 */
class FirmConfigService
{
    public static function whatsapp(): array
    {
        $dbEnabled = FirmSetting::getValue('whatsapp_enabled');
        $dbPhoneId = FirmSetting::getValue('whatsapp_phone_number_id');
        $dbToken = FirmSetting::getValue('whatsapp_access_token');

        return [
            'enabled' => $dbEnabled !== null
                ? filter_var($dbEnabled, FILTER_VALIDATE_BOOLEAN)
                : (bool) config('services.whatsapp.enabled'),
            'phone_number_id' => ! empty($dbPhoneId) ? $dbPhoneId : config('services.whatsapp.phone_number_id'),
            'access_token' => ! empty($dbToken) ? $dbToken : config('services.whatsapp.access_token'),
            'api_version' => config('services.whatsapp.api_version', 'v20.0'),
            'base_url' => config('services.whatsapp.base_url', 'https://graph.facebook.com'),
            'templates' => [
                'compliance' => FirmSetting::getValue('whatsapp_template_compliance') ?: 'ca_compliance_reminder',
                'document' => FirmSetting::getValue('whatsapp_template_document') ?: 'ca_document_reminder',
                'invoice' => FirmSetting::getValue('whatsapp_template_invoice') ?: 'ca_invoice_reminder',
                'language' => FirmSetting::getValue('whatsapp_template_language') ?: 'en',
            ],
        ];
    }

    public static function razorpay(): array
    {
        $dbEnabled = FirmSetting::getValue('razorpay_enabled');
        $dbKeyId = FirmSetting::getValue('razorpay_key_id');
        $dbKeySecret = FirmSetting::getValue('razorpay_key_secret');
        $dbWebhookSecret = FirmSetting::getValue('razorpay_webhook_secret');

        return [
            'enabled' => $dbEnabled !== null
                ? filter_var($dbEnabled, FILTER_VALIDATE_BOOLEAN)
                : (bool) config('services.razorpay.enabled', false),
            'key_id' => ! empty($dbKeyId) ? $dbKeyId : config('services.razorpay.key_id'),
            'key_secret' => ! empty($dbKeySecret) ? $dbKeySecret : config('services.razorpay.key_secret'),
            'webhook_secret' => ! empty($dbWebhookSecret) ? $dbWebhookSecret : config('services.razorpay.webhook_secret'),
        ];
    }

    public static function applyMailConfig(): void
    {
        // In automated tests, preserve array mailer unless explicitly testing SMTP
        if (app()->runningUnitTests() && config('mail.default') === 'array') {
            return;
        }

        $host = FirmSetting::getValue('mail_host');
        if (empty($host)) {
            return;
        }

        $port = (int) (FirmSetting::getValue('mail_port') ?: 587);
        $encryption = FirmSetting::getValue('mail_encryption') ?: 'tls';
        $username = FirmSetting::getValue('mail_username');
        $password = FirmSetting::getValue('mail_password');
        $fromAddress = FirmSetting::getValue('mail_from_address') ?: FirmSetting::getValue('firm_email', config('mail.from.address'));
        $fromName = FirmSetting::getValue('mail_from_name') ?: FirmSetting::getValue('firm_name', config('mail.from.name'));

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        Mail::purge('smtp');
    }
}
