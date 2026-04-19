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
        $this->redirect(request()->header('Referer') ?? '/admin', navigate: false);
    }

    public function clearBranch(): void
    {
        app(BranchContext::class)->clear();
        $this->redirect(request()->header('Referer') ?? '/admin', navigate: false);
    }

    public function render()
    {
        return view('livewire.branch-switcher', [
            'branches'     => Branch::where('is_active', true)->orderBy('name')->get(),
            'activeBranch' => app(BranchContext::class)->getBranch(),
        ]);
    }
}
