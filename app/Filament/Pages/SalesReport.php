<?php

namespace App\Filament\Pages;

use App\Models\CupTopup;
use App\Models\CupType;
use App\Models\DailyCupLog;
use App\Models\DailyFloat;
use App\Models\Expense;
use App\Models\Order;
use App\Models\WalletTopupRequest;
use App\Services\BranchContext;
use Filament\Notifications\Notification;
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

    public string  $period        = 'day';
    public int     $selectedMonth;
    public int     $selectedYear;
    public string  $customFrom   = '';
    public string  $customTo     = '';

    public string  $floatAmount    = '';
    public ?string $floatSetBy     = null;
    public ?string $floatUpdatedAt = null;

    public function mount(): void
    {
        $this->selectedMonth = (int) now('Pacific/Tarawa')->month;
        $this->selectedYear  = (int) now('Pacific/Tarawa')->year;
        $this->customFrom    = now('Pacific/Tarawa')->toDateString();
        $this->customTo      = now('Pacific/Tarawa')->toDateString();

        $user = auth()->user();
        if (! $user?->is_admin && in_array($this->period, ['month', 'year'])) {
            $this->period = 'day';
        }
        if (! $user?->is_admin && ! $user?->is_super_staff && $this->period === 'custom') {
            $this->period = 'day';
        }

        $this->loadFloat();
        $this->loadCupLogs();
    }

    private function loadFloat(): void
    {
        $today    = now('Pacific/Tarawa')->toDateString();
        $existing = DailyFloat::where('date', $today)
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
        $today    = now('Pacific/Tarawa')->toDateString();

        DailyFloat::updateOrCreate(
            ['branch_id' => $branchId, 'date' => $today],
            ['amount' => $amount, 'set_by' => auth()->user()->getFilamentName()]
        );

        $this->floatAmount    = number_format($amount, 2);
        $this->floatSetBy     = auth()->user()->getFilamentName();
        $this->floatUpdatedAt = now('Pacific/Tarawa')->format('d M Y, h:i A');

        Notification::make()
            ->title('Float saved — A$' . number_format($amount, 2))
            ->success()
            ->send();
    }

    public function setCustomRange(string $from, string $to): void
    {
        $this->customFrom = $from;
        $this->customTo   = $to;
    }

    public function updatedCustomFrom(): void
    {
        if ($this->customFrom > $this->customTo) {
            $this->customTo = $this->customFrom;
        }
    }

    public function updatedCustomTo(): void
    {
        if ($this->customTo < $this->customFrom) {
            $this->customFrom = $this->customTo;
        }
    }

    // ── Cup tracking ─────────────────────────────────────────────────

    public array $cupOpening  = [];  // [cup_type_id => value]
    public array $cupClosing  = [];  // [cup_type_id => value]
    public array $cupTopupQty = [];  // [cup_type_id => pending input]

    private function activeCupTypesForBranch(): \Illuminate\Database\Eloquent\Collection
    {
        $branchId = $this->branchId();
        return CupType::active()->with('branches')->get()->filter(
            fn ($ct) => $ct->isAvailableAt($branchId)
        );
    }

    private function loadCupLogs(): void
    {
        $today    = now('Pacific/Tarawa')->toDateString();
        $branchId = $this->branchId();

        $logs = DailyCupLog::where('date', $today)
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('cup_type_id');

        foreach ($this->activeCupTypesForBranch() as $ct) {
            $log = $logs->get($ct->id);
            $this->cupOpening[$ct->id]  = $log?->opening !== null ? (string) $log->opening : '';
            $this->cupClosing[$ct->id]  = $log?->closing !== null ? (string) $log->closing : '';
            $this->cupTopupQty[$ct->id] = '';
        }
    }

    public function saveCupLog(int $cupTypeId, string $field): void
    {
        $today    = now('Pacific/Tarawa')->toDateString();
        $branchId = $this->branchId();
        $allowed  = ['opening', 'closing'];

        if (! in_array($field, $allowed)) return;

        $prop  = $field === 'opening' ? 'cupOpening' : 'cupClosing';
        $value = max(0, (int) ($this->{$prop}[$cupTypeId] ?? 0));

        $byField = $field === 'opening' ? 'opening_by' : 'closing_by';

        DailyCupLog::updateOrCreate(
            ['date' => $today, 'branch_id' => $branchId, 'cup_type_id' => $cupTypeId],
            [$field => $value, $byField => auth()->user()->getFilamentName(), 'logged_by' => auth()->user()->getFilamentName()]
        );

        $this->{$prop}[$cupTypeId] = (string) $value;

        Notification::make()->title('Cup log saved')->success()->send();
    }

    public function addCupTopup(int $cupTypeId): void
    {
        $qty = max(1, (int) ($this->cupTopupQty[$cupTypeId] ?? 0));

        if ($qty === 0) {
            Notification::make()->title('Enter a quantity first')->warning()->send();
            return;
        }

        CupTopup::create([
            'date'       => now('Pacific/Tarawa')->toDateString(),
            'branch_id'  => $this->branchId(),
            'cup_type_id'=> $cupTypeId,
            'quantity'   => $qty,
            'logged_by'  => auth()->user()->getFilamentName(),
            'logged_at'  => now(),
        ]);

        $this->cupTopupQty[$cupTypeId] = '';

        Notification::make()->title("{$qty} cups added as top-up")->success()->send();
    }

    public function deleteCupTopup(int $topupId): void
    {
        CupTopup::where('id', $topupId)
            ->where('date', now('Pacific/Tarawa')->toDateString())
            ->delete();

        Notification::make()->title('Top-up entry removed')->success()->send();
    }

    public function getCupTrackingData(): array
    {
        $today    = now('Pacific/Tarawa')->toDateString();
        $branchId = $this->branchId();

        $logs   = DailyCupLog::where('date', $today)->where('branch_id', $branchId)->get()->keyBy('cup_type_id');
        $topups = CupTopup::where('date', $today)->where('branch_id', $branchId)->get()->groupBy('cup_type_id');

        $result = [];
        foreach ($this->activeCupTypesForBranch() as $ct) {
            $log        = $logs->get($ct->id);
            $typeTopups = $topups->get($ct->id, collect());
            $topupTotal = $typeTopups->sum('quantity');

            $opening = $log?->opening ?? null;
            $closing = $log?->closing ?? null;
            $used    = ($opening !== null && $closing !== null)
                ? max(0, $opening + $topupTotal - $closing)
                : null;

            $result[] = [
                'id'          => $ct->id,
                'name'        => $ct->name,
                'opening'     => $opening,
                'opening_by'  => $log?->opening_by,
                'closing'     => $closing,
                'closing_by'  => $log?->closing_by,
                'topup_total' => $topupTotal,
                'topups'      => $typeTopups->values()->toArray(),
                'used'        => $used,
            ];
        }

        return ['cups' => $result];
    }

    public function getCupSummary(): array
    {
        if ($this->period === 'day') return [];

        $tz = 'Pacific/Tarawa';
        [$dateFrom, $dateTo] = match ($this->period) {
            'week'   => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month'  => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'   => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            'custom' => [$this->customFrom ?: now($tz)->toDateString(), $this->customTo ?: now($tz)->toDateString()],
            default  => [now($tz)->toDateString(), now($tz)->toDateString()],
        };

        $branchId = $this->branchId();
        $days     = (int) \Carbon\Carbon::parse($dateFrom)->diffInDays(\Carbon\Carbon::parse($dateTo)) + 1;

        $logs = DailyCupLog::whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with('cupType')
            ->get();

        $topups = CupTopup::whereBetween('date', [$dateFrom, $dateTo])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->groupBy('cup_type_id');

        $result = [];
        foreach (CupType::active()->with('branches')->get()->filter(fn ($ct) => $ct->isAvailableAt($branchId)) as $ct) {
            $ctLogs     = $logs->where('cup_type_id', $ct->id);
            $topupTotal = ($topups->get($ct->id) ?? collect())->sum('quantity');
            $daysLogged = $ctLogs->filter(fn ($l) => $l->opening !== null && $l->closing !== null)->count();

            $totalUsed  = $ctLogs->sum(function ($log) use ($topups, $ct) {
                if ($log->opening === null || $log->closing === null) return 0;
                $dayTopups = ($topups->get($ct->id) ?? collect())
                    ->filter(fn ($t) => $t->date->toDateString() === $log->date->toDateString())
                    ->sum('quantity');
                return max(0, $log->opening + $dayTopups - $log->closing);
            });

            $result[] = [
                'name'        => $ct->name,
                'days_logged' => $daysLogged,
                'days_total'  => $days,
                'total_used'  => $totalUsed,
                'avg_per_day' => $daysLogged > 0 ? round($totalUsed / $daysLogged, 1) : null,
                'topup_total' => $topupTotal,
            ];
        }

        return $result;
    }

    /** Whether the current user can see Month/Year tabs. */
    public function canViewExtendedPeriods(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    /** Whether the current user can see the Custom Range tab. */
    public function canViewCustomRange(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    /** Called when Livewire sets the period property — guard against staff selecting restricted periods. */
    public function updatedPeriod(string $value): void
    {
        $user = auth()->user();
        if (! $user?->is_admin && in_array($value, ['month', 'year'])) {
            $this->period = 'day';
        }
        if (! $user?->is_admin && ! $user?->is_super_staff && $value === 'custom') {
            $this->period = 'day';
        }
    }

    public function previousYear(): void { $this->selectedYear--; }
    public function nextYear(): void     { $this->selectedYear++; }

    public function getPeriodLabel(): string
    {
        $tz = 'Pacific/Tarawa';

        return match ($this->period) {
            'day'    => 'Today - ' . now($tz)->format('d M Y'),
            'week'   => 'This Week (' . now($tz)->copy()->startOfWeek()->format('d M') . ' - ' . now($tz)->copy()->endOfWeek()->format('d M Y') . ')',
            'month'  => \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth)->format('F Y'),
            'year'   => (string) $this->selectedYear,
            'custom' => $this->customFrom === $this->customTo
                ? \Carbon\Carbon::parse($this->customFrom)->format('d M Y')
                : \Carbon\Carbon::parse($this->customFrom)->format('d M Y') . ' – ' . \Carbon\Carbon::parse($this->customTo)->format('d M Y'),
            default  => '',
        };
    }

    protected function dateRange(): array
    {
        // Compute boundaries in Tarawa time, then convert to UTC so they match
        // the UTC-stored created_at values in the database.
        $tz = 'Pacific/Tarawa';

        return match ($this->period) {
            'day'    => [
                now($tz)->startOfDay()->utc(),
                now($tz)->endOfDay()->utc(),
            ],
            'week'   => [
                now($tz)->copy()->startOfWeek()->utc(),
                now($tz)->copy()->endOfWeek()->utc(),
            ],
            'month'  => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->utc(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->utc(),
            ],
            'year'   => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->utc(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->utc(),
            ],
            'custom' => [
                \Carbon\Carbon::parse($this->customFrom ?: now($tz)->toDateString(), $tz)->startOfDay()->utc(),
                \Carbon\Carbon::parse($this->customTo   ?: now($tz)->toDateString(), $tz)->endOfDay()->utc(),
            ],
            default  => [
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
            'day'    => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'   => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month'  => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'   => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            'custom' => [$this->customFrom ?: now($tz)->toDateString(), $this->customTo ?: now($tz)->toDateString()],
            default  => [now($tz)->toDateString(), now($tz)->toDateString()],
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
            'day'    => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'   => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month'  => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'   => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            'custom' => [$this->customFrom ?: now($tz)->toDateString(), $this->customTo ?: now($tz)->toDateString()],
            default  => [now($tz)->toDateString(), now($tz)->toDateString()],
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
            'day'    => [now($tz)->toDateString(), now($tz)->toDateString()],
            'week'   => [now($tz)->copy()->startOfWeek()->toDateString(), now($tz)->copy()->endOfWeek()->toDateString()],
            'month'  => [
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->startOfMonth()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->endOfMonth()->toDateString(),
            ],
            'year'   => [
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->startOfYear()->toDateString(),
                \Carbon\Carbon::create($this->selectedYear, 1, 1, 0, 0, 0, $tz)->endOfYear()->toDateString(),
            ],
            'custom' => [$this->customFrom ?: now($tz)->toDateString(), $this->customTo ?: now($tz)->toDateString()],
            default  => [now($tz)->toDateString(), now($tz)->toDateString()],
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

        if ($this->period === 'custom') {
            $cfrom = \Carbon\Carbon::parse($this->customFrom ?: now($tz)->toDateString(), $tz);
            $cto   = \Carbon\Carbon::parse($this->customTo   ?: now($tz)->toDateString(), $tz);
            $days  = (int) $cfrom->diffInDays($cto) + 1;
            $from  = $cfrom->copy()->subDays($days)->startOfDay()->utc();
            $to    = $cfrom->copy()->subDay()->endOfDay()->utc();
        } else {
            [$from, $to] = match ($this->period) {
                'day'   => [now($tz)->subDay()->startOfDay()->utc(), now($tz)->subDay()->endOfDay()->utc()],
                'week'  => [now($tz)->copy()->subWeek()->startOfWeek()->utc(), now($tz)->copy()->subWeek()->endOfWeek()->utc()],
                'month' => [
                    \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->subMonthNoOverflow()->startOfMonth()->utc(),
                    \Carbon\Carbon::create($this->selectedYear, $this->selectedMonth, 1, 0, 0, 0, $tz)->subMonthNoOverflow()->endOfMonth()->utc(),
                ],
                'year'  => [
                    \Carbon\Carbon::create($this->selectedYear - 1, 1, 1, 0, 0, 0, $tz)->startOfYear()->utc(),
                    \Carbon\Carbon::create($this->selectedYear - 1, 1, 1, 0, 0, 0, $tz)->endOfYear()->utc(),
                ],
                default => [now($tz)->subDay()->startOfDay()->utc(), now($tz)->subDay()->endOfDay()->utc()],
            };
        }

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

        if ($this->period === 'custom') {
            $fromDate = \Carbon\Carbon::parse($this->customFrom ?: now($tz)->toDateString(), $tz);
            $toDate   = \Carbon\Carbon::parse($this->customTo   ?: now($tz)->toDateString(), $tz);
            $days     = (int) $fromDate->diffInDays($toDate) + 1;

            if ($days <= 1) {
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

            if ($days <= 90) {
                $rows   = $query
                    ->select(DB::raw("DATE(CONVERT_TZ(created_at,'+00:00','+12:00')) as d"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
                    ->groupBy('d')->orderBy('d')->get()->keyBy('d');
                $labels = [];
                $data   = [];
                for ($i = 0; $i < $days; $i++) {
                    $date     = $fromDate->copy()->addDays($i);
                    $labels[] = $date->format('d M');
                    $data[]   = (float) ($rows[$date->toDateString()]->revenue ?? 0);
                }
                return ['labels' => $labels, 'data' => $data];
            }

            // > 90 days — group by month
            $rows   = $query
                ->select(DB::raw("DATE_FORMAT(CONVERT_TZ(created_at,'+00:00','+12:00'),'%Y-%m') as ym"), DB::raw('COALESCE(SUM(total_price),0) as revenue'))
                ->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');
            $labels = [];
            $data   = [];
            $cursor = $fromDate->copy()->startOfMonth();
            while ($cursor->lte($toDate)) {
                $key      = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $data[]   = (float) ($rows[$key]->revenue ?? 0);
                $cursor->addMonth();
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

    public function getSalesByHour(): array
    {
        if ($this->period !== 'custom') {
            return ['labels' => [], 'orders' => [], 'revenue' => []];
        }

        [$from, $to] = $this->dateRange();

        $rows = $this->scopeOrders(Order::whereBetween('created_at', [$from, $to]))
            ->whereIn('order_status', self::PAID_STATUSES)
            ->selectRaw("HOUR(CONVERT_TZ(created_at, '+00:00', '+12:00')) as h, COUNT(*) as orders, COALESCE(SUM(total_price), 0) as revenue")
            ->groupBy('h')
            ->orderBy('h')
            ->get()
            ->keyBy('h');

        $labels  = [];
        $orders  = [];
        $revenue = [];

        for ($h = 6; $h <= 22; $h++) {
            $ampm      = $h < 12 ? 'AM' : 'PM';
            $display   = ($h % 12 === 0 ? 12 : $h % 12) . ' ' . $ampm;
            $labels[]  = $display;
            $orders[]  = (int)   ($rows[$h]->orders  ?? 0);
            $revenue[] = (float) ($rows[$h]->revenue ?? 0);
        }

        return ['labels' => $labels, 'orders' => $orders, 'revenue' => $revenue];
    }

    public function getDailyBreakdown(): array
    {
        if ($this->period !== 'custom') {
            return [];
        }

        $tz       = 'Pacific/Tarawa';
        [$from, $to] = $this->dateRange();
        $fromDate = \Carbon\Carbon::parse($this->customFrom ?: now($tz)->toDateString(), $tz);
        $toDate   = \Carbon\Carbon::parse($this->customTo   ?: now($tz)->toDateString(), $tz);
        $days     = (int) $fromDate->diffInDays($toDate) + 1;
        $branchId = $this->branchId();

        // Reconciled cash per day per branch from cash_reconciliations
        $reconQ = DB::table('cash_reconciliations')
            ->leftJoin('branches', 'cash_reconciliations.branch_id', '=', 'branches.id')
            ->whereBetween('cash_reconciliations.reconciliation_date', [
                $fromDate->toDateString(),
                $toDate->toDateString(),
            ])
            ->where('cash_reconciliations.payment_method', 'Cash')
            ->select(
                DB::raw('COALESCE(branches.name, "No Branch") as branch_name'),
                'cash_reconciliations.branch_id',
                DB::raw('DATE(cash_reconciliations.reconciliation_date) as day'),
                DB::raw('COALESCE(SUM(cash_reconciliations.actual_cash), 0) as reconciled')
            )
            ->groupBy('branch_name', 'cash_reconciliations.branch_id', 'day')
            ->orderBy('branch_name')
            ->orderBy('day');

        if ($branchId) {
            $reconQ->where('cash_reconciliations.branch_id', $branchId);
        }

        $reconRows = $reconQ->get()->groupBy('branch_name');

        // Daily float per day per branch_id
        $floatQ = DB::table('daily_floats')
            ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()]);
        if ($branchId) {
            $floatQ->where('branch_id', $branchId);
        }
        // Map: "branch_id|date" => float amount
        $floatMap = $floatQ->get()->mapWithKeys(fn ($r) => [$r->branch_id . '|' . $r->date => (float) $r->amount])->toArray();

        // Build the full date list for the range
        $allDates = [];
        for ($i = 0; $i < $days; $i++) {
            $allDates[] = $fromDate->copy()->addDays($i)->toDateString();
        }

        $result = [];
        foreach ($reconRows as $branchName => $branchRows) {
            $firstRow      = $branchRows->first();
            $thisBranchId  = $firstRow->branch_id;
            $reconMap      = $branchRows->pluck('reconciled', 'day')->toArray();
            $total         = 0;
            $dayList       = [];

            foreach ($allDates as $date) {
                if (! isset($reconMap[$date])) {
                    $dayList[] = ['date' => $date, 'actual' => null, 'float' => null, 'reconciled' => null];
                    continue;
                }
                $floatAmt   = $floatMap[$thisBranchId . '|' . $date] ?? 0.0;
                $actual     = (float) $reconMap[$date];
                $reconciled = max(0.0, $actual - $floatAmt);
                $dayList[]  = ['date' => $date, 'actual' => $actual, 'float' => $floatAmt, 'reconciled' => $reconciled];
                $total     += $reconciled;
            }

            $result[] = [
                'branch'  => $branchName,
                'days'    => $dayList,
                'total'   => $total,
            ];
        }

        return $result;
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
