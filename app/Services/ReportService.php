<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ComplianceDeadline;
use App\Models\Document;
use App\Models\GstReturn;
use App\Models\Invoice;
use App\Models\ItrFiling;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\TdsReturn;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds structured practice reports and UTF-8 BOM CSV exports compatible with Microsoft Excel.
 */
class ReportService
{
    public const TYPES = [
        'client_compliance' => 'Client-wise Compliance Summary',
        'gst_status' => 'GST Filing Status Report',
        'itr_status' => 'ITR Filing Status Report',
        'tds_status' => 'TDS Return Status Report',
        'overdue_compliance' => 'Overdue Compliance Report',
        'employee_productivity' => 'Employee Productivity Report',
        'pending_tasks' => 'Pending Tasks Report',
        'outstanding_invoices' => 'Outstanding Invoices Report',
        'monthly_revenue' => 'Monthly Revenue Report',
        'document_collection' => 'Document Collection Status Report',
        'tally_export' => 'Tally-Ready Invoices & Receipts Export',
        'client_profitability' => 'Client Profitability Report',
    ];

    public function build(string $type, ?string $fromDate = null, ?string $toDate = null): array
    {
        return match ($type) {
            'gst_status' => $this->gstReport($fromDate, $toDate),
            'itr_status' => $this->itrReport($fromDate, $toDate),
            'tds_status' => $this->tdsReport($fromDate, $toDate),
            'overdue_compliance' => $this->overdueComplianceReport($fromDate, $toDate),
            'employee_productivity' => $this->employeeProductivityReport($fromDate, $toDate),
            'pending_tasks' => $this->pendingTasksReport($fromDate, $toDate),
            'outstanding_invoices' => $this->outstandingInvoicesReport($fromDate, $toDate),
            'monthly_revenue' => $this->monthlyRevenueReport($fromDate, $toDate),
            'document_collection' => $this->documentCollectionReport($fromDate, $toDate),
            'tally_export' => $this->tallyExportReport($fromDate, $toDate),
            'client_profitability' => $this->clientProfitabilityReport($fromDate, $toDate),
            default => $this->clientComplianceReport(),
        };
    }

