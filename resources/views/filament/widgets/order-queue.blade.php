<x-filament-widgets::widget>
    <div wire:poll.60s>
    <x-filament::section>
        <x-slot name="heading">Order Queue</x-slot>
        <x-slot name="description">Live order docket — oldest orders shown first. Updates every minute.</x-slot>

        {{-- $orders, $paid, $preparing, $ready passed from getViewData() --}}

        @if ($orders->isEmpty())
            <div style="text-align:center; padding:2.5rem 1rem; color:#9ca3af;">
                <svg style="width:3rem;height:3rem;margin:0 auto 0.75rem;display:block;opacity:0.4;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p style="font-size:1rem;font-weight:600;margin:0 0 0.25rem;">No active orders</p>
                <p style="font-size:0.875rem;margin:0;">New paid orders will appear here automatically.</p>
            </div>
        @else

            {{-- ── NEEDS PREPARING (Paid) ─────────────────────────────────── --}}
            @if ($paid->isNotEmpty())
                <div style="margin-bottom:1.5rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                        <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#f59e0b;display:inline-block;flex-shrink:0;"></span>
                        <h3 style="font-size:0.875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#b45309;margin:0;">
                            Needs Preparing
                        </h3>
                        <span style="background:#fef3c7;color:#92400e;font-size:0.75rem;font-weight:700;padding:0.125rem 0.5rem;border-radius:9999px;">
                            {{ $paid->count() }}
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.75rem;">
                        @foreach ($paid as $order)
                            @include('filament.widgets.partials.order-card', [
                                'order'       => $order,
                                'borderColor' => '#f59e0b',
                                'bgColor'     => '#fffbeb',
                                'action'      => 'markPreparing',
                                'actionLabel' => 'Start Preparing',
                                'actionColor' => '#d97706',
                                'actionIcon'  => '🔥',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── IN PROGRESS (Preparing) ────────────────────────────────── --}}
            @if ($preparing->isNotEmpty())
                <div style="margin-bottom:1.5rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                        <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#3b82f6;display:inline-block;flex-shrink:0;"></span>
                        <h3 style="font-size:0.875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#1d4ed8;margin:0;">
                            In Progress
                        </h3>
                        <span style="background:#dbeafe;color:#1e40af;font-size:0.75rem;font-weight:700;padding:0.125rem 0.5rem;border-radius:9999px;">
                            {{ $preparing->count() }}
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.75rem;">
                        @foreach ($preparing as $order)
                            @include('filament.widgets.partials.order-card', [
                                'order'       => $order,
                                'borderColor' => '#3b82f6',
                                'bgColor'     => '#eff6ff',
                                'action'      => 'markReady',
                                'actionLabel' => 'Mark Ready',
                                'actionColor' => '#2563eb',
                                'actionIcon'  => '🔔',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── READY FOR PICKUP (Ready) ────────────────────────────────── --}}
            @if ($ready->isNotEmpty())
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                        <span style="width:0.75rem;height:0.75rem;border-radius:9999px;background:#10b981;display:inline-block;flex-shrink:0;"></span>
                        <h3 style="font-size:0.875rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#065f46;margin:0;">
                            Ready for Pickup
                        </h3>
                        <span style="background:#d1fae5;color:#064e3b;font-size:0.75rem;font-weight:700;padding:0.125rem 0.5rem;border-radius:9999px;">
                            {{ $ready->count() }}
                        </span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.75rem;">
                        @foreach ($ready as $order)
                            @include('filament.widgets.partials.order-card', [
                                'order'       => $order,
                                'borderColor' => '#10b981',
                                'bgColor'     => '#ecfdf5',
                                'action'      => 'markCollected',
                                'actionLabel' => 'Mark Collected',
                                'actionColor' => '#059669',
                                'actionIcon'  => '✅',
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

        @endif
    </x-filament::section>
    </div>
</x-filament-widgets::widget>
