<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Restrict create / edit / delete to admins.
 *
 * Two layers of protection:
 *  1. canCreate/canEdit/canDelete returning false hides buttons AND causes
 *     Filament to abort(403) when the page URL is visited directly.
 *  2. The individual action closures call abort_unless() as a second check,
 *     so even a crafted POST/Livewire request is rejected server-side.
 *
 * Staff can still view the index and individual records (canViewAny / canView
 * are intentionally NOT overridden here).
 */
trait AdminOnlyAccess
{
    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->is_admin;
    }

    public static function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->is_admin;
    }
}
