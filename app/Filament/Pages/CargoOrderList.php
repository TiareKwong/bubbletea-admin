<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class CargoOrderList extends Page
{
    protected string $view = 'filament.pages.cargo-order-list';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Cargo Order List';

    protected static string|\UnitEnum|null $navigationGroup = 'Stock';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    public array $quantities = [];

    public function mount(): void
    {
        foreach ($this->getItems() as $item) {
            $this->quantities[$item->id] = '';
        }
    }

    public function getItems(): \Illuminate\Support\Collection
    {
        return DB::table('stock_items')
            ->whereRaw('current_quantity <= sea_reorder_quantity')
            ->orderByRaw('(current_quantity / NULLIF(sea_reorder_quantity, 0)) ASC')
            ->orderBy('name')
            ->get();
    }

    public function getItemsProperty(): \Illuminate\Support\Collection
    {
        return $this->getItems();
    }

    public function printList(): void
    {
        $this->dispatch('print-order-list');
    }
}
