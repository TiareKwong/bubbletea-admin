<x-filament-panels::page>

@php
    $btnBase = 'padding:0.4rem 1.1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid;cursor:pointer;';
    $btnAct  = $btnBase . 'background:#7c3aed;color:#fff;border-color:#7c3aed;';
    $btnInact= $btnBase . 'background:#fff;color:#374151;border-color:#e5e7eb;';

    $items  = $this->productSales;
    $unsold = $items->where('times_ordered', 0)->count();
@endphp

{{-- Range toggle --}}
<div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;">
    <button wire:click="$set('range','30')"  style="{{ $range === '30'  ? $btnAct : $btnInact }}">Last 30 Days</button>
    <button wire:click="$set('range','90')"  style="{{ $range === '90'  ? $btnAct : $btnInact }}">Last 90 Days</button>
    <button wire:click="$set('range','all')" style="{{ $range === 'all' ? $btnAct : $btnInact }}">All Time</button>
</div>

{{-- Not selling callout --}}
@if($unsold > 0)
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:0.75rem;padding:0.9rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.75rem;">
    <span style="font-size:1.25rem;">⚠️</span>
    <span style="font-weight:600;color:#92400e;">{{ $unsold }} product{{ $unsold > 1 ? 's have' : ' has' }} not sold at all in this period — consider running a promotion.</span>
</div>
@endif

{{-- Table --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">#</th>
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Product</th>
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Category</th>
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Status</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Times Ordered</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Total Qty Sold</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $i => $row)
                    @php $notSelling = $row->times_ordered == 0; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;{{ $notSelling ? 'background:#fffbeb;' : ($i % 2 !== 0 ? 'background:#fafafa;' : '') }}">
                        <td style="padding:0.75rem 1rem;color:#9ca3af;">{{ $i + 1 }}</td>
                        <td style="padding:0.75rem 1rem;font-weight:600;color:#111827;">
                            {{ $row->name }}
                            @if($notSelling)
                                <span style="margin-left:0.4rem;background:#fef3c7;color:#92400e;padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">Not Selling</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            @if($row->category)
                                <span style="background:#ede9fe;color:#6d28d9;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">{{ $row->category }}</span>
                            @else
                                <span style="color:#d1d5db;">—</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            @php
                                $statusColor = match(strtolower($row->status)) {
                                    'available'    => 'background:#d1fae5;color:#065f46;',
                                    'unavailable'  => 'background:#fee2e2;color:#991b1b;',
                                    default        => 'background:#f3f4f6;color:#6b7280;',
                                };
                            @endphp
                            <span style="{{ $statusColor }}padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">{{ ucfirst($row->status) }}</span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:{{ $notSelling ? '#d97706' : '#374151' }};">
                            {{ $row->times_ordered }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;color:#374151;">
                            @if($notSelling)
                                <span style="color:#d1d5db;">—</span>
                            @else
                                {{ $row->total_qty }}
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;color:#374151;">
                            @if($notSelling)
                                <span style="color:#d1d5db;">—</span>
                            @else
                                A${{ number_format((float) $row->total_revenue, 2) }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding:2rem;text-align:center;color:#9ca3af;">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-filament-panels::page>
