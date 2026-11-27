<?php

namespace App\Services\WhatsApp;

use App\Services\FirmConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integration-ready WhatsApp Business Cloud API provider.
 * Reads credentials from FirmSetting first with .env fallback.
 */
class MetaWhatsAppCloudService implements WhatsAppProviderInterface
{
    public function isConfigured(): bool
    {
        $cfg = FirmConfigService::whatsapp();

        return (bool) $cfg['enabled']
            && ! empty($cfg['phone_number_id'])
            && ! empty($cfg['access_token']);
    }

    public function sendTextMessage(string $recipientPhone, string $message): array
    {
        $normalizedPhone = $this->normalizePhone($recipientPhone);
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($normalizedPhone, '+'),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        return $this->dispatchPayload($normalizedPhone, $payload, 'Dispatched via WhatsApp Business Cloud API.');
    }

    public function sendTemplate(string $to, string $templateName, array $bodyParams = [], string $languageCode = 'en'): array
    {
        $normalizedPhone = $this->normalizePhone($to);
        $templatePayload = [
            'name' => $templateName,
            'language' => ['code' => $languageCode ?: 'en'],
        ];

        if (! empty($bodyParams)) {
            $templatePayload['components'] = [[
                'type' => 'body',
                'parameters' => array_values(array_map(
                    fn ($value) => ['type' => 'text', 'text' => (string) $value],
                    $bodyParams
                )),
            ]];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($normalizedPhone, '+'),
            'type' => 'template',
            'template' => $templatePayload,
        ];

        return $this->dispatchPayload($normalizedPhone, $payload, "Dispatched template '{$templateName}' via WhatsApp Business Cloud API.");
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone) ?: '';
    }

    private function dispatchPayload(string $normalizedPhone, array $payload, string $successMessage): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'not_configured',
                'provider_message_id' => null,
                'message' => 'WhatsApp Business API credentials are not configured. Message logged as pending integration setup.',
                'payload' => $payload,
            ];
        }

        if (empty($normalizedPhone)) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'message' => 'Client mobile number is missing or invalid.',
                'payload' => $payload,
            ];
        }

        $cfg = FirmConfigService::whatsapp();
        $endpoint = sprintf(
            '%s/%s/%s/messages',
            rtrim((string) $cfg['base_url'], '/'),
            $cfg['api_version'] ?: 'v20.0',
            $cfg['phone_number_id']
        );

        try {
            $response = Http::withToken((string) $cfg['access_token'])
                ->timeout(10)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                return [
                    'status' => 'sent',
                    'provider_message_id' => $response->json('messages.0.id'),
                    'message' => $successMessage,
                    'payload' => $payload,
                ];
            }

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'message' => 'WhatsApp API error: '.($response->json('error.message') ?? $response->status()),
                'payload' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp dispatch exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'message' => 'WhatsApp dispatch failed: '.$e->getMessage(),
                'payload' => $payload,
            ];
        }
    }
}
