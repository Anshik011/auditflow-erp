<?php

namespace App\Console\Commands;

use App\Models\ComplianceDeadline;
use App\Models\ComplianceRule;
use App\Services\ComplianceCalendarService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringDeadlinesCommand extends Command
{
    protected $signature = 'app:generate-recurring-deadlines';

    protected $description = 'Generate recurring compliance deadlines from active rules and mark overdue items.';

    public function handle(ComplianceCalendarService $calendarService): int
    {
        $today = Carbon::today();

        // Mark past-due incomplete deadlines as overdue
        $overdueUpdated = ComplianceDeadline::query()
            ->whereIn('status', ['upcoming', 'in_progress'])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        $created = 0;
        $rules = ComplianceRule::query()->where('is_active', true)->get();

        foreach ($rules as $rule) {
            if ($rule->frequency === 'one_time') {
                continue;
            }

            if ($rule->frequency === 'monthly') {
                $targetDate = Carbon::create($today->year, $today->month, 1)->addMonths($rule->month_offset);
                $dueDate = $targetDate->copy()->day(min($rule->due_day, $targetDate->daysInMonth));

                $exists = ComplianceDeadline::query()
                    ->where('compliance_rule_id', $rule->id)
                    ->whereDate('due_date', $dueDate)
                    ->exists();

                if (! $exists) {
                    $calendarService->generateFromRule($rule, $today->year, $today->month);
                    $created++;
                }
            } elseif ($rule->frequency === 'quarterly') {
                $quarterMonth = (int) (ceil($today->month / 3) * 3);
                $targetDate = Carbon::create($today->year, $quarterMonth, 1)->addMonths($rule->month_offset);
                $dueDate = $targetDate->copy()->day(min($rule->due_day, $targetDate->daysInMonth));

                $exists = ComplianceDeadline::query()
                    ->where('compliance_rule_id', $rule->id)
                    ->whereDate('due_date', $dueDate)
                    ->exists();

                if (! $exists) {
                    $calendarService->generateFromRule($rule, $today->year, $quarterMonth);
                    $created++;
                }
            } elseif ($rule->frequency === 'annual') {
                $baseMonth = $rule->due_month ?? $today->month;
                $targetDate = Carbon::create($today->year, $baseMonth, 1)->addMonths($rule->month_offset);
                $dueDate = $targetDate->copy()->day(min($rule->due_day, $targetDate->daysInMonth));

                $exists = ComplianceDeadline::query()
                    ->where('compliance_rule_id', $rule->id)
                    ->whereDate('due_date', $dueDate)
                    ->exists();

                if (! $exists) {
                    $calendarService->generateFromRule($rule, $today->year, $baseMonth);
                    $created++;
                }
            }
        }

        $this->info("Generated {$created} deadlines; marked {$overdueUpdated} overdue.");

        return self::SUCCESS;
    }
}
