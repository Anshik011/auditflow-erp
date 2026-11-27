<?php

use App\Models\FirmSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'whatsapp_template_compliance' => 'ca_compliance_reminder',
            'whatsapp_template_document' => 'ca_document_reminder',
            'whatsapp_template_invoice' => 'ca_invoice_reminder',
            'whatsapp_template_language' => 'en',
        ];

        foreach ($defaults as $key => $value) {
            if (FirmSetting::query()->where('key', $key)->doesntExist()) {
                FirmSetting::setValue($key, $value, 'whatsapp');
            }
        }
    }

    public function down(): void
    {
        FirmSetting::query()->whereIn('key', [
            'whatsapp_template_compliance',
            'whatsapp_template_document',
            'whatsapp_template_invoice',
            'whatsapp_template_language',
        ])->delete();
    }
};
