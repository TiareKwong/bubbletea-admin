<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\CashReconciliation as ReconciliationModel;
use App\Models\DailyFloat;
use App\Models\Expense;
use App\Models\Order;
use App\Models\ReimbursementPayment;
use App\Models\StaffDeduction;
use App\Models\User;
use App\Models\WalletTopupRequest;
use App\Models\WalletTransaction;
use App\Services\BranchContext;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CashReconciliation extends Page
{
    protected string $view = 'filament.pages.cash-reconciliation';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Daily Reconciliation';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    public function getTitle(): string
    {
        $branch = app(BranchContext::class)->getBranch();
        return $branch ? 'Daily Reconciliation — ' . $branch->name : 'Daily Reconciliation';
    }

    public string $selectedDate   = '';
    public string $floatAmount    = '';
    public ?string $floatSetBy    = null;
    public ?string $floatUpdatedAt = null;

    public array $actualAmounts = [
        'Cash'          => '',
        'EFTPOS'        => '',
        'Bank Transfer' => '',
    ];

    public array $notes = [
        'Cash'          => '',
        'EFTPOS'        => '',
        'Bank Transfer' => '',
    ];

    // Staff (user IDs) checked as on-shift for the Cash count currently
    // being submitted — used to attribute/split a shortage deduction.
    public array $cashShiftStaff = [];

    // Denomination counts for cash counting
    public array $denominations = [
        '5c'   => '',
        '10c'  => '',
        '20c'  => '',
        '50c'  => '',
        '$1'   => '',
        '$2'   => '',
        '$5'   => '',
        '$10'  => '',
        '$20'  => '',
        '$50'  => '',
        '$100' => '',
    ];

    private const DENOMINATION_VALUES = [
        '5c'   => 0.05,
        '10c'  => 0.10,
        '20c'  => 0.20,
        '50c'  => 0.50,
        '$1'   => 1.00,
        '$2'   => 2.00,
        '$5'   => 5.00,
        '$10'  => 10.00,
        '$20'  => 20.00,
        '$50'  => 50.00,
        '$100' => 100.00,
    ];

    public function getCashDenominationTotal(): float
    {
        $total = 0.0;
        foreach (self::DENOMINATION_VALUES as $key => $value) {
            $count = max(0, (int) ($this->denominations[$key] ?? 0));
            $total += $count * $value;
        }
        return $total;
    }

    public function updatedDenominations(): void
    {
        $this->actualAmounts['Cash'] = (string) $this->getCashDenominationTotal();
    }

    private const METHODS = ['Cash', 'EFTPOS', 'Bank Transfer'];

    private const TZ_OFFSET = '+12:00'; // Pacific/Tarawa

    public function mount(): void
    {
        $this->selectedDate = now('Pacific/Tarawa')->toDateString();
        $this->loadFloat();
        $this->checkMissingDates();
    }

    public function updatedSelectedDate(): void
    {
        $this->actualAmounts  = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->notes          = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->denominations  = array_fill_keys(array_keys(self::DENOMINATION_VALUES), '');
        $this->cashShiftStaff = [];
        $this->loadFloat();
        $this->checkMissingDates();
    }

    public function getBranchStaffOptionsProperty(): array
    {
        // Not scoped to the current branch on purpose — staff sometimes
        // cover a shift at a branch other than their assigned one, so
        // whoever submits the reconciliation should be able to pick
        // anyone who was actually on shift, not just that branch's roster.
        return User::where('is_staff', true)
            ->where('is_admin', false)
            ->where('is_super_staff', false)
            ->where('email', '!=', 'guest@internal.local')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])
            ->toArray();
    }

    private function loadFloat(): void
    {
        $existing = DailyFloat::where('date', $this->selectedDate)
            ->where('branch_id', $this->branchId())
            ->first();

        $this->floatAmount    = $existing ? number_format((float) $existing->amount, 2) : '';
        $this->floatSetBy     = $existing?->set_by;
        $this->floatUpdatedAt = $existing
            ? $existing->updated_at->setTimezone('Pacific/Tarawa')->format('d M Y, h:i A')
            : null;
    }

    public function saveFloat(): void
    {
        $amount   = max(0.0, (float) str_replace(',', '', $this->floatAmount ?: '0'));
        $branchId = $this->branchId();

        $hasSubmissions = ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate)
            ->where('branch_id', $branchId)
            ->exists();

        DailyFloat::updateOrCreate(
            ['branch_id' => $branchId, 'date' => $this->selectedDate],
            ['amount' => $amount, 'set_by' => auth()->user()->getFilamentName()]
        );

        $this->floatAmount    = number_format($amount, 2);
        $this->floatSetBy     = auth()->user()->getFilamentName();
        $this->floatUpdatedAt = now('Pacific/Tarawa')->format('d M Y, h:i A');

        if ($hasSubmissions) {
            Notification::make()
                ->title('Float updated — A$' . number_format($amount, 2))
                ->body('Float has already been used in submitted reconciliation entries for this date. Changing it may cause mismatches with previously submitted figures. The expected cash shown on screen now reflects the new float.')
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Float saved — A$' . number_format($amount, 2))
                ->success()
                ->send();
        }
    }

    private function branchId(): ?int
    {
        return app(BranchContext::class)->getId();
    }

    private function scopeOrderQuery($query)
    {
        $id = $this->branchId();
        return $id ? $query->where('branch_id', $id) : $query;
    }

    private function scopeReconQuery($query)
    {
        $id = $this->branchId();
        return $id ? $query->where('branch_id', $id) : $query;
    }

    public function getAvailableDates(): array
    {
        $today = now('Pacific/Tarawa')->toDateString();

        $dates = $this->scopeOrderQuery(Order::whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) <= ?", [$today]))
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) as order_date")
            ->groupBy('order_date')
            ->orderByDesc('order_date')
            ->pluck('order_date')
            ->map(fn ($d) => (string) $d)
            ->toArray();

        if (! in_array($today, $dates)) {
            array_unshift($dates, $today);
        }

        return $dates;
    }

    public function isBackfill(): bool
    {
        return $this->selectedDate < now('Pacific/Tarawa')->toDateString();
    }

    public function getMethodTotals(): array
    {
        // 1. Order totals per payment method
        $orderRows = $this->scopeOrderQuery(Order::whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) = ?", [$this->selectedDate]))
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw('payment_method, SUM(total_price) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // 2. Approved wallet top-ups per payment method (approved on selected date)
        $branchId = $this->branchId();
        $topupQuery = WalletTopupRequest::whereRaw("DATE(updated_at) = ?", [$this->selectedDate])
            ->where('status', 'Approved');
        if ($branchId) {
            $topupQuery->where('branch_id', $branchId);
        }
        $topupRows = $topupQuery
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // 3. Change-to-wallet (always Cash — customer overpays, change goes to wallet)
        $changeQuery = WalletTransaction::whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) = ?", [$this->selectedDate])
            ->where('type', 'change')
            ->whereNull('removed_at');
        if ($branchId) {
            $changeQuery->where('branch_id', $branchId);
        }
        $changeTotal = $changeQuery->sum('amount');

        // 4. Cash box expenses (deducted from Cash expected total)
        $expenseQuery = Expense::where('expense_date', $this->selectedDate)
            ->where('paid_from', 'cash_box');
        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }
        $cashBoxExpenses = (float) $expenseQuery->sum('amount');

        // 4b. Cash reimbursement payments (cash paid out to reimburse staff — deducted from Cash)
        $reimbQuery = ReimbursementPayment::where('payment_date', $this->selectedDate)
            ->where('payment_method', 'Cash');
        if ($branchId) {
            $reimbQuery->where('branch_id', $branchId);
        }
        $cashReimbursements = (float) $reimbQuery->sum('amount');

        // 5. Opening float (Cash only)
        $floatRecord = DailyFloat::where('date', $this->selectedDate)
            ->where('branch_id', $branchId)
            ->first();
        $floatAmount = $floatRecord ? (float) $floatRecord->amount : 0.0;

        $result = [];
        foreach (self::METHODS as $method) {
            $orderRow    = $orderRows->get($method);
            $topupRow    = $topupRows->get($method);
            $ordersTotal = $orderRow ? (float) $orderRow->total : 0.0;
            $topupTotal  = $topupRow ? (float) $topupRow->total : 0.0;
            $change      = $method === 'Cash' ? (float) $changeTotal : 0.0;
            $expenses    = $method === 'Cash' ? $cashBoxExpenses : 0.0;
            $reimb       = $method === 'Cash' ? $cashReimbursements : 0.0;
            $float       = $method === 'Cash' ? $floatAmount : 0.0;

            $result[$method] = [
                'expected'       => $float + $ordersTotal + $topupTotal + $change - $expenses - $reimb,
                'count'          => $orderRow ? (int) $orderRow->count : 0,
                'orders_total'   => $ordersTotal,
                'orders_count'   => $orderRow ? (int) $orderRow->count : 0,
                'topup_total'    => $topupTotal,
                'topup_count'    => $topupRow ? (int) $topupRow->count : 0,
                'change_total'   => $change,
                'expense_total'  => $expenses,
                'reimb_total'    => $reimb,
                'float_amount'   => $float,
            ];
        }
        return $result;
    }

    public function getSelectedDateReconciliations(): array
    {
        return $this->scopeReconQuery(ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate))
            ->orderBy('submitted_at', 'asc')
            ->get()
            ->groupBy('payment_method')
            ->map(fn ($group) => $group->toArray())
            ->toArray();
    }

    public array $missingDates = [];

    public function checkMissingDates(): void
    {
        $from      = now('Pacific/Tarawa')->subDays(30)->toDateString();
        $yesterday = now('Pacific/Tarawa')->subDay()->toDateString();

        // Query 1: all (date, method) pairs that had orders in the last 30 days.
        $orderPairs = $this->scopeOrderQuery(Order::whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) >= ?", [$from]))
            ->whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) <= ?", [$yesterday])
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) as d, payment_method as m")
            ->groupBy('d', 'm')
            ->get()
            ->map(fn ($r) => $r->d . '|' . $r->m)
            ->toArray();

        if (empty($orderPairs)) {
            $this->missingDates = [];
            return;
        }

        // Query 2: all (date, method) pairs already reconciled in the same window.
        $reconciledPairs = $this->scopeReconQuery(ReconciliationModel::whereDate('reconciliation_date', '>=', $from))
            ->whereDate('reconciliation_date', '<=', $yesterday)
            ->selectRaw('DATE(reconciliation_date) as d, payment_method as m')
            ->get()
            ->map(fn ($r) => $r->d . '|' . $r->m)
            ->toArray();

        // Any order pair not in reconciled pairs = that date is missing.
        $missingPairs = array_diff($orderPairs, $reconciledPairs);

        $this->missingDates = collect($missingPairs)
            ->map(fn ($pair) => explode('|', $pair)[0])
            ->unique()
            ->sort()
            ->reverse()
            ->values()
            ->toArray();
    }

    public function submitMethod(string $method): void
    {
        if (! in_array($method, self::METHODS)) {
            return;
        }

        // Only admins can back-fill past dates.
        if ($this->isBackfill() && ! auth()->user()?->is_admin) {
            Notification::make()->title('Only admins can submit for past dates')->danger()->send();
            return;
        }

        $actual          = (float) str_replace(',', '', $this->actualAmounts[$method] ?? '0');
        $totalExpected   = $this->getMethodTotals()[$method]['expected'];
        $alreadyActual   = (float) $this->scopeReconQuery(ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate))
            ->where('payment_method', $method)
            ->sum('actual_cash');
        $residualExpected = $totalExpected - $alreadyActual;
        $difference       = $actual - $residualExpected;

        $branchStaffAvailable = $method === 'Cash' && count($this->branchStaffOptions) > 0;

        if ($branchStaffAvailable && $difference < 0 && empty($this->cashShiftStaff)) {
            Notification::make()
                ->title('Select who was on shift first')
                ->body('This count is A$' . number_format(abs($difference), 2) . ' short. Check off which staff were on shift for this cash count before submitting — the shortage will be split between them.')
                ->warning()
                ->send();
            return;
        }

        $reconciliation = ReconciliationModel::create([
            'reconciliation_date' => $this->selectedDate,
            'payment_method'      => $method,
            'expected_cash'       => $residualExpected,
            'actual_cash'         => $actual,
            'difference'          => $difference,
            'notes'               => $this->notes[$method] ?: null,
            'submitted_by'        => auth()->user()->getFilamentName(),
            'submitted_at'        => now('UTC'),
            'branch_id'           => $this->branchId(),
            'shift_staff_ids'     => $method === 'Cash' ? array_values(array_unique(array_map('intval', $this->cashShiftStaff))) : null,
        ]);

        $this->actualAmounts[$method] = '';
        $this->notes[$method]         = '';
        $this->cashShiftStaff         = [];
        if ($method === 'Cash') {
            $this->denominations = array_fill_keys(array_keys(self::DENOMINATION_VALUES), '');
        }

        // Shortage deductions are only finalized once the whole day is
        // reconciled — a Cash short submitted now might get offset by an
        // EFTPOS/Bank Transfer surplus submitted later the same day (e.g. a
        // split payment the POS only recorded under one method).
        $dayNote = $this->reconcileDayDeductions();

        $label        = $this->isBackfill() ? "$method reconciliation back-filled" : "$method reconciliation submitted";
        $notification = Notification::make()->title($label)->success();
        if ($dayNote) {
            $notification->body($dayNote);
        }
        $notification->send();
    }

    /**
     * A method's residual can never reach zero once there's a genuine,
     * confirmed shortfall (a "yes, that's really missing" submission still
     * leaves a gap — that's the point of it), so "day closed" can't mean
     * "residual paid off". It means every payment method with real activity
     * that day has at least one submission — same notion as the "Day
     * closed" badge already shown on this page.
     */
    private function isDayFullyReconciled(): bool
    {
        $methodTotals = $this->getMethodTotals();

        foreach ($methodTotals as $method => $data) {
            $hasActivity = $data['orders_count'] > 0 || $data['topup_total'] > 0 || $data['change_total'] > 0
                || ($data['reimb_total'] ?? 0) > 0 || ($data['float_amount'] ?? 0) > 0;

            if (! $hasActivity) {
                continue;
            }

            $hasSubmission = $this->scopeReconQuery(ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate))
                ->where('payment_method', $method)
                ->exists();

            if (! $hasSubmission) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recomputes shortage deductions for the selected date/branch from
     * scratch: nets Cash shortfalls against EFTPOS/Bank Transfer surpluses
     * (only surpluses offset — a shortfall in those methods never adds to
     * the deduction) once every method with activity that day has been
     * submitted. Idempotent — safe to call after every submission.
     */
    private function reconcileDayDeductions(): ?string
    {
        $branchId = $this->branchId();

        $cashRows = ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate)
            ->where('branch_id', $branchId)
            ->where('payment_method', 'Cash')
            ->get();

        $cashReconciliationIds = $cashRows->pluck('id')->toArray();

        // Always clear out previously auto-created deductions for this day
        // first — the recompute below is the single source of truth.
        StaffDeduction::where('source', 'cash_short')
            ->whereIn('cash_reconciliation_id', $cashReconciliationIds ?: [0])
            ->delete();

        if (! $this->isDayFullyReconciled()) {
            return null;
        }

        // Net variance per method must be computed as (total actual submitted
        // − total expected), NOT by summing each row's stored `difference` —
        // when a method has multiple incremental submissions, each row's
        // `difference` is measured against a shrinking residual, so summing
        // them overstates the real shortfall.
        $methodTotals = $this->getMethodTotals();

        $cashNet = (float) $cashRows->sum('actual_cash') - $methodTotals['Cash']['expected'];

        if ($cashNet >= 0) {
            return 'Day fully reconciled — no shortage to deduct.';
        }

        $otherSurplus = 0.0;
        foreach (['EFTPOS', 'Bank Transfer'] as $otherMethod) {
            $actualSum = (float) ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate)
                ->where('branch_id', $branchId)
                ->where('payment_method', $otherMethod)
                ->sum('actual_cash');

            $net = $actualSum - $methodTotals[$otherMethod]['expected'];

            if ($net > 0) {
                $otherSurplus += $net;
            }
        }

        $deductible = round(max(0.0, abs($cashNet) - $otherSurplus), 2);

        if ($deductible <= 0) {
            return 'Cash was short but fully offset by surpluses on other payment methods today — no deduction.';
        }

        $staffIds = collect($cashRows)
            ->flatMap(fn (ReconciliationModel $r) => $r->shift_staff_ids ?? [])
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($staffIds)) {
            return 'A$' . number_format($deductible, 2) . ' net shortage today, but no staff were recorded as on shift — not attributed to anyone.';
        }

        $latestCashReconciliationId = $cashRows->sortByDesc('id')->first()?->id;

        return $this->createShortageDeductions($staffIds, $deductible, $latestCashReconciliationId, $otherSurplus);
    }

    /**
     * Splits a net shortage evenly (to the cent) across the given staff,
     * creating one StaffDeduction each. Returns a human-readable summary
     * for the submission notification.
     */
    private function createShortageDeductions(array $staffIds, float $deductible, ?int $cashReconciliationId, float $offsetByOtherMethods): string
    {
        $staff  = User::whereIn('id', $staffIds)->get()->keyBy('id');
        $branch = $this->branchId() ? Branch::find($this->branchId()) : null;

        $n           = count($staffIds);
        $totalCents  = (int) round($deductible * 100);
        $baseCents   = intdiv($totalCents, $n);
        $remainder   = $totalCents % $n;
        $submittedBy = auth()->user()->getFilamentName();

        $reasonPrefix = 'Cash short — ' . ($branch?->name ?? 'Branch') . ', ' . Carbon::parse($this->selectedDate)->format('d M Y');
        if ($offsetByOtherMethods > 0) {
            $reasonPrefix .= ' (net of A$' . number_format($offsetByOtherMethods, 2) . ' surplus on other methods)';
        }

        $names = [];
        foreach ($staffIds as $i => $userId) {
            $cents  = $baseCents + ($i < $remainder ? 1 : 0);
            $amount = $cents / 100;

            StaffDeduction::create([
                'user_id'                => $userId,
                'date'                   => $this->selectedDate,
                'amount'                 => $amount,
                'reason'                 => $reasonPrefix,
                'source'                 => 'cash_short',
                'cash_reconciliation_id' => $cashReconciliationId,
                'created_by'             => $submittedBy,
            ]);

            $names[] = ($staff->get($userId)?->full_name ?? 'Unknown') . ' (A$' . number_format($amount, 2) . ')';
        }

        return 'A$' . number_format($deductible, 2) . ' net shortage added as a deduction — ' . implode(', ', $names) . '.';
    }
}
