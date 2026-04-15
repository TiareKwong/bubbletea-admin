<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Reward;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustPoints')
                ->label('Adjust Points')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->is_admin)
                ->form([
                    Select::make('type')
                        ->label('Adjustment Type')
                        ->options([
                            'add'    => 'Add Points',
                            'deduct' => 'Deduct Points',
                        ])
                        ->required()
                        ->default('add'),

                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(auth()->user()?->is_admin, 403);

                    $reward = Reward::firstOrCreate(
                        ['user_id' => $this->record->id],
                        ['points'  => 0]
                    );

                    $reward->points = max(0, $data['type'] === 'add'
                        ? $reward->points + (int) $data['amount']
                        : $reward->points - (int) $data['amount']
                    );

                    $reward->save();

                    $this->refreshFormData([]);
                }),
        ];
    }
}
