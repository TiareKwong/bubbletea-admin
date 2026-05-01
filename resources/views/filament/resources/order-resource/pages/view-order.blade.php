@php
    $order = $this->record;
    $items = $order->orderItems()->with('flavor')->get();

    $walletUsed  = (float) $order->wallet_amount_used;
    $amountDue   = max(0, (float) $order->total_price - $walletUsed);
    $isGuest     = $order->user?->email === 'guest@internal.local';
    $isPaid      = in_array($order->order_status, ['Paid', 'Preparing', 'Ready', 'Collected']);
    $isCancelled = $order->order_status === 'Cancelled';

    $statusColor = match($order->order_status) {
        'Paid', 'Ready', 'Collected'                           => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7'],
        'Preparing'                                             => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'border' => '#c4b5fd'],
        'Pending Payment', 'Payment Verification',
        'Points Verification'                                   => ['bg' => '#fef3c7', 'text' => '#92400e', 'border' => '#fcd34d'],
        'Cancelled'                                             => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5'],
        default                                                 => ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#e5e7eb'],
    };

    $placedAt = $order->created_at
        ? \Carbon\Carbon::parse($order->created_at)->setTimezone('Pacific/Tarawa')->format('d M Y, g:i A')
        : '—';

    $changeTransactions = \App\Models\WalletTransaction::where('user_id', $order->user_id)
        ->where('reference', 'Change from order #' . $order->order_code)
        ->orderBy('created_at', 'desc')
        ->get();
@endphp

<x-filament-panels::page>

