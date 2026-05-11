<?php

namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    const SESSION_KEY = 'active_branch_id';

    // 0 stored in session = admin explicitly chose "All Branches"
    private const ALL_SENTINEL = 0;

    public function getId(): ?int
    {
        if (session()->has(self::SESSION_KEY)) {
            $val = session(self::SESSION_KEY);
            return $val === self::ALL_SENTINEL ? null : $val;
        }

        // Default to the user's assigned branch on first load
        $user = auth()->user();
        if ($user && $user->branch_id) {
            $this->set($user->branch_id);
            return $user->branch_id;
        }

        return null;
    }

    public function set(int $id): void
    {
        session([self::SESSION_KEY => $id]);
    }

    public function setAll(): void
    {
        session([self::SESSION_KEY => self::ALL_SENTINEL]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function isAll(): bool
    {
        return session()->has(self::SESSION_KEY) && session(self::SESSION_KEY) === self::ALL_SENTINEL;
    }

    public function getBranch(): ?Branch
    {
        $id = $this->getId();
        return $id ? Branch::find($id) : null;
    }

    public function isSet(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /** Apply branch filter to a query if a branch is active. */
    public function applyTo($query, string $column = 'branch_id'): mixed
    {
        $id = $this->getId();
        if ($id) {
            $query->where($column, $id);
        }
        return $query;
    }
}
