<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Announcements';

    protected static string|\UnitEnum|null $navigationGroup = 'App';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('message')
                ->required()
                ->rows(3)
                ->maxLength(500)
                ->helperText('This message appears on the Home screen banner. Leave empty to auto-generate from new flavors.'),

            DatePicker::make('expires_at')
                ->label('Expires On')
                ->nullable()
                ->helperText('Leave empty to show indefinitely.'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Only one active announcement shows at a time. Activating this will deactivate others.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('message')
                    ->limit(80)
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('activate')
                    ->label('Set Active')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Announcement $record): bool => ! $record->is_active && (auth()->user()?->is_admin ?? false))
                    ->action(function (Announcement $record): void {
                        Announcement::query()->update(['is_active' => false]);
                        $record->update(['is_active' => true]);
                    }),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Announcement $record): bool => $record->is_active && (auth()->user()?->is_admin ?? false))
                    ->action(fn (Announcement $record) => $record->update(['is_active' => false])),

                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->is_admin ?? false),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()?->is_admin ?? false),
            ]);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
        ];
    }
}
