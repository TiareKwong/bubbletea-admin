<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToppingResource\Pages;
use App\Models\Topping;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Support\ImageUploader;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ToppingResource extends Resource
{
    protected static ?string $model = Topping::class;

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Toppings';

    protected static string|\UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100),

            TextInput::make('price')
                ->label('Price ($)')
                ->required()
                ->numeric()
                ->minValue(0)
                ->step(0.01),

            Placeholder::make('image_preview')
                ->label('Current Image')
                ->content(fn ($record): HtmlString|string =>
                    $record?->image_url
                        ? new HtmlString('<img src="' . e($record->image_url) . '" style="max-height:160px;border-radius:8px;object-fit:contain;">')
                        : '—'
                )
                ->visible(fn ($record): bool => (bool) $record?->image_url),

            FileUpload::make('image_url')
                ->label('Image')
                ->helperText('Max 800×800px, compressed to JPEG automatically. Leave empty to keep existing.')
                ->image()
                ->disk('droplet')
                ->directory('images/toppings')
                ->imagePreviewHeight('160')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->imageResizeTargetWidth('800')
                ->imageResizeTargetHeight('800')
                ->imageResizeMode('contain')
                ->imageResizeUpscale(false)
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => ImageUploader::upload($file, 'images/toppings')),

            Select::make('status')
                ->options([
                    'Available'    => 'Available',
                    'Out of Stock' => 'Out of Stock',
                ])
                ->required()
                ->default('Available'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->height(48)
                    ->width(48)
                    ->defaultImageUrl(asset('images/placeholder.png')),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Available'    => 'success',
                        'Out of Stock' => 'danger',
                        default        => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Available'    => 'Available',
                        'Out of Stock' => 'Out of Stock',
                    ]),
            ])
            ->actions([
                Action::make('toggleAvailability')
                    ->label(fn (Topping $record): string => $record->status === 'Available' ? 'Mark Out of Stock' : 'Mark Available')
                    ->icon(fn (Topping $record): string => $record->status === 'Available' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Topping $record): string => $record->status === 'Available' ? 'danger' : 'success')
                    ->visible(fn (): bool => (bool) auth()->user()?->is_admin)
                    ->action(function (Topping $record): void {
                        abort_unless(auth()->user()?->is_admin, 403);
                        $record->status = $record->status === 'Available' ? 'Out of Stock' : 'Available';
                        $record->save();
                    })
                    ->requiresConfirmation(false),

                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListToppings::route('/'),
            'create' => Pages\CreateTopping::route('/create'),
            'edit'   => Pages\EditTopping::route('/{record}/edit'),
        ];
    }
}
