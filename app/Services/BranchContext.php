<?php

namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    const SESSION_KEY = 'active_branch_id';

    public function getId(): ?int
    {
        return session(self::SESSION_KEY);
    }

    public function set(int $id): void
    {
        session([self::SESSION_KEY => $id]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
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
