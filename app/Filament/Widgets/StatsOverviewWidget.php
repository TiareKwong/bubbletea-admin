<?php

namespace App\Filament\Widgets;

use App\Models\Flavor;
use App\Models\Order;
use App\Models\Topping;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    // Refresh every 60 seconds so staff see up-to-date counts without manual reload.
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // One query for all order status counts.
        $orderCounts = Order::query()
            ->select([
                DB::raw("SUM(order_status IN ('Payment Verification','Points Verification')) AS needs_attention"),
                DB::raw("SUM(order_status = 'Pending Payment') AS pending_payment"),
                DB::raw("SUM(collected = 1 AND DATE(updated_at) = CURDATE()) AS collected_today"),
            ])
            ->first();

        // One query for menu counts.
        $menuCounts = DB::table('flavors')
            ->select([
                DB::raw("SUM(status = 'Available') AS available_flavors"),
                DB::raw("SUM(status = 'Out of Stock') AS out_of_stock_flavors"),
            ])
            ->first();

        $toppingCounts = DB::table('toppings')
            ->select([
                DB::raw("SUM(status = 'Available') AS available_toppings"),
                DB::raw("SUM(status = 'Out of Stock') AS out_of_stock_toppings"),
            ])
            ->first();

        $needsAttention    = (int) ($orderCounts->needs_attention ?? 0);
        $pendingPayment    = (int) ($orderCounts->pending_payment ?? 0);
        $collectedToday    = (int) ($orderCounts->collected_today ?? 0);
        $availableFlavors  = (int) ($menuCounts->available_flavors ?? 0);
        $outStockFlavors   = (int) ($menuCounts->out_of_stock_flavors ?? 0);
        $availableToppings = (int) ($toppingCounts->available_toppings ?? 0);
        $outStockToppings  = (int) ($toppingCounts->out_of_stock_toppings ?? 0);

        return [
            Stat::make('Needs Attention', $needsAttention)
                ->description($needsAttention > 0 ? 'Tap to verify payments' : 'All payments verified')
                ->descriptionIcon($needsAttention > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($needsAttention > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.orders.index') . '?tab=needs_attention'),

            Stat::make('Pending Payment', $pendingPayment)
                ->description($pendingPayment > 0 ? 'Waiting for customers' : 'None pending')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayment > 0 ? 'info' : 'gray')
                ->url(route('filament.admin.resources.orders.index') . '?tab=pending_payment'),

            Stat::make('Collected Today', $collectedToday)
                ->description('Orders picked up today')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('Available Flavors', $availableFlavors)
                ->description($outStockFlavors > 0 ? $outStockFlavors . ' out of stock' : 'All in stock')
                ->descriptionIcon($outStockFlavors > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($outStockFlavors > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.flavors.index')),

            Stat::make('Available Toppings', $availableToppings)
                ->description($outStockToppings > 0 ? $outStockToppings . ' out of stock' : 'All in stock')
                ->descriptionIcon($outStockToppings > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($outStockToppings > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.toppings.index')),
        ];
    }
}
