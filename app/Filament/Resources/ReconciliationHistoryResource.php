<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReconciliationHistoryResource\Pages;
use App\Models\CashReconciliation;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReconciliationHistoryResource extends Resource
{
    protected static ?string $model = CashReconciliation::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Reconciliation History';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Reconciliation';

    public static function canCreate(): bool   { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool   { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return (bool) auth()->user()?->is_admin; }
    public static function canDeleteAny(): bool { return (bool) auth()->user()?->is_admin; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reconciliation_date', 'desc')
            ->columns([
                TextColumn::make('reconciliation_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash'          => 'warning',
                        'EFTPOS'        => 'info',
                        'Bank Transfer' => 'primary',
                        default         => 'gray',
                    }),

                TextColumn::make('expected_cash')
                    ->label('Expected')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('actual_cash')
                    ->label('Actual')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('difference')
                    ->label('Difference')
                    ->formatStateUsing(fn ($state): string =>
                        ($state >= 0 ? '+' : '-') . 'A$' . number_format(abs($state), 2)
                    )
                    ->color(fn ($state): string =>
                        $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')
                    )
                    ->sortable(),

                TextColumn::make('submitted_by')
                    ->label('Submitted by'),

                TextColumn::make('submitted_at')
                    ->label('Submitted at')
                    ->dateTime('d M Y, h:i A')
                    ->timezone(config('app.timezone'))
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Method')
                    ->options([
                        'Cash'          => 'Cash',
                        'EFTPOS'        => 'EFTPOS',
                        'Bank Transfer' => 'Bank Transfer',
                    ]),

                Filter::make('reconciliation_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q, $v) => $q->whereDate('reconciliation_date', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('reconciliation_date', '<=', $v));
                    }),

                Filter::make('discrepancies')
                    ->label('Discrepancies only')
                    ->query(fn (Builder $query) => $query->where('difference', '!=', 0))
                    ->toggle(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReconciliationHistory::route('/'),
        ];
    }
}
