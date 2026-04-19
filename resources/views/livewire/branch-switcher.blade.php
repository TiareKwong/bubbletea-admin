<div x-data="{ open: false }" class="relative flex items-center mr-3">
    <button
        @click="open = !open"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
               {{ $activeBranch ? 'bg-white/15 hover:bg-white/25 text-white' : 'bg-amber-400/90 hover:bg-amber-300 text-amber-900 animate-pulse' }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>{{ $activeBranch?->name ?? 'Select Branch' }}</span>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
        style="display:none;"
    >
        @if($activeBranch)
        <button
            wire:click="clearBranch"
            class="w-full text-left px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-50 flex items-center gap-2 border-b border-gray-100"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            All Branches
        </button>
        @endif

        @forelse($branches as $branch)
        <button
            wire:click="switchBranch({{ $branch->id }})"
            @click="open = false"
            class="w-full text-left px-4 py-2.5 text-sm flex items-center gap-2 transition
                   {{ $activeBranch?->id === $branch->id
                       ? 'bg-purple-50 text-purple-700 font-semibold'
                       : 'text-gray-700 hover:bg-gray-50' }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 {{ $activeBranch?->id === $branch->id ? 'text-purple-500' : 'text-gray-300' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            {{ $branch->name }}
        </button>
        @empty
        <p class="px-4 py-3 text-sm text-gray-400">No branches set up yet.</p>
        @endforelse
    </div>
</div>
