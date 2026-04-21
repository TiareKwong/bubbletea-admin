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
        session()->save();
        $this->js('window.location.reload()');
    }

    public function clearBranch(): void
    {
        app(BranchContext::class)->clear();
        session()->save();
        $this->js('window.location.reload()');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.branch-switcher', [
            'branches'     => Branch::where('is_active', true)->orderBy('name')->get(),
            'activeBranch' => app(BranchContext::class)->getBranch(),
            'isAdmin'      => (bool) $user?->is_admin,
        ]);
    }
}
