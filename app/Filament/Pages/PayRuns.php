<?php

namespace App\Filament\Pages;

use App\Models\PayRun;
use App\Models\PayRunEntry;
use App\Models\Timesheet;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PayRuns extends Page
{
    protected string $view = 'filament.pages.pay-runs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Pay Runs';

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    public string $weekStart = '';
    public string $weekEnd   = '';
    public string $notes     = '';
    public bool   $previewing = false;

    public array $preview = [];

    public function mount(): void
    {
        $monday = now('Pacific/Tarawa')->startOfWeek();
        $this->weekStart = $monday->toDateString();
        $this->weekEnd   = $monday->copy()->endOfWeek()->toDateString();
    }

    public function preview(): void
    {
        if (! $this->weekStart || ! $this->weekEnd) {
            Notification::make()->title('Please select a week range.')->warning()->send();
            return;
        }

        $existing = PayRun::where('week_start', $this->weekStart)
            ->where('week_end', $this->weekEnd)
            ->first();

        if ($existing) {
            Notification::make()
                ->title('A pay run already exists for this week.')
                ->body('Week of ' . $this->weekStart . ' to ' . $this->weekEnd)
                ->warning()
                ->send();
            return;
        }

        $staff = User::where('is_staff', true)
            ->where('is_admin', false)
            ->where('is_super_staff', false)
            ->where('email', '!=', 'guest@internal.local')
            ->get();

        $this->preview = [];

        foreach ($staff as $member) {
            $hours = (float) Timesheet::where('user_id', $member->id)
                ->whereBetween('work_date', [$this->weekStart, $this->weekEnd])
                ->sum('hours_worked');

            if ($hours <= 0) continue;

            $rate        = (float) ($member->hourly_rate ?? 0);
            $gross       = round($hours * $rate, 2);
            $empKpf      = round($gross * 0.075, 2);
            $employerKpf = round($gross * 0.075, 2);
            $net         = round($gross - $empKpf, 2);

            $this->preview[] = [
                'user_id'      => $member->id,
                'name'         => $member->full_name,
                'hourly_rate'  => $rate,
                'total_hours'  => $hours,
                'gross_pay'    => $gross,
                'employee_kpf' => $empKpf,
                'employer_kpf' => $employerKpf,
                'net_pay'      => $net,
            ];
        }

        if (empty($this->preview)) {
            Notification::make()
                ->title('No timesheets found for this week.')
                ->body('Make sure hours have been logged before creating a pay run.')
                ->warning()
                ->send();
            return;
        }

        $this->previewing = true;
    }

    public function createPayRun(): void
    {
        DB::transaction(function () {
            $payRun = PayRun::create([
                'week_start'  => $this->weekStart,
                'week_end'    => $this->weekEnd,
                'status'      => 'draft',
                'notes'       => $this->notes ?: null,
                'created_by'  => auth()->user()->getFilamentName(),
            ]);

            foreach ($this->preview as $entry) {
                PayRunEntry::create([
                    'pay_run_id'   => $payRun->id,
                    'user_id'      => $entry['user_id'],
                    'hourly_rate'  => $entry['hourly_rate'],
                    'total_hours'  => $entry['total_hours'],
                    'gross_pay'    => $entry['gross_pay'],
                    'employee_kpf' => $entry['employee_kpf'],
                    'employer_kpf' => $entry['employer_kpf'],
                    'net_pay'      => $entry['net_pay'],
                ]);
            }
        });

        $this->previewing = false;
        $this->preview    = [];
        $this->notes      = '';

        Notification::make()->title('Pay run created successfully.')->success()->send();
    }

    public function cancelPreview(): void
    {
        $this->previewing = false;
        $this->preview    = [];
    }

    public function markAsPaid(int $payRunId): void
    {
        $payRun = PayRun::findOrFail($payRunId);
        $payRun->update(['status' => 'paid', 'paid_at' => now()]);
        Notification::make()->title('Pay run marked as paid.')->success()->send();
    }

    public function getPayRunsProperty(): \Illuminate\Support\Collection
    {
        return PayRun::with('entries.user')
            ->orderBy('week_start', 'desc')
            ->get();
    }
}
