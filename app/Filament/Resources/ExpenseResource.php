<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Services\BranchContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?int $navigationSort = 2;

    // Staff can create and edit expenses (to fix mistakes).
    // Only admins can delete.
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool   { return true; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return (bool) auth()->user()?->is_admin; }
    public static function canDeleteAny(): bool                                         { return (bool) auth()->user()?->is_admin; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('description')
                ->label('What was purchased')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('amount')
                ->label('Amount (A$)')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->prefix('A$'),

            Select::make('paid_from')
                ->label('Paid from')
                ->required()
                ->default('cash_box')
                ->options([
                    'cash_box'  => 'Cash Box (deducted from today\'s cash)',
                    'own_money' => 'Own Money (staff paid personally)',
                ])
                ->helperText('Cash Box will reduce the expected cash in Daily Reconciliation.'),

            TextInput::make('purchased_by')
                ->label('Purchased by')
                ->maxLength(100)
                ->placeholder('Leave empty if you bought it yourself')
                ->helperText('Only fill this in if someone else made the purchase.'),

            DatePicker::make('expense_date')
                ->label('Date of purchase')
                ->required()
                ->default(now('Pacific/Tarawa')->toDateString())
                ->maxDate(now('Pacific/Tarawa')->toDateString()),

            Textarea::make('notes')
                ->label('Notes (optional)')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(40),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('purchased_by')
                    ->label('Purchased by')
                    ->default('—'),

                TextColumn::make('created_by')
                    ->label('Entered by'),
            ])
            ->filters([
                Filter::make('expense_date')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->whereDate('expense_date', '<=', $v));
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_admin),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        return app(BranchContext::class)->applyTo($query);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view'   => Pages\ViewExpense::route('/{record}'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
