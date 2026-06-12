<x-filament-panels::page>

<style>
    @media print {
        .no-print { display: none !important; }
        .print-only { display: block !important; }
        body { font-size: 13px; }
    }
    .print-only { display: none; }
</style>

<script>
    window.addEventListener('print-order-list', () => window.print());
</script>

@php
    $items = $this->items;
@endphp

{{-- Header actions --}}
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
    <div>
        @if($items->isEmpty())
            <span style="font-size:0.875rem;color:#6b7280;">All stock items are above their sea reorder threshold.</span>
        @else
            <span style="font-size:0.875rem;color:#6b7280;">{{ $items->count() }} item{{ $items->count() > 1 ? 's' : '' }} need to be ordered. Fill in the quantities and print.</span>
        @endif
    </div>
    @if($items->isNotEmpty())
    <button wire:click="printList"
        style="padding:0.4rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;display:flex;align-items:center;gap:0.4rem;">
        🖨 Print Order List
    </button>
    @endif
</div>

{{-- Print header --}}
<div class="print-only" style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.25rem;font-weight:700;margin:0 0 0.25rem;">Vicky's Bubble Tea — Cargo Order List</h2>
    <p style="margin:0;color:#6b7280;font-size:0.875rem;">Generated: {{ now('Pacific/Tarawa')->format('d M Y, h:i A') }}</p>
</div>

@if($items->isEmpty())
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:0.75rem;padding:1.5rem;text-align:center;">
        <div style="font-size:1.5rem;margin-bottom:0.5rem;">✅</div>
        <div style="font-weight:600;color:#166534;">All items are above their sea reorder threshold.</div>
        <div style="font-size:0.875rem;color:#4ade80;margin-top:0.25rem;">Nothing needs to be ordered right now.</div>
    </div>
@else
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">#</th>
                        <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Item</th>
                        <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Category</th>
                        <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">In Storage</th>
                        <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Sea Reorder At</th>
                        <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Status</th>
                        <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Qty to Order</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                        @php
                            $isOut = (float) $item->current_quantity <= 0;
                            $rowBg = $isOut ? 'background:#fef2f2;' : ($i % 2 !== 0 ? 'background:#fafafa;' : '');
                        @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;{{ $rowBg }}">
                            <td style="padding:0.75rem 1rem;color:#9ca3af;">{{ $i + 1 }}</td>
                            <td style="padding:0.75rem 1rem;font-weight:600;color:#111827;">
                                {{ $item->name }}
                                @if($isOut)
                                    <span style="margin-left:0.4rem;background:#fee2e2;color:#991b1b;padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">Out of Stock</span>
                                @endif
                            </td>
                            <td style="padding:0.75rem 1rem;">
                                <span style="background:#ede9fe;color:#6d28d9;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">{{ $item->category }}</span>
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:{{ $isOut ? '#dc2626' : '#d97706' }};">
                                {{ rtrim(rtrim(number_format((float) $item->current_quantity, 2), '0'), '.') }} {{ $item->unit }}
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:right;color:#6b7280;">
                                {{ rtrim(rtrim(number_format((float) $item->sea_reorder_quantity, 2), '0'), '.') }} {{ $item->unit }}
                            </td>
                            <td style="padding:0.75rem 1rem;text-align:right;">
                                @if($isOut)
                                    <span style="background:#fee2e2;color:#991b1b;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">Out of Stock</span>
                                @else
                                    <span style="background:#fef3c7;color:#92400e;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">Order by Sea</span>
                                @endif
                            </td>
                            <td style="padding:0.6rem 1rem;text-align:right;">
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="—"
                                    wire:model.lazy="quantities.{{ $item->id }}"
                                    class="no-print"
                                    style="width:90px;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.35rem 0.5rem;font-size:0.875rem;text-align:right;color:#111827;"
                                >
                                <span class="print-only">
                                    {{ $quantities[$item->id] ?? '' }}
                                    @if(!empty($quantities[$item->id])) {{ $item->unit }} @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

</x-filament-panels::page>
