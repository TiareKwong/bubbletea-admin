<?php

namespace App\Filament\Pages;

use App\Models\CashReconciliation as ReconciliationModel;
use App\Models\DailyFloat;
use App\Models\Expense;
use App\Models\Order;
use App\Models\ReimbursementPayment;
use App\Models\WalletTopupRequest;
use App\Models\WalletTransaction;
use App\Services\BranchContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CashReconciliation extends Page
{
    protected string $view = 'filament.pages.cash-reconciliation';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Daily Reconciliation';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

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
        $this->loadFloat();
        $this->checkMissingDates();
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

        ReconciliationModel::create([
            'reconciliation_date' => $this->selectedDate,
            'payment_method'      => $method,
            'expected_cash'       => $residualExpected,
            'actual_cash'         => $actual,
            'difference'          => $actual - $residualExpected,
            'notes'               => $this->notes[$method] ?: null,
            'submitted_by'        => auth()->user()->getFilamentName(),
            'submitted_at'        => now('UTC'),
            'branch_id'           => $this->branchId(),
        ]);

        $this->actualAmounts[$method] = '';
        $this->notes[$method]         = '';
        if ($method === 'Cash') {
            $this->denominations = array_fill_keys(array_keys(self::DENOMINATION_VALUES), '');
        }

        $label = $this->isBackfill() ? "$method reconciliation back-filled" : "$method reconciliation submitted";
        Notification::make()->title($label)->success()->send();
    }
}
