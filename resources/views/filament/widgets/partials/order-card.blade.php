@php
    $customer  = trim(($order->user?->first_name ?? '') . ' ' . ($order->user?->last_name ?? '')) ?: 'Guest';
    $branch    = $order->branch?->name ?? '—';
    $placedAt  = $order->created_at->timezone('Pacific/Tarawa');
    $minutesAgo = (int) $order->created_at->diffInMinutes(now());
    $timeLabel = $minutesAgo < 1
        ? 'just now'
        : ($minutesAgo < 60
            ? $minutesAgo . 'm ago'
            : $placedAt->format('h:i A'));
@endphp

<div style="border:2px solid {{ $borderColor }};background:{{ $bgColor }};border-radius:0.75rem;overflow:hidden;">

    {{-- Card header --}}
    <div style="padding:0.625rem 0.875rem;border-bottom:1px solid {{ $borderColor }}30;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">
        <span style="font-size:1rem;font-weight:800;color:#1f2937;letter-spacing:0.05em;">
            #{{ $order->order_code }}
        </span>
        <span style="font-size:0.75rem;color:#6b7280;white-space:nowrap;">
            ⏱ {{ $timeLabel }}
        </span>
    </div>

    {{-- Customer + branch --}}
    <div style="padding:0.5rem 0.875rem;border-bottom:1px solid {{ $borderColor }}20;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;font-size:0.8rem;">
        <span style="font-weight:600;color:#374151;">👤 {{ $customer }}</span>
        <span style="color:#6b7280;">📍 {{ $branch }}</span>
    </div>

    {{-- Item list (the docket) --}}
    <div style="padding:0.625rem 0.875rem;">
        @foreach ($order->orderItems as $item)
            @php
                $flavorName = $item->flavor?->name ?? 'Unknown';

                // Build descriptor line (size / ice / sugar)
                $descriptors = array_filter([
                    $item->size,
                    $item->ice,
                    $item->sugar,
                ]);

                // Group toppings by name and count (handles both string[] and {id,name,price}[] formats)
                $toppingGroups = collect($item->toppings ?? [])
                    ->map(fn ($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t)
                    ->filter()
                    ->countBy();
            @endphp

            <div style="margin-bottom:0.5rem;padding-bottom:0.5rem;border-bottom:1px dashed #e5e7eb;last:border-b-0;">
                {{-- Flavor + quantity --}}
                <div style="display:flex;align-items:baseline;justify-content:space-between;gap:0.5rem;">
                    <span style="font-weight:700;font-size:0.9rem;color:#111827;">
                        {{ $flavorName }}
                    </span>
                    @if ($item->quantity > 1)
                        <span style="font-weight:700;font-size:0.875rem;color:#7c3aed;white-space:nowrap;">
                            ×{{ $item->quantity }}
                        </span>
                    @endif
                </div>

                {{-- Size / Ice / Sugar --}}
                @if (count($descriptors) > 0)
                    <div style="font-size:0.775rem;color:#6b7280;margin-top:0.1rem;">
                        {{ implode(' · ', $descriptors) }}
                    </div>
                @endif

                {{-- Toppings --}}
                @if ($toppingGroups->isNotEmpty())
                    <div style="font-size:0.775rem;color:#7c3aed;margin-top:0.15rem;">
                        @foreach ($toppingGroups as $name => $count)
                            + {{ $name }}@if ($count > 1) ×{{ $count }}@endif{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Action button --}}
    <div style="padding:0.625rem 0.875rem;border-top:1px solid {{ $borderColor }}30;">
        <button
            wire:click="{{ $action }}({{ $order->id }})"
            wire:loading.attr="disabled"
            style="width:100%;padding:0.5rem;background:{{ $actionColor }};color:white;border:none;border-radius:0.5rem;font-weight:700;font-size:0.875rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:0.4rem;"
            onmouseover="this.style.opacity='0.85'"
            onmouseout="this.style.opacity='1'"
        >
            <span>{{ $actionIcon }}</span>
            <span wire:loading.remove wire:target="{{ $action }}({{ $order->id }})">{{ $actionLabel }}</span>
            <span wire:loading wire:target="{{ $action }}({{ $order->id }})">Working…</span>
        </button>
    </div>

</div>
