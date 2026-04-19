<?php

namespace App\Filament\Resources\FlavorResource\Pages;

use App\Filament\Resources\FlavorResource;
use App\Models\ProductType;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFlavors extends ListRecords
{
    protected static string $resource = FlavorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),
        ];

        ProductType::orderBy('name')->each(function (ProductType $type) use (&$tabs) {
            $label = ucfirst(str_replace('_', ' ', $type->name));
            $tabs[$type->name] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->name));
        });

        return $tabs;
    }
}
