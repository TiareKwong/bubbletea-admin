<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FlavorResource\Pages;
use App\Models\Flavor;
use App\Models\FlavorCategory;
use App\Models\ProductType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Support\ImageUploader;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FlavorResource extends Resource
{
    protected static ?string $model = Flavor::class;

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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static string|\UnitEnum|null $navigationGroup = 'Menu';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->options(fn () => ProductType::orderBy('name')
                    ->pluck('name', 'name')
                    ->mapWithKeys(fn ($v, $k) => [$k => ucfirst(str_replace('_', ' ', $v))])
                    ->toArray())
                ->default('drink')
                ->required()
                ->live()
                ->columnSpanFull()
                ->helperText('Manage types under Menu → Product Types. "drink" = full options, "ice_cream" = toppings only, everything else = simple add to cart. Only drink and ice_cream items earn loyalty points.'),

            Select::make('category')
                ->options(fn () => FlavorCategory::orderBy('name')->pluck('name', 'name')->toArray())
                ->placeholder('Select a category')
                ->nullable(),

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
                ->directory('images')
                ->imagePreviewHeight('160')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                ->imageResizeTargetWidth('800')
                ->imageResizeTargetHeight('800')
                ->imageResizeMode('contain')
                ->imageResizeUpscale(false)
                ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => ImageUploader::upload($file, 'images')),

            \Filament\Schemas\Components\Section::make('Small')
                ->columns(2)
                ->visible(fn (Get $get): bool => $get('type') === 'drink')
                ->schema([
                    TextInput::make('small_price')
                        ->label('Price ($)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->placeholder('Leave blank if not available'),

                    TextInput::make('small_ml')
                        ->label('Size (ml)')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('e.g. 360'),
                ]),

            \Filament\Schemas\Components\Section::make(fn (Get $get): string => $get('type') === 'drink' ? 'Regular' : 'Price')
                ->columns(2)
                ->schema([
                    TextInput::make('regular_price')
                        ->label('Price ($)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->placeholder('Leave blank if not available')
                        ->rules([
                            fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                $type    = $get('type') ?? 'drink';
                                $small   = $get('small_price');
                                $regular = $get('regular_price');
                                $large   = $get('large_price');
                                if ($type !== 'drink' && ($regular === null || $regular === '')) {
                                    $fail('A price is required.');
                                } elseif ($type === 'drink' &&
                                    ($small === null || $small === '') &&
                                    ($regular === null || $regular === '') &&
                                    ($large === null || $large === '')) {
                                    $fail('At least one size price must be filled in.');
                                }
                            },
                        ]),

                    TextInput::make('regular_ml')
                        ->label('Size (ml)')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('e.g. 500')
                        ->visible(fn (Get $get): bool => $get('type') === 'drink'),
                ]),

            \Filament\Schemas\Components\Section::make('Large')
                ->columns(2)
                ->visible(fn (Get $get): bool => $get('type') === 'drink')
                ->schema([
                    TextInput::make('large_price')
                        ->label('Price ($)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->placeholder('Leave blank if not available'),

                    TextInput::make('large_ml')
                        ->label('Size (ml)')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('e.g. 700'),
                ]),

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

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'drink'     => 'info',
                        'ice_cream' => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string =>
                        ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('category')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('small_price')
                    ->label('Small')
                    ->money('AUD')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('regular_price')
                    ->label('Regular')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('large_price')
                    ->label('Large')
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
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(fn () => ProductType::orderBy('name')
                        ->pluck('name', 'name')
                        ->mapWithKeys(fn ($v, $k) => [$k => ucfirst(str_replace('_', ' ', $v))])
                        ->toArray())
                    ->placeholder('All Types'),

                SelectFilter::make('status')
                    ->options([
                        'Available'    => 'Available',
                        'Out of Stock' => 'Out of Stock',
                    ]),

                SelectFilter::make('category')
                    ->options(fn () => FlavorCategory::orderBy('name')->pluck('name', 'name')->toArray())
                    ->placeholder('All Categories'),
            ])
            ->actions([
                Action::make('toggleAvailability')
                    ->label(fn (Flavor $record): string => $record->status === 'Available' ? 'Mark Out of Stock' : 'Mark Available')
                    ->icon(fn (Flavor $record): string => $record->status === 'Available' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Flavor $record): string => $record->status === 'Available' ? 'danger' : 'success')
                    ->visible(fn (): bool => (bool) auth()->user()?->is_admin)
                    ->action(function (Flavor $record): void {
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
            'index'  => Pages\ListFlavors::route('/'),
            'create' => Pages\CreateFlavor::route('/create'),
            'edit'   => Pages\EditFlavor::route('/{record}/edit'),
        ];
    }
}