    public function streamCsv(string $type, ?string $fromDate = null, ?string $toDate = null): StreamedResponse
    {
        $report = $this->build($type, $fromDate, $toDate);
        $filename = $type.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Microsoft Excel opens currency & characters cleanly
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $report['headers']);
            foreach ($report['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function clientComplianceReport(): array
    {
        $clients = Client::with(['assignedCa'])
            ->withCount([
                'gstReturns as gst_pending_count' => fn ($q) => $q->where('status', '!=', 'filed'),
                'itrFilings as itr_pending_count' => fn ($q) => $q->whereNotIn('filing_status', ['filed', 'verified']),
                'tdsReturns as tds_pending_count' => fn ($q) => $q->where('status', '!=', 'filed'),
                'tasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', 'completed'),
            ])->orderBy('name')->get();

        return [
            'headers' => ['Client Code', 'Client Name', 'Category', 'Assigned CA', 'Pending GST', 'Pending ITR', 'Pending TDS', 'Open Tasks', 'Outstanding Fees (INR)'],
            'rows' => $clients->map(fn ($c) => [
                $c->client_code,
                $c->name,
                ucfirst($c->category),
                $c->assignedCa?->name ?? 'Unassigned',
                $c->gst_pending_count,
                $c->itr_pending_count,
                $c->tds_pending_count,
                $c->open_tasks_count,
                number_format($c->outstandingBalance(), 2, '.', ''),
            ])->toArray(),
        ];
    }

    private function gstReport(?string $fromDate, ?string $toDate): array
    {
        $q = GstReturn::with(['client', 'assignee']);
        if ($fromDate) {
            $q->whereDate('due_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('due_date', '<=', $toDate);
        }
        $rows = $q->orderBy('due_date')->get();

        return [
            'headers' => ['Client', 'GSTIN', 'Return Type', 'Tax Period', 'FY', 'Due Date', 'Status', 'ARN', 'Filing Date', 'Assigned To'],
            'rows' => $rows->map(fn ($r) => [
                $r->client?->name ?? '—',
                $r->client?->gstin ?? '—',
                $r->return_type_code,
                $r->tax_period,
                $r->financial_year,
                optional($r->due_date)->format('Y-m-d'),
                $r->statusLabel(),
                $r->arn ?? '—',
                optional($r->filing_date)->format('Y-m-d') ?? '—',
                $r->assignee?->name ?? 'Unassigned',
            ])->toArray(),
        ];
    }

    private function itrReport(?string $fromDate, ?string $toDate): array
    {
        $q = ItrFiling::with(['client', 'assignee']);
        if ($fromDate) {
            $q->whereDate('due_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('due_date', '<=', $toDate);
        }
        $rows = $q->orderByDesc('assessment_year')->get();

        return [
            'headers' => ['Client', 'PAN', 'AY', 'Return Type', 'Category', 'Docs Status', 'Filing Status', 'Due Date', 'Ack No', 'Refund Status', 'Assigned To'],
            'rows' => $rows->map(fn ($r) => [
                $r->client?->name ?? '—',
                $r->client?->pan ?? '—',
                $r->assessment_year,
                $r->return_type,
                $r->itr_category,
                ucfirst($r->document_status),
                $r->filingStatusLabel(),
                optional($r->due_date)->format('Y-m-d') ?? '—',
                $r->acknowledgement_number ?? '—',
                $r->refundStatusLabel(),
                $r->assignee?->name ?? 'Unassigned',
            ])->toArray(),
        ];
    }

    private function tdsReport(?string $fromDate, ?string $toDate): array
    {
        $q = TdsReturn::with(['client', 'assignee']);
        if ($fromDate) {
            $q->whereDate('due_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('due_date', '<=', $toDate);
        }
        $rows = $q->orderBy('due_date')->get();

        return [
            'headers' => ['Client', 'TAN', 'FY', 'Quarter', 'Form', 'Deduction (INR)', 'Tax Deposited (INR)', 'Challan No', 'Due Date', 'Status', 'Assigned To'],
            'rows' => $rows->map(fn ($r) => [
                $r->client?->name ?? '—',
                $r->client?->tan ?? '—',
                $r->financial_year,
                $r->quarter,
                $r->form_type,
                number_format((float) $r->deduction_amount, 2, '.', ''),
                number_format((float) $r->tax_deposited, 2, '.', ''),
                $r->challan_number ?? '—',
                optional($r->due_date)->format('Y-m-d') ?? '—',
                $r->statusLabel(),
                $r->assignee?->name ?? 'Unassigned',
            ])->toArray(),
        ];
    }

    private function overdueComplianceReport(?string $fromDate, ?string $toDate): array
    {
        $q = ComplianceDeadline::with(['client', 'assignee'])
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', '<', today());
        if ($fromDate) {
            $q->whereDate('due_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('due_date', '<=', $toDate);
        }
        $rows = $q->orderBy('due_date')->get();

        return [
            'headers' => ['Deadline Title', 'Type', 'Client', 'Due Date', 'Days Overdue', 'Priority', 'Assigned To'],
            'rows' => $rows->map(fn ($d) => [
                $d->title,
                $d->typeLabel(),
                $d->client?->name ?? 'Firm-wide',
                optional($d->due_date)->format('Y-m-d'),
                $d->due_date ? $d->due_date->diffInDays(today()) : 0,
                ucfirst($d->priority),
                $d->assignee?->name ?? 'Unassigned',
            ])->toArray(),
        ];
    }

    private function employeeProductivityReport(?string $fromDate, ?string $toDate): array
    {
        $staff = User::staff()->withCount([
            'assignedTasks as total_tasks',
            'assignedTasks as completed_tasks' => fn ($q) => $q->where('status', 'completed'),
            'assignedTasks as overdue_tasks' => fn ($q) => $q->where('status', '!=', 'completed')->whereDate('due_date', '<', today()),
            'gstReturns as gst_filed' => fn ($q) => $q->where('status', 'filed'),
            'itrFilings as itr_filed' => fn ($q) => $q->whereIn('filing_status', ['filed', 'verified']),
        ])->orderBy('name')->get();

        return [
            'headers' => ['Employee Code', 'Name', 'Role', 'Designation', 'Total Tasks', 'Completed Tasks', 'Completion %', 'Overdue Tasks', 'GST Filed', 'ITR Filed'],
            'rows' => $staff->map(function ($u) {
                $rate = $u->total_tasks > 0 ? round(($u->completed_tasks / $u->total_tasks) * 100, 1) : 0;

                return [
                    $u->employee_code ?? '—',
                    $u->name,
                    $u->roleLabel(),
                    $u->designation ?? '—',
                    $u->total_tasks,
                    $u->completed_tasks,
                    $rate.'%',
                    $u->overdue_tasks,
                    $u->gst_filed,
                    $u->itr_filed,
                ];
            })->toArray(),
        ];
    }

    private function pendingTasksReport(?string $fromDate, ?string $toDate): array
    {
        $q = Task::with(['client', 'assignee'])->where('status', '!=', 'completed');
        if ($fromDate) {
            $q->whereDate('due_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('due_date', '<=', $toDate);
        }
        $rows = $q->orderBy('due_date')->get();

        return [
            'headers' => ['Task Title', 'Client', 'Category', 'Priority', 'Status', 'Due Date', 'Assigned To'],
            'rows' => $rows->map(fn ($t) => [
                $t->title,
                $t->client?->name ?? 'Internal',
                Task::CATEGORIES[$t->category] ?? ucfirst($t->category),
                $t->priorityLabel(),
                $t->statusLabel(),
                optional($t->due_date)->format('Y-m-d') ?? '—',
                $t->assignee?->name ?? 'Unassigned',
            ])->toArray(),
        ];
    }

    private function outstandingInvoicesReport(?string $fromDate, ?string $toDate): array
    {
        $q = Invoice::with('client')->whereIn('status', ['unpaid', 'partially_paid', 'overdue']);
        if ($fromDate) {
            $q->whereDate('invoice_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('invoice_date', '<=', $toDate);
        }
        $rows = $q->orderBy('due_date')->get();

        return [
            'headers' => ['Invoice #', 'Client', 'Invoice Date', 'Due Date', 'Total (INR)', 'Paid (INR)', 'Balance Due (INR)', 'Status'],
            'rows' => $rows->map(fn ($i) => [
                $i->invoice_number,
                $i->client?->name ?? '—',
                $i->invoice_date->format('Y-m-d'),
                $i->due_date->format('Y-m-d'),
                number_format((float) $i->total_amount, 2, '.', ''),
                number_format((float) $i->paid_amount, 2, '.', ''),
                number_format((float) $i->balance_amount, 2, '.', ''),
                $i->statusLabel(),
            ])->toArray(),
        ];
    }

    private function monthlyRevenueReport(?string $fromDate, ?string $toDate): array
    {
        $q = Payment::with(['client', 'invoice']);
        if ($fromDate) {
            $q->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('payment_date', '<=', $toDate);
        }
        $payments = $q->orderByDesc('payment_date')->get();

        return [
            'headers' => ['Payment Date', 'Invoice #', 'Client', 'Mode', 'Reference', 'Amount (INR)'],
            'rows' => $payments->map(fn ($p) => [
                $p->payment_date->format('Y-m-d'),
                $p->invoice?->invoice_number ?? '—',
                $p->client?->name ?? '—',
                Payment::MODES[$p->payment_mode] ?? ucfirst($p->payment_mode),
                $p->reference_number ?? '—',
                number_format((float) $p->amount, 2, '.', ''),
            ])->toArray(),
        ];
    }

    private function documentCollectionReport(?string $fromDate, ?string $toDate): array
    {
        $q = Document::with(['client', 'requester', 'uploader']);
        if ($fromDate) {
            $q->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $q->whereDate('created_at', '<=', $toDate);
        }
        $docs = $q->orderByDesc('updated_at')->get();

        return [
            'headers' => ['Document Title', 'Client', 'Category', 'Status', 'Version', 'Due Date', 'Requested By', 'Uploaded By'],
            'rows' => $docs->map(fn ($d) => [
                $d->title,
                $d->client?->name ?? '—',
                $d->categoryLabel(),
                $d->statusLabel(),
                'v'.$d->current_version,
                optional($d->due_date)->format('Y-m-d') ?? '—',
                $d->requester?->name ?? '—',
                $d->uploader?->name ?? '—',
            ])->toArray(),
        ];
    }

    private function tallyExportReport(?string $fromDate, ?string $toDate): array
    {
        $invQ = Invoice::with('client');
        $payQ = Payment::with(['client', 'invoice']);

        if ($fromDate) {
            $invQ->whereDate('invoice_date', '>=', $fromDate);
            $payQ->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $invQ->whereDate('invoice_date', '<=', $toDate);
            $payQ->whereDate('payment_date', '<=', $toDate);
        }

        $rows = [];

        foreach ($invQ->orderBy('invoice_date')->get() as $inv) {
            $rows[] = [
                $inv->invoice_date->format('Y-m-d'),
                'Sales',
                $inv->invoice_number,
                $inv->client?->name ?? '—',
                $inv->client?->gstin ?? 'Unregistered',
                number_format((float) $inv->taxable_amount, 2, '.', ''),
                number_format((float) $inv->cgst_amount, 2, '.', ''),
                number_format((float) $inv->sgst_amount, 2, '.', ''),
                number_format((float) $inv->igst_amount, 2, '.', ''),
                number_format((float) $inv->total_amount, 2, '.', ''),
                '0.00',
                '',
                '0.00',
                '',
                $inv->invoice_number,
            ];
        }

        foreach ($payQ->orderBy('payment_date')->get() as $pay) {
            $rows[] = [
                $pay->payment_date->format('Y-m-d'),
                'Receipt',
                $pay->invoice?->invoice_number ?? ('RCPT-'.$pay->id),
                $pay->client?->name ?? '—',
                $pay->client?->gstin ?? 'Unregistered',
                '0.00',
                '0.00',
                '0.00',
                '0.00',
                number_format((float) $pay->amount + (float) $pay->tds_deducted, 2, '.', ''),
                number_format((float) $pay->amount, 2, '.', ''),
                $pay->tds_section ?? '',
                number_format((float) $pay->tds_deducted, 2, '.', ''),
                Payment::MODES[$pay->payment_mode] ?? ucfirst($pay->payment_mode),
                $pay->reference_number ?? '—',
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a[0], $b[0]));

        return [
            'headers' => [
                'Voucher Date', 'Voucher Type', 'Voucher No', 'Party Ledger Name', 'Party GSTIN',
                'Taxable Value', 'CGST', 'SGST', 'IGST', 'Gross Amount',
                'Cash Received', 'TDS Section', 'TDS Deducted', 'Payment Mode', 'Reference / Narration',
            ],
            'rows' => $rows,
        ];
    }

    private function clientProfitabilityReport(?string $fromDate, ?string $toDate): array
    {
        $clients = Client::orderBy('name')->get();
        $rows = [];

        foreach ($clients as $client) {
            $invQ = Invoice::where('client_id', $client->id);
            $payQ = Payment::where('client_id', $client->id);
            $logQ = TaskTimeLog::whereHas('task', fn ($q) => $q->where('client_id', $client->id));

            if ($fromDate) {
                $invQ->whereDate('invoice_date', '>=', $fromDate);
                $payQ->whereDate('payment_date', '>=', $fromDate);
                $logQ->whereDate('logged_date', '>=', $fromDate);
            }
            if ($toDate) {
                $invQ->whereDate('invoice_date', '<=', $toDate);
                $payQ->whereDate('payment_date', '<=', $toDate);
                $logQ->whereDate('logged_date', '<=', $toDate);
            }

            $billed = (float) $invQ->sum('total_amount');
            $collected = (float) $payQ->sum('amount') + (float) $payQ->sum('tds_deducted');
            $totalMinutes = (int) (clone $logQ)->sum('minutes');
            $billableMinutes = (int) (clone $logQ)->where('is_billable', true)->sum('minutes');

            $totalHours = round($totalMinutes / 60, 2);
            $billableHours = round($billableMinutes / 60, 2);
            $effectiveRate = $totalHours > 0 ? round($billed / $totalHours, 2) : 0.0;

            $rows[] = [
                $client->client_code,
                $client->name,
                number_format($billed, 2, '.', ''),
                number_format($collected, 2, '.', ''),
                number_format($totalHours, 2, '.', ''),
                number_format($billableHours, 2, '.', ''),
                number_format($effectiveRate, 2, '.', ''),
            ];
        }

        return [
            'headers' => ['Client Code', 'Client Name', 'Billed Fees (INR)', 'Collected + TDS (INR)', 'Total Staff Hours', 'Billable Hours', 'Effective Rate / Hr (INR)'],
            'rows' => $rows,
        ];
    }
}
