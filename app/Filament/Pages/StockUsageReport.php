<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class StockUsageReport extends Page
{
    protected string $view = 'filament.pages.stock-usage-report';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationLabel = 'Stock Usage';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    public string $range = '30';

    public function getStockUsageProperty(): \Illuminate\Support\Collection
    {
        $from = match ($this->range) {
            '90'   => now('Pacific/Tarawa')->subDays(90)->startOfDay(),
            'all'  => null,
            default => now('Pacific/Tarawa')->subDays(30)->startOfDay(),
        };

        return DB::table('stock_items as si')
            ->leftJoin('stock_logs as sl', function ($join) use ($from) {
                $join->on('sl.stock_item_id', '=', 'si.id')
                     ->where('sl.type', 'dispatched');
                if ($from) {
                    $join->where('sl.logged_at', '>=', $from);
                }
            })
            ->select(
                'si.name',
                'si.category',
                'si.unit',
                'si.current_quantity',
                DB::raw('COUNT(sl.id) as times_dispatched'),
                DB::raw('COALESCE(SUM(sl.quantity), 0) as total_dispatched')
            )
            ->groupBy('si.id', 'si.name', 'si.category', 'si.unit', 'si.current_quantity')
            ->orderBy('times_dispatched', 'asc')
            ->orderBy('si.name', 'asc')
            ->get();
    }
}
