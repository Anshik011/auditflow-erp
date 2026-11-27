<?php

namespace App\Http\Controllers;

use App\Models\FirmSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Shared-hosting web cron endpoint for cPanel / Hostinger URL cron jobs.
 */
class CronController extends Controller
{
    public function run(Request $request, ActivityLogger $logger): JsonResponse
    {
        $configuredToken = (string) (FirmSetting::getValue('cron_secret_token') ?: config('app.cron_token', ''));
        $providedToken = (string) $request->query('token', '');

        if ($configuredToken === '' || $providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['ok' => false, 'message' => 'Invalid or missing cron token.'], 403);
        }

        Artisan::call('schedule:run');
        $output = trim(Artisan::output());

        $logger->log('cron', 'executed', 'Web cron endpoint triggered scheduled jobs.');

        return response()->json([
            'ok' => true,
            'executed_at' => now()->toIso8601String(),
            'output' => $output,
        ]);
    }
}
