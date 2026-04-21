<div x-data="{ open: false }" style="position:relative; display:inline-flex; align-items:center; height:100%; margin-left:0.75rem; margin-right:0.25rem;">

    <button
        @click="open = !open"
        style="display:inline-flex; align-items:center; gap:0.375rem;
               padding:0.3125rem 0.625rem;
               border-radius:0.5rem; font-size:0.8125rem; font-weight:600; cursor:pointer;
               border:1.5px solid {{ $activeBranch ? '#7c3aed' : '#f59e0b' }};
               background:{{ $activeBranch ? '#f5f3ff' : '#fef3c7' }};
               color:{{ $activeBranch ? '#6d28d9' : '#92400e' }};
               line-height:1; white-space:nowrap;"
    >
        <svg style="width:13px;height:13px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span>{{ $activeBranch?->name ?? 'All Branches' }}</span>
        <svg style="width:10px;height:10px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        style="display:none; position:absolute; right:0; top:calc(100% + 6px); width:200px;
               background:#fff; border-radius:0.75rem; box-shadow:0 10px 25px rgba(0,0,0,0.15);
               border:1px solid #e5e7eb; z-index:9999; overflow:hidden;"
    >
        @if($isAdmin && $activeBranch)
        <button
            wire:click="clearBranch"
            style="width:100%; text-align:left; padding:0.625rem 1rem; font-size:0.8125rem;
                   color:#9ca3af; background:none; border:none; border-bottom:1px solid #f3f4f6;
                   cursor:pointer; display:flex; align-items:center; gap:0.5rem;"
            onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='none'"
        >
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            All Branches
        </button>
        @endif

        @forelse($branches as $branch)
        <button
            wire:click="switchBranch({{ $branch->id }})"
            @click="open = false"
            style="width:100%; text-align:left; padding:0.625rem 1rem; font-size:0.8125rem;
                   {{ $activeBranch?->id === $branch->id ? 'color:#6d28d9; font-weight:600; background:#f5f3ff;' : 'color:#374151; background:none;' }}
                   border:none; cursor:pointer; display:flex; align-items:center; gap:0.5rem;"
            onmouseover="this.style.background='{{ $activeBranch?->id === $branch->id ? '#ede9fe' : '#f9fafb' }}'"
            onmouseout="this.style.background='{{ $activeBranch?->id === $branch->id ? '#f5f3ff' : 'none' }}'"
        >
            <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ $branch->name }}
        </button>
        @empty
        <p style="padding:0.75rem 1rem; font-size:0.8125rem; color:#9ca3af;">No branches set up yet.</p>
        @endforelse
    </div>
</div>
