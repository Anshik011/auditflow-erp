<?php

use App\Models\FirmSetting;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('audit:rebrand', function () {
    $this->info('Rebranding to Uptoware Audit...');

    // 1. Update Firm Settings
    FirmSetting::updateOrCreate(
        ['key' => 'firm_name'],
        ['value' => 'Uptoware Audit & Advisory', 'group' => 'firm']
    );
    FirmSetting::updateOrCreate(
        ['key' => 'firm_tagline'],
        ['value' => 'Chartered Accountants, Tax & Practice Management', 'group' => 'firm']
    );
    FirmSetting::updateOrCreate(
        ['key' => 'firm_email'],
        ['value' => 'office@uptoware.com', 'group' => 'firm']
    );

    // 2. Update Users
    $map = [
        'admin@auditflow.in' => 'admin@uptoware.com',
        'partner@auditflow.in' => 'partner@uptoware.com',
        'manager@auditflow.in' => 'manager@uptoware.com',
        'employee@auditflow.in' => 'employee@uptoware.com',
        'sneha@auditflow.in' => 'sneha@uptoware.com',
    ];
    foreach ($map as $old => $new) {
        $updated = User::where('email', $old)->update(['email' => $new]);
        if ($updated) {
            $this->line("Updated user email: {$old} -> {$new}");
        }
    }

    $this->info('Uptoware Audit rebrand complete!');
})->purpose('Rebrand system to Uptoware Audit');
