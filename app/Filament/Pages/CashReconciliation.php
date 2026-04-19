<?php

namespace App\Filament\Pages;

use App\Models\CashReconciliation as ReconciliationModel;
use App\Models\Order;
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

    public string $selectedDate = '';

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
        $this->checkMissingDates();
    }

    public function updatedSelectedDate(): void
    {
        $this->actualAmounts  = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->notes          = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->denominations  = array_fill_keys(array_keys(self::DENOMINATION_VALUES), '');
        $this->checkMissingDates();
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
        $topupRows = WalletTopupRequest::whereRaw("DATE(CONVERT_TZ(updated_at, '+00:00', '" . self::TZ_OFFSET . "')) = ?", [$this->selectedDate])
            ->where('status', 'Approved')
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // 3. Change-to-wallet (always Cash — customer overpays, change goes to wallet)
        $changeTotal = WalletTransaction::whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '" . self::TZ_OFFSET . "')) = ?", [$this->selectedDate])
            ->where('type', 'change')
            ->whereNull('removed_at')
            ->sum('amount');

        $result = [];
        foreach (self::METHODS as $method) {
            $orderRow    = $orderRows->get($method);
            $topupRow    = $topupRows->get($method);
            $ordersTotal = $orderRow ? (float) $orderRow->total : 0.0;
            $topupTotal  = $topupRow ? (float) $topupRow->total : 0.0;
            $change      = $method === 'Cash' ? (float) $changeTotal : 0.0;

            $result[$method] = [
                'expected'     => $ordersTotal + $topupTotal + $change,
                'count'        => $orderRow ? (int) $orderRow->count : 0,
                'orders_total' => $ordersTotal,
                'orders_count' => $orderRow ? (int) $orderRow->count : 0,
                'topup_total'  => $topupTotal,
                'topup_count'  => $topupRow ? (int) $topupRow->count : 0,
                'change_total' => $change,
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
