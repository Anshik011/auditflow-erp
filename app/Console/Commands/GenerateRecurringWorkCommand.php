<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\FirmSetting;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringWorkCommand extends Command
{
    protected $signature = 'app:generate-recurring-work';

    protected $description = 'Generate monthly recurring GST/TDS tasks and retainer invoices for active clients.';

    public function handle(BillingService $billingService): int
    {
        $now = Carbon::now();
        $periodLabel = $now->format('M Y');
        $systemUserId = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_PARTNER])->value('id') ?? 1;

        $cgstRate = (float) FirmSetting::getValue('default_cgst_rate', 9);
        $sgstRate = (float) FirmSetting::getValue('default_sgst_rate', 9);

        $tasksCreated = 0;
        $invoicesCreated = 0;

        $clients = Client::query()->where('status', 'active')->get();

        foreach ($clients as $client) {
            if ($client->is_gst_registered) {
                $gstTitle = "Monthly GST Filing (GSTR-1 & 3B) - {$periodLabel}";
                $exists = Task::query()
                    ->where('client_id', $client->id)
                    ->where('title', $gstTitle)
                    ->exists();

                if (! $exists) {
                    Task::create([
                        'title' => $gstTitle,
                        'description' => "Auto-generated monthly GST compliance task for {$client->name}.",
                        'client_id' => $client->id,
                        'assigned_to' => $client->assigned_ca_id,
                        'created_by' => $systemUserId,
                        'category' => 'gst',
                        'priority' => 'high',
                        'status' => 'todo',
                        'due_date' => $now->copy()->day(20),
                    ]);
                    $tasksCreated++;
                }
            }

            if ($client->is_tds_applicable) {
                $tdsTitle = "Monthly TDS Payment & Compliance - {$periodLabel}";
                $exists = Task::query()
                    ->where('client_id', $client->id)
                    ->where('title', $tdsTitle)
                    ->exists();

                if (! $exists) {
                    Task::create([
                        'title' => $tdsTitle,
                        'description' => "Auto-generated monthly TDS compliance task for {$client->name}.",
                        'client_id' => $client->id,
                        'assigned_to' => $client->assigned_ca_id,
                        'created_by' => $systemUserId,
                        'category' => 'tds',
                        'priority' => 'medium',
                        'status' => 'todo',
                        'due_date' => $now->copy()->day(7),
                    ]);
                    $tasksCreated++;
                }
            }

            $shouldBill = $client->is_recurring_retainer
                && (float) $client->retainer_amount > 0
                && ($client->billing_cycle === 'monthly'
                    || ($client->billing_cycle === 'quarterly' && in_array($now->month, [1, 4, 7, 10], true)));

            if ($shouldBill) {
                $noteTag = "Retainer Fee - {$periodLabel}";
                $alreadyBilled = Invoice::query()
                    ->where('client_id', $client->id)
                    ->where('notes', 'like', "%{$noteTag}%")
                    ->exists();

                if (! $alreadyBilled) {
                    $billingService->createInvoice([
                        'client_id' => $client->id,
                        'invoice_date' => $now->toDateString(),
                        'due_date' => $now->copy()->addDays(15)->toDateString(),
                        'tax_mode' => 'cgst_sgst',
                        'cgst_rate' => $cgstRate,
                        'sgst_rate' => $sgstRate,
                        'notes' => "Draft {$noteTag} ({$client->billing_cycle})",
                    ], [[
                        'service_description' => "Professional Retainer & Compliance Services ({$periodLabel})",
                        'sac_code' => '998221',
                        'quantity' => 1,
                        'unit_price' => (float) $client->retainer_amount,
                    ]], $systemUserId);

                    $invoicesCreated++;
                }
            }
        }

        $this->info("Created {$tasksCreated} recurring task(s) and {$invoicesCreated} retainer invoice(s).");

        return self::SUCCESS;
    }
}
