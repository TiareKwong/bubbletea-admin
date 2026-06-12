<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProductSalesReport extends Page
{
    protected string $view = 'filament.pages.product-sales-report';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Product Sales';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    public string $range = '30';

    public function getProductSalesProperty(): \Illuminate\Support\Collection
    {
        $from = match ($this->range) {
            '90'   => now('Pacific/Tarawa')->subDays(90)->startOfDay(),
            'all'  => null,
            default => now('Pacific/Tarawa')->subDays(30)->startOfDay(),
        };

        $query = DB::table('flavors as f')
            ->leftJoin('order_items as oi', function ($join) use ($from) {
                $join->on('oi.flavor_id', '=', 'f.id');
                if ($from) {
                    $join->whereExists(function ($sub) use ($from) {
                        $sub->from('orders')
                            ->whereColumn('orders.id', 'oi.order_id')
                            ->where('orders.created_at', '>=', $from);
                    });
                }
            })
            ->select(
                'f.name',
                'f.category',
                'f.status',
                DB::raw('COUNT(oi.id) as times_ordered'),
                DB::raw('COALESCE(SUM(oi.quantity), 0) as total_qty'),
                DB::raw('COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue')
            )
            ->groupBy('f.id', 'f.name', 'f.category', 'f.status')
            ->orderBy('total_qty', 'asc')
            ->orderBy('f.name', 'asc');

        return $query->get();
    }
}