<div style="max-width:700px;">

    {{-- ── Receipt card ──────────────────────────────────────── --}}
    <div style="background:white;border-radius:1rem;border:1px solid #e5e7eb;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.06);">

        {{-- Header --}}
        <div style="padding:1.5rem 1.75rem;border-bottom:1px solid #f3f4f6;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 0.25rem;">Order</p>
                    <p style="font-size:2rem;font-weight:800;color:#111827;margin:0;letter-spacing:-0.03em;line-height:1;">#{{ $order->order_code }}</p>
                </div>
                <span style="display:inline-block;padding:0.4rem 1rem;border-radius:9999px;font-size:0.8rem;font-weight:700;background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};border:1px solid {{ $statusColor['border'] }};white-space:nowrap;margin-top:0.25rem;">
                    {{ $order->order_status }}
                </span>
            </div>

            <div style="display:flex;gap:1.25rem;margin-top:1rem;flex-wrap:wrap;">
                <span style="font-size:0.8rem;color:#6b7280;display:flex;align-items:center;gap:0.3rem;">
                    📅 {{ $placedAt }}
                </span>
                @if($order->branch)
                <span style="font-size:0.8rem;color:#6b7280;display:flex;align-items:center;gap:0.3rem;">
                    🏪 {{ $order->branch->name }}
                </span>
                @endif
                <span style="font-size:0.8rem;color:#6b7280;display:flex;align-items:center;gap:0.3rem;">
                    👤 {{ $isGuest ? 'Walk-in' : ($order->user?->first_name . ' ' . $order->user?->last_name) }}
                </span>
                @if(!$isGuest && $order->user?->phone_number && $order->user->phone_number !== '0000000000')
                <span style="font-size:0.8rem;color:#6b7280;display:flex;align-items:center;gap:0.3rem;">
                    📱 {{ $order->user->phone_number }}
                </span>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <div style="padding:0 1.75rem;">
            <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.08em;margin:1.25rem 0 0.5rem;">Items</p>

            @foreach($items as $item)
                @php
                    $qty       = (int) $item->quantity;
                    $lineTotal = (float) $item->price;
                    $unitPrice = $qty > 0 ? $lineTotal / $qty : $lineTotal;

                    $toppings = $item->toppings;
                    if (is_string($toppings)) $toppings = json_decode($toppings, true) ?? [];
                    $toppingNames = collect((array) $toppings)
                        ->map(fn($t) => is_array($t) ? ($t['name'] ?? null) : $t)
                        ->filter()->unique()->values()->implode(', ');

                    $isLast = $loop->last;
                @endphp
                <div style="display:flex;gap:0.875rem;padding:0.875rem 0;{{ !$isLast ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                    {{-- Qty badge --}}
                    <div style="width:2.25rem;height:2.25rem;border-radius:0.625rem;background:#7c3aed;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span style="font-size:0.8rem;font-weight:800;color:white;">{{ $qty }}×</span>
                    </div>

                    {{-- Name + customisations --}}
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:0.9375rem;font-weight:700;color:#111827;margin:0;">{{ $item->flavor?->name ?? '—' }}</p>
                        <p style="font-size:0.8rem;color:#6b7280;margin:0.125rem 0 0;">
                            {{ $item->size }}{{ ($item->ice && $item->ice !== 'Regular') ? ' · '.$item->ice.' Ice' : '' }}{{ ($item->sugar && $item->sugar !== '100%') ? ' · '.$item->sugar.' Sugar' : '' }}
                        </p>
                        @if($toppingNames)
                            <p style="font-size:0.775rem;color:#9ca3af;margin:0.2rem 0 0;">🫙 {{ $toppingNames }}</p>
                        @endif
                    </div>

                    {{-- Price --}}
                    <div style="text-align:right;flex-shrink:0;">
                        <p style="font-size:0.9375rem;font-weight:700;color:#111827;margin:0;">A${{ number_format($lineTotal, 2) }}</p>
                        @if($qty > 1)
                            <p style="font-size:0.75rem;color:#9ca3af;margin:0;">A${{ number_format($unitPrice, 2) }} each</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Totals block --}}
        <div style="margin:0 1.75rem;border-top:2px dashed #e5e7eb;padding:1rem 0 0;">

            @if((float)($order->discount_applied ?? 0) > 0)
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                <span style="font-size:0.85rem;color:#059669;">🏷️ Discount{{ $order->promo_title ? ' ('.$order->promo_title.')' : '' }}</span>
                <span style="font-size:0.85rem;color:#059669;font-weight:600;">- A${{ number_format((float)$order->discount_applied, 2) }}</span>
            </div>
            @endif

            @if($walletUsed > 0)
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                <span style="font-size:0.85rem;color:#6b7280;">👜 Wallet used</span>
                <span style="font-size:0.85rem;color:#6b7280;">- A${{ number_format($walletUsed, 2) }}</span>
            </div>
            @endif

            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;">
                <span style="font-size:1rem;font-weight:700;color:#111827;">Total</span>
                <span style="font-size:1.5rem;font-weight:800;color:#111827;">A${{ number_format($order->total_price, 2) }}</span>
            </div>

            @if($amountDue > 0 && !$isCancelled && !$isPaid)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0.75rem;background:#fef3c7;border-radius:0.5rem;border:1px solid #fcd34d;margin-top:0.25rem;">
                <span style="font-size:0.875rem;font-weight:600;color:#92400e;">Amount to Collect</span>
                <span style="font-size:1rem;font-weight:800;color:#92400e;">A${{ number_format($amountDue, 2) }}</span>
            </div>
            @endif
        </div>

        {{-- Payment + Points footer --}}
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.875rem 1.75rem 1.25rem;flex-wrap:wrap;gap:0.5rem;">
            <span style="font-size:0.8rem;color:#6b7280;">
                💳 <strong style="color:#374151;">{{ $order->payment_method }}</strong>
                @if($order->payment_reference)
                    · Ref: <span style="font-family:monospace;">{{ $order->payment_reference }}</span>
                @endif
            </span>
            <div style="display:flex;gap:1rem;">
                @if(! $isGuest && $order->points_earned > 0)
                    <span style="font-size:0.8rem;color:#6b7280;">⭐ +{{ $order->points_earned }} pts earned</span>
                @endif
                @if(! $isGuest && $order->points_used > 0)
                    <span style="font-size:0.8rem;color:#6b7280;">⭐ {{ $order->points_used }} pts used</span>
                @endif
                @if($order->collected)
                    <span style="font-size:0.8rem;color:#059669;font-weight:600;">✓ Collected</span>
                @endif
            </div>
        </div>

        {{-- Change Added to Wallet (registered customers only) --}}
        @if(! $isGuest)
            @if($changeTransactions->isNotEmpty())
            <div style="padding:1rem 1.75rem;border-top:1px solid #e5e7eb;background:#eff6ff;">
                <p style="font-size:0.7rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.08em;margin:0 0 0.5rem;">💰 Change Added to Wallet</p>
                <livewire:order-change-wallet :order-id="$this->record->id" />
            </div>
            @endif

            @if($walletUsed > 0 && !$changeTransactions->isNotEmpty())
            <div style="padding:0.75rem 1.75rem;border-top:1px solid #e5e7eb;background:#f5f3ff;">
                <p style="font-size:0.8rem;color:#5b21b6;margin:0;">
                    👜 <strong>A${{ number_format($walletUsed, 2) }}</strong> was paid from the customer's wallet.
                </p>
            </div>
            @endif
        @endif
    </div>

    {{-- Updated by --}}
    @if($order->updated_by)
    <p style="font-size:0.75rem;color:#9ca3af;margin:0.75rem 0 0;text-align:center;">
        Last updated by {{ $order->updated_by }}
    </p>
    @endif

</div>

</x-filament-panels::page>
