<?php

namespace App\Console\Commands;

use App\Models\ComplianceDeadline;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CommunicationService;
use App\Services\FirmConfigService;
use App\Services\WhatsApp\WhatsAppProviderInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendComplianceRemindersCommand extends Command
{
    protected $signature = 'app:send-compliance-reminders {--limit=25 : Maximum reminders to dispatch per run}';

    protected $description = 'Send batched Email & WhatsApp reminders for deadlines, pending documents, and overdue invoices.';

    public function handle(CommunicationService $comm, WhatsAppProviderInterface $whatsApp): int
    {
        $limit = max(1, min(25, (int) $this->option('limit')));
        $sentCount = 0;
        $today = Carbon::today();
        $systemUserId = User::whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_PARTNER])->value('id') ?? 1;
        $waEnabled = $whatsApp->isConfigured();
        $waTemplates = FirmConfigService::whatsapp()['templates'];

        // 1. Upcoming & Overdue Compliance Deadlines
        $deadlines = ComplianceDeadline::with(['client', 'rule'])
            ->whereNotNull('client_id')
            ->where('status', '!=', 'completed')
            ->where(function ($q) {
                $q->whereNull('last_reminded_at')
                    ->orWhereDate('last_reminded_at', '<', today());
            })
            ->orderBy('due_date')
            ->get();

        foreach ($deadlines as $deadline) {
            if ($sentCount >= $limit) {
                break;
            }
            if (! $deadline->client) {
                continue;
            }

            $daysBefore = $deadline->rule?->reminder_days_before ?? 5;
            $triggerDate = $deadline->reminder_date ?? $deadline->due_date->copy()->subDays($daysBefore);
            if ($triggerDate->gt($today)) {
                continue;
            }

            $statusWord = $deadline->due_date->lt($today) ? 'OVERDUE' : 'Upcoming';
            $subject = "{$statusWord} Compliance Reminder: {$deadline->title}";
            $message = "Dear {$deadline->client->name}, this is a reminder for '{$deadline->title}' due on {$deadline->due_date->format('d M Y')}. Please share any pending inputs with your CA team.";

            $dispatched = false;
            if (! empty($deadline->client->email)) {
                $comm->send($deadline->client, ['channel' => 'email', 'subject' => $subject, 'message' => $message], $systemUserId);
                $sentCount++;
                $dispatched = true;
            }
            if ($waEnabled && ! empty($deadline->client->mobile) && $sentCount < $limit) {
                $comm->send($deadline->client, [
                    'channel' => 'whatsapp',
                    'subject' => $subject,
                    'message' => $message,
                    'template_name' => $waTemplates['compliance'],
                    'template_params' => [
                        $deadline->client->name,
                        $deadline->title,
                        $deadline->due_date->format('d M Y'),
                    ],
                    'template_language' => $waTemplates['language'],
                ], $systemUserId);
                $sentCount++;
                $dispatched = true;
            }

            if ($dispatched) {
                $deadline->update(['last_reminded_at' => now()]);
            }
        }

        // 2. Pending / Rejected Document Requests
        if ($sentCount < $limit) {
            $documents = Document::with('client')
                ->whereIn('status', ['requested', 'rejected'])
                ->where(function ($q) {
                    $q->whereNull('last_reminded_at')
                        ->orWhereDate('last_reminded_at', '<', today()->subDays(2));
                })
                ->orderBy('due_date')
                ->limit($limit - $sentCount)
                ->get();

            foreach ($documents as $doc) {
                if ($sentCount >= $limit) {
                    break;
                }
                if (! $doc->client) {
                    continue;
                }

                $subject = "Document Required: {$doc->title}";
                $dueNote = $doc->due_date ? " by {$doc->due_date->format('d M Y')}" : '';
                $message = "Dear {$doc->client->name}, please upload '{$doc->title}' ({$doc->statusLabel()}){$dueNote} on the Client Portal.";

                $dispatched = false;
                if (! empty($doc->client->email)) {
                    $comm->send($doc->client, ['channel' => 'email', 'subject' => $subject, 'message' => $message], $systemUserId);
                    $sentCount++;
                    $dispatched = true;
                }
                if ($waEnabled && ! empty($doc->client->mobile) && $sentCount < $limit) {
                    $comm->send($doc->client, [
                        'channel' => 'whatsapp',
                        'subject' => $subject,
                        'message' => $message,
                        'template_name' => $waTemplates['document'],
                        'template_params' => [
                            $doc->client->name,
                            $doc->title,
                            $doc->due_date?->format('d M Y') ?? 'As soon as possible',
                        ],
                        'template_language' => $waTemplates['language'],
                    ], $systemUserId);
                    $sentCount++;
                    $dispatched = true;
                }

                if ($dispatched) {
                    $doc->update(['last_reminded_at' => now()]);
                }
            }
        }

        // 3. Overdue Invoices
        if ($sentCount < $limit) {
            $invoices = Invoice::with('client')
                ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                ->whereDate('due_date', '<', $today)
                ->where(function ($q) {
                    $q->whereNull('last_reminded_at')
                        ->orWhereDate('last_reminded_at', '<', today()->subDays(3));
                })
                ->orderBy('due_date')
                ->limit($limit - $sentCount)
                ->get();

            foreach ($invoices as $invoice) {
                if ($sentCount >= $limit) {
                    break;
                }
                if (! $invoice->client) {
                    continue;
                }

                $payLink = $invoice->razorpay_payment_link_url
                    ? " Pay online: {$invoice->razorpay_payment_link_url}"
                    : '';
                $subject = "Overdue Invoice Reminder: {$invoice->invoice_number}";
                $message = "Dear {$invoice->client->name}, invoice {$invoice->invoice_number} for INR ".number_format((float) $invoice->balance_amount, 2)." was due on {$invoice->due_date->format('d M Y')}.{$payLink}";

                $dispatched = false;
                if (! empty($invoice->client->email)) {
                    $comm->send($invoice->client, ['channel' => 'email', 'subject' => $subject, 'message' => $message], $systemUserId);
                    $sentCount++;
                    $dispatched = true;
                }
                if ($waEnabled && ! empty($invoice->client->mobile) && $sentCount < $limit) {
                    $comm->send($invoice->client, [
                        'channel' => 'whatsapp',
                        'subject' => $subject,
                        'message' => $message,
                        'template_name' => $waTemplates['invoice'],
                        'template_params' => [
                            $invoice->client->name,
                            $invoice->invoice_number,
                            number_format((float) $invoice->balance_amount, 2),
                            $invoice->razorpay_payment_link_url ?: $invoice->due_date->format('d M Y'),
                        ],
                        'template_language' => $waTemplates['language'],
                    ], $systemUserId);
                    $sentCount++;
                    $dispatched = true;
                }

                if ($dispatched) {
                    $invoice->update(['status' => 'overdue', 'last_reminded_at' => now()]);
                }
            }
        }

        $this->info("Dispatched {$sentCount} compliance/document/invoice reminder(s) (batch cap: {$limit}).");

        return self::SUCCESS;
    }
}
