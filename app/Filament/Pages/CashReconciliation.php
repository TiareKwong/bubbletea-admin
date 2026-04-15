<?php

namespace App\Filament\Pages;

use App\Models\CashReconciliation as ReconciliationModel;
use App\Models\Order;
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

    private const METHODS = ['Cash', 'EFTPOS', 'Bank Transfer'];

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->checkMissingDates();
    }

    public function updatedSelectedDate(): void
    {
        $this->actualAmounts = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->notes         = ['Cash' => '', 'EFTPOS' => '', 'Bank Transfer' => ''];
        $this->checkMissingDates();
    }

    public function getAvailableDates(): array
    {
        // All distinct dates (by updated_at) that have reconcilable orders, up to today.
        $dates = Order::whereDate('updated_at', '<=', now()->toDateString())
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw('DATE(updated_at) as order_date')
            ->groupBy('order_date')
            ->orderByDesc('order_date')
            ->pluck('order_date')
            ->map(fn ($d) => (string) $d)
            ->toArray();

        // Always include today even if there are no orders yet.
        $today = now()->toDateString();
        if (! in_array($today, $dates)) {
            array_unshift($dates, $today);
        }

        return $dates;
    }

    public function isBackfill(): bool
    {
        return $this->selectedDate < now()->toDateString();
    }

    public function getMethodTotals(): array
    {
        $rows = Order::whereDate('updated_at', $this->selectedDate)
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw('payment_method, SUM(total_price) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $result = [];
        foreach (self::METHODS as $method) {
            $row = $rows->get($method);
            $result[$method] = [
                'expected' => $row ? (float) $row->total : 0.0,
                'count'    => $row ? (int)   $row->count : 0,
            ];
        }
        return $result;
    }

    public function getSelectedDateReconciliations(): array
    {
        return ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate)
            ->get()
            ->keyBy('payment_method')
            ->toArray();
    }

    public array $missingDates = [];

    public function checkMissingDates(): void
    {
        $from = now()->subDays(30)->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Query 1: all (date, method) pairs that had orders in the last 30 days.
        $orderPairs = Order::whereDate('updated_at', '>=', $from)
            ->whereDate('updated_at', '<=', $yesterday)
            ->whereIn('payment_method', self::METHODS)
            ->where(function ($q) {
                $q->whereIn('order_status', ['Paid'])->orWhere('collected', true);
            })
            ->selectRaw('DATE(updated_at) as d, payment_method as m')
            ->groupBy('d', 'm')
            ->get()
            ->map(fn ($r) => $r->d . '|' . $r->m)
            ->toArray();

        if (empty($orderPairs)) {
            $this->missingDates = [];
            return;
        }

        // Query 2: all (date, method) pairs already reconciled in the same window.
        $reconciledPairs = ReconciliationModel::whereDate('reconciliation_date', '>=', $from)
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

        if (ReconciliationModel::whereDate('reconciliation_date', $this->selectedDate)
            ->where('payment_method', $method)
            ->exists()) {
            Notification::make()->title("$method already submitted for this date")->warning()->send();
            return;
        }

        $actual   = (float) str_replace(',', '', $this->actualAmounts[$method] ?? '0');
        $expected = $this->getMethodTotals()[$method]['expected'];

        ReconciliationModel::create([
            'reconciliation_date' => $this->selectedDate,
            'payment_method'      => $method,
            'expected_cash'       => $expected,
            'actual_cash'         => $actual,
            'difference'          => $actual - $expected,
            'notes'               => $this->notes[$method] ?: null,
            'submitted_by'        => auth()->user()->getFilamentName(),
            'submitted_at'        => now(),
        ]);

        $this->actualAmounts[$method] = '';
        $this->notes[$method]         = '';

        $label = $this->isBackfill() ? "$method reconciliation back-filled" : "$method reconciliation submitted";
        Notification::make()->title($label)->success()->send();
    }
}
