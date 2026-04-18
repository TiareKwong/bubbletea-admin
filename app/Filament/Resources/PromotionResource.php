<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Flavor;
use App\Models\Promotion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Support\ImageUploader;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
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

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Promotions';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            // ── Basic Info ────────────────────────────────────────────────────
            Section::make('Promotion Details')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Select::make('status')
                        ->options([
                            'Active'   => 'Active',
                            'Inactive' => 'Inactive',
                        ])
                        ->required()
                        ->default('Active'),

                    Textarea::make('description')
                        ->required()
                        ->columnSpanFull(),

                    DatePicker::make('valid_from')
                        ->required()
                        ->native(false),

                    DatePicker::make('valid_until')
                        ->required()
                        ->native(false)
                        ->afterOrEqual('valid_from'),
                ]),

            // ── Promotion Rule ────────────────────────────────────────────────
            Section::make('Promotion Rule')
                ->description('Define how the discount works and which flavors it applies to.')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Discount Type')
                        ->options([
                            'buy_x_get_y_free' => 'Buy X Get Y Free',
                            'percent_off'      => 'Percentage Discount',
                        ])
                        ->required()
                        ->default('buy_x_get_y_free')
                        ->live()
                        ->helperText('Buy X Get Y Free: e.g. Buy 2 get 1 free. Percentage: e.g. 10% off each cup.'),

                    Select::make('applies_to')
                        ->label('Applies To')
                        ->options([
                            'all'      => 'All Flavors',
                            'category' => 'Specific Category',
                        ])
                        ->required()
                        ->default('all')
                        ->live(),

                    TextInput::make('buy_quantity')
                        ->label('Buy Quantity')
                        ->numeric()
                        ->minValue(1)
                        ->integer()
                        ->helperText('How many the customer must buy (e.g. 2 for "Buy 2 Get 1 Free")')
                        ->hidden(fn (Get $get): bool => $get('type') !== 'buy_x_get_y_free')
                        ->required(fn (Get $get): bool => $get('type') === 'buy_x_get_y_free'),

                    TextInput::make('free_quantity')
                        ->label('Free Quantity')
                        ->numeric()
                        ->minValue(1)
                        ->integer()
                        ->helperText('How many they get free (e.g. 1 for "Buy 2 Get 1 Free")')
                        ->hidden(fn (Get $get): bool => $get('type') !== 'buy_x_get_y_free')
                        ->required(fn (Get $get): bool => $get('type') === 'buy_x_get_y_free'),

                    Select::make('free_item_size')
                        ->label('Free Drink Size')
                        ->options([
                            'Any'     => 'Any Size',
                            'Small'   => 'Small Only',
                            'Regular' => 'Regular Only',
                            'Large'   => 'Large Only',
                        ])
                        ->default('Any')
                        ->hidden(fn (Get $get): bool => $get('type') !== 'buy_x_get_y_free')
                        ->helperText('What size drink does the customer get for free?'),

                    Select::make('free_item_category')
                        ->label('Free Drink Category')
                        ->options(fn (): array => Flavor::whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray()
                        )
                        ->placeholder('Any Category')
                        ->hidden(fn (Get $get): bool => $get('type') !== 'buy_x_get_y_free')
                        ->helperText('Which flavor category must the free drink be from? Leave blank for any.'),

                    TextInput::make('discount_percent')
                        ->label('Discount Percentage')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('%')
                        ->helperText('e.g. 10 for 10% off each qualifying cup')
                        ->hidden(fn (Get $get): bool => $get('type') !== 'percent_off')
                        ->required(fn (Get $get): bool => $get('type') === 'percent_off'),

                    Select::make('target_category')
                        ->label('Flavor Category')
                        ->options(fn (): array => Flavor::whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray()
                        )
                        ->hidden(fn (Get $get): bool => $get('applies_to') !== 'category')
                        ->required(fn (Get $get): bool => $get('applies_to') === 'category')
                        ->helperText('Only flavors in this category will count towards the promotion.'),
                ]),

            // ── Image ─────────────────────────────────────────────────────────
            Section::make('Image')
                ->schema([
                    Placeholder::make('image_preview')
                        ->label('Current Image')
                        ->content(fn ($record): HtmlString|string =>
                            $record?->image_url
                                ? new HtmlString('<img src="' . e($record->image_url) . '" style="max-height:160px;border-radius:8px;object-fit:contain;">')
                                : '—'
                        )
                        ->visible(fn ($record): bool => (bool) $record?->image_url),

                    FileUpload::make('image_url')
                        ->label('Upload Image')
                        ->helperText('Max 800×800px, compressed to JPEG automatically. Leave empty to keep existing.')
                        ->image()
                        ->disk('droplet')
                        ->directory('images/promotions')
                        ->imagePreviewHeight('160')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                        ->imageResizeTargetWidth('800')
                        ->imageResizeTargetHeight('800')
                        ->imageResizeMode('contain')
                        ->imageResizeUpscale(false)
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => ImageUploader::upload($file, 'images/promotions')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->height(48)
                    ->width(80)
                    ->defaultImageUrl(asset('images/placeholder.png')),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active'   => 'success',
                        'Inactive' => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('valid_from')
                    ->label('From')
                    ->date()
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Until')
                    ->date()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('valid_from', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Active'   => 'Active',
                        'Inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Action::make('toggleStatus')
                    ->label(fn (Promotion $record): string => $record->status === 'Active' ? 'Deactivate' : 'Activate')
                    ->icon(fn (Promotion $record): string => $record->status === 'Active' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Promotion $record): string => $record->status === 'Active' ? 'gray' : 'success')
                    ->visible(fn (): bool => (bool) auth()->user()?->is_admin)
                    ->action(function (Promotion $record): void {
                        abort_unless(auth()->user()?->is_admin, 403);
                        $newStatus = $record->status === 'Active' ? 'Inactive' : 'Active';
                        $record->status = $newStatus;
                        $record->save();

                        if ($newStatus === 'Active') {
                            \App\Models\Promotion::where('id', '!=', $record->id)
                                ->where('status', 'Active')
                                ->update(['status' => 'Inactive']);

                            \App\Services\PushNotificationService::broadcastAll(
                                '🎉 New Promotion!',
                                $record->title . ' — ' . $record->description,
                            );
                        }
                    }),

                EditAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
                DeleteAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn () => (bool) auth()->user()?->is_admin),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit'   => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
