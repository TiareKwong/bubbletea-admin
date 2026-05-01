<?php

namespace App\Filament\Pages;

use App\Models\DailyFloat;
use App\Models\Expense;
use App\Models\Order;
use App\Models\WalletTopupRequest;
use App\Services\BranchContext;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class SalesReport extends Page
{
    protected string $view = 'filament.pages.sales-report';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Sales Report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        $branch = app(BranchContext::class)->getBranch();
        return $branch ? 'Sales Report — ' . $branch->name : 'Sales Report';
    }

    public string $period        = 'day';
    public int    $selectedMonth;
    public int    $selectedYear;

    public function mount(): void
    {
        $this->selectedMonth = (int) now('Pacific/Tarawa')->month;
        $this->selectedYear  = (int) now('Pacific/Tarawa')->year;

        // Staff may only view Day and Week; reset if they somehow land on a restricted period.
        if (! auth()->user()?->is_admin && in_array($this->period, ['month', 'year'])) {
            $this->period = 'day';
        }
    }

    /** Whether the current user can see Month/Year tabs. */
    public function canViewExtendedPeriods(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    /** Called when Livewire sets the period property — guard against staff selecting month/year. */
    public function updatedPeriod(string $value): void
    {
        if (! auth()->user()?->is_admin && in_array($value, ['month', 'year'])) {
            $this->period = 'day';
        }
    }

    public function previousYear(): void { $this->selectedYear--; }
    public function nextYear(): void     { $this->selectedYear++; }

    public function getPeriodLabel(): string
    {
        $tz = 'Pacific/Tarawa';

        return match ($this->period) {
            'day'   => 'Today - ' . now($tz)->format('d M Y'),
            'week'  => 'This Week (' . now($tz)->copy()->startOfWeek()->format('d M') . ' - ' . now($tz)->copy()->endOfWeek()->format('d M Y') . ')',
            'month' => \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth)->format('F Y'),
            'year'  => (string) $this->selectedYear,
            default => '',
        };
    }

    protected function dateRange(): array
    {
        // Compute boundaries in Tarawa time, then convert to UTC so they match
        // the UTC-stored created_at values in the database.
        $tz = 'Pacific/Tarawa';

        return match ($this->period) {
            'day'   => [
                now($tz)->startOfDay()->utc(),
                now($tz)->endOfDay()->utc(),
            ],
            'week'  => [
                now($tz)->copy()->startOfWeek()->utc(),
                now($tz)->copy()->endOfWeek()->utc(),
            ],
            'month' => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->utc(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->utc(),
            ],
            'year'  => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->utc(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->utc(),
            ],
            default => [
                now($tz)->startOfDay()->utc(),
                now($tz)->endOfDay()->utc(),
            ],
        };
    }

    // Only these statuses represent confirmed, received payments.
    private const PAID_STATUSES = ['Paid', 'Preparing', 'Ready', 'Collected'];

    private function branchId(): ?int
    {
        return app(BranchContext::class)->getId();
    }

    private function scopeOrders($query)
    {
        $id = $this->branchId();
        return $id ? $query->where('orders.branch_id', $id) : $query;
    }

    public function getAvailableYears(): array
    {
        $earliest = (int) Order::min(DB::raw('YEAR(created_at)'));
        $current  = (int) now('Pacific/Tarawa')->year;
        $from     = max($earliest ?: $current, $current - 4);

        return range($from, $current);
    }

    public function getSummary(): array
    {
        [$from, $to] = $this->dateRange();

        $row = $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->whereIn('order_status', self::PAID_STATUSES)
            ->select([
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total_price), 0) as total_revenue'),
                DB::raw('COALESCE(AVG(total_price), 0) as avg_order_value'),
                DB::raw('SUM(collected = 1) as collected_count'),
            ])
            ->first();

        return [
            'total_orders'    => (int)   ($row->total_orders    ?? 0),
            'total_revenue'   => (float) ($row->total_revenue   ?? 0),
            'avg_order_value' => (float) ($row->avg_order_value ?? 0),
            'collected_count' => (int)   ($row->collected_count ?? 0),
        ];
    }

    public function getPaymentBreakdown(): array
    {
        [$from, $to] = $this->dateRange();

        return $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->whereIn('order_status', self::PAID_STATUSES)
            ->select('payment_method', DB::raw('COUNT(*) as orders'), DB::raw('COALESCE(SUM(total_price), 0) as revenue'))
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    public function getTopFlavors(): array
    {
        [$from, $to] = $this->dateRange();

        $q = DB::table('order_items')
            ->join('orders',  'order_items.order_id',  '=', 'orders.id')
            ->join('flavors', 'order_items.flavor_id', '=', 'flavors.id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.order_status', self::PAID_STATUSES);

        $branchId = $this->branchId();
        if ($branchId) {
            $q->where('orders.branch_id', $branchId);
        }

        return $q
            ->select(
                'flavors.name',
                DB::raw('SUM(order_items.quantity) as qty'),
                DB::raw('SUM(order_items.price) as revenue')
            )
            ->groupBy('flavors.id', 'flavors.name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getStatusBreakdown(): array
    {
        [$from, $to] = $this->dateRange();

        return $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->select('order_status', DB::raw('COUNT(*) as count'))
            ->groupBy('order_status')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    public function getFloatSummary(): array
    {
        $tz = 'Pacific/Tarawa';

        [$dateFrom, $dateTo] = match ($this->period) {
            'day'   => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'  => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month' => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'  => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            default => [now($tz)->toDateString(), now($tz)->toDateString()],
        };

        $query = DailyFloat::whereBetween('date', [$dateFrom, $dateTo]);
        $branchId = $this->branchId();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'total' => (float) $query->sum('amount'),
            'days'  => (int)   $query->count(),
        ];
    }

    public function getTopupSummary(): array
    {
        $tz = 'Pacific/Tarawa';

        [$dateFrom, $dateTo] = match ($this->period) {
            'day'   => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'  => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month' => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'  => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            default => [now($tz)->toDateString(), now($tz)->toDateString()],
        };

        $branchId = $this->branchId();

        $query = WalletTopupRequest::whereRaw("DATE(updated_at) BETWEEN ? AND ?", [$dateFrom, $dateTo])
            ->where('status', 'Approved');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $rows = $query
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'rows'  => $rows,
            'total' => (float) array_sum(array_column($rows, 'total')),
            'count' => (int)   array_sum(array_column($rows, 'count')),
        ];
    }

    public function getExpenseSummary(): array
    {
        $tz = 'Pacific/Tarawa';

        [$expenseFrom, $expenseTo] = match ($this->period) {
            'day'   => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'  => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month' => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'  => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            default => [now($tz)->toDateString(), now($tz)->toDateString()],
        };

        $expenseQuery = Expense::whereBetween('expense_date', [$expenseFrom, $expenseTo]);
        $branchId = $this->branchId();
        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }

        $rows = $expenseQuery
            ->select('category', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $totalExpenses = array_sum(array_column($rows, 'total'));

        return [
            'rows'  => $rows,
            'total' => (float) $totalExpenses,
        ];
    }

    public function getComparisonSummary(): array
    {
        $tz = 'Pacific/Tarawa';

        [$from, $to] = match ($this->period) {
            'day'  => [now($tz)->subDay()->startOfDay()->utc(), now($tz)->subDay()->endOfDay()->utc()],
            'week' => [now($tz)->copy()->subWeek()->startOfWeek()->utc(), now($tz)->copy()->subWeek()->endOfWeek()->utc()],
            'month' => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->subMonthNoOverflow()->startOfMonth()->utc(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->subMonthNoOverflow()->endOfMonth()->utc(),
            ],
            'year' => [
                \Carbon\Carbon::create($this->selectedYear - 1, 1, 1, 0, 0, 0, $tz)->startOfYear()->utc(),
                \Carbon\Carbon::create($this->selectedYear - 1, 1, 1, 0, 0, 0, $tz)->endOfYear()->utc(),
            ],
            default => [now($tz)->subDay()->startOfDay()->utc(), now($tz)->subDay()->endOfDay()->utc()],
        };

        $row = $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->whereIn('order_status', self::PAID_STATUSES)
            ->select([
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(total_price), 0) as total_revenue'),
                DB::raw('COALESCE(AVG(total_price), 0) as avg_order_value'),
            ])
            ->first();

        return [
            'total_orders'    => (int)   ($row->total_orders    ?? 0),
            'total_revenue'   => (float) ($row->total_revenue   ?? 0),
            'avg_order_value' => (float) ($row->avg_order_value ?? 0),
        ];
    }

    public function getRevenueTimeSeries(): array
    {
        $tz = 'Pacific/Tarawa';
        [$from, $to] = $this->dateRange();

        $query = $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->whereIn('order_status', self::PAID_STATUSES);

        if ($this->period === 'day') {
            $rows = $query
                ->select(DB::raw("HOUR(CONVERT_TZ(created_at,'+00:00','+12:00')) as h"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
                ->groupBy('h')->orderBy('h')->get()->keyBy('h');
            $labels = [];
            $data   = [];
            for ($h = 6; $h <= 22; $h++) {
                $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $data[]   = (float) ($rows[$h]->revenue ?? 0);
            }
            return ['labels' => $labels, 'data' => $data];
        }

        if ($this->period === 'week') {
            $rows  = $query
                ->select(DB::raw("DATE(CONVERT_TZ(created_at,'+00:00','+12:00')) as d"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
                ->groupBy('d')->orderBy('d')->get()->keyBy('d');
            $labels = [];
            $data   = [];
            $start  = now($tz)->copy()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $date     = $start->copy()->addDays($i);
                $labels[] = $date->format('D d M');
                $data[]   = (float) ($rows[$date->toDateString()]->revenue ?? 0);
            }
            return ['labels' => $labels, 'data' => $data];
        }

        if ($this->period === 'month') {
            $rows  = $query
                ->select(DB::raw("DATE(CONVERT_TZ(created_at,'+00:00','+12:00')) as d"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
                ->groupBy('d')->orderBy('d')->get()->keyBy('d');
            $start       = \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz);
            $daysInMonth = $start->daysInMonth;
            $labels = [];
            $data   = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date     = $start->copy()->setDay($d);
                $labels[] = $date->format('d M');
                $data[]   = (float) ($rows[$date->toDateString()]->revenue ?? 0);
            }
            return ['labels' => $labels, 'data' => $data];
        }

        // year
        $rows     = $query
            ->select(DB::raw("MONTH(CONVERT_TZ(created_at,'+00:00','+12:00')) as m"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
            ->groupBy('m')->orderBy('m')->get()->keyBy('m');
        $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = (float) ($rows[$m]->revenue ?? 0);
        }
        return ['labels' => $monthNames, 'data' => $data];
    }

    public function exportCsv(): void
    {
        $summary  = $this->getSummary();
        $payments = $this->getPaymentBreakdown();
        $flavors  = $this->getTopFlavors();
        $expenses = $this->getExpenseSummary();
        $topups   = $this->getTopupSummary();

        $lines   = [];
        $lines[] = 'Sales Report,' . now('Pacific/Tarawa')->format('d M Y');
        $lines[] = '';
        $lines[] = 'SUMMARY';
        $lines[] = 'Total Revenue,A$' . number_format($summary['total_revenue'], 2);
        $lines[] = 'Total Orders,' . $summary['total_orders'];
        $lines[] = 'Avg Order Value,A$' . number_format($summary['avg_order_value'], 2);
        $lines[] = '';
        $lines[] = 'REVENUE BY PAYMENT METHOD';
        $lines[] = 'Method,Orders,Revenue';
        foreach ($payments as $r) {
            $lines[] = $r['payment_method'] . ',' . $r['orders'] . ',A$' . number_format($r['revenue'], 2);
        }
        $lines[] = '';
        $lines[] = 'TOP ITEMS';
        $lines[] = 'Rank,Item,Qty Sold,Revenue';
        foreach ($flavors as $i => $r) {
            $lines[] = ($i + 1) . ',' . $r->name . ',' . $r->qty . ',A$' . number_format($r->revenue, 2);
        }
        $lines[] = '';
        $lines[] = 'EXPENSES BY CATEGORY';
        $lines[] = 'Category,Total';
        foreach ($expenses['rows'] as $r) {
            $lines[] = $r['category'] . ',A$' . number_format($r['total'], 2);
        }
        $lines[] = 'Total,A$' . number_format($expenses['total'], 2);
        $lines[] = '';
        $lines[] = 'WALLET TOP-UPS';
        $lines[] = 'Method,Count,Total';
        foreach ($topups['rows'] as $r) {
            $lines[] = $r['payment_method'] . ',' . $r['count'] . ',A$' . number_format($r['total'], 2);
        }

        $csv      = implode("\n", $lines);
        $filename = 'sales-report-' . $this->period . '-' . now('Pacific/Tarawa')->format('Y-m-d') . '.csv';

        $this->dispatch('download-csv', content: $csv, filename: $filename);
    }

}
