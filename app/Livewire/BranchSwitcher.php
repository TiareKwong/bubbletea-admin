<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Services\BranchContext;
use Livewire\Component;

class BranchSwitcher extends Component
{
    public function switchBranch(int $branchId): void
    {
        app(BranchContext::class)->set($branchId);
        auth()->user()->update(['branch_id' => $branchId]);
        session()->save();
        $this->js('window.location.reload()');
    }

    public function viewAllBranches(): void
    {
        app(BranchContext::class)->setAll();
        session()->save();
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $user    = auth()->user();
        $context = app(BranchContext::class);

        return view('livewire.branch-switcher', [
            'branches'        => Branch::where('is_active', true)->orderBy('name')->get(),
            'activeBranch'    => $context->getBranch(),
            'isAdmin'         => (bool) $user?->is_admin,
            'canViewAll'      => (bool) ($user?->is_admin || $user?->is_super_staff),
            'isAll'           => $context->isAll(),
        ]);
    }
}
