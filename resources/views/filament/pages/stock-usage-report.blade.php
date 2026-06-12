<x-filament-panels::page>

@php
    $btnBase = 'padding:0.4rem 1.1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid;cursor:pointer;';
    $btnAct  = $btnBase . 'background:#7c3aed;color:#fff;border-color:#7c3aed;';
    $btnInact= $btnBase . 'background:#fff;color:#374151;border-color:#e5e7eb;';

    $items  = $this->stockUsage;
    $unused = $items->where('times_dispatched', 0)->count();
@endphp

{{-- Range toggle --}}
<div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;">
    <button wire:click="$set('range','30')"  style="{{ $range === '30'  ? $btnAct : $btnInact }}">Last 30 Days</button>
    <button wire:click="$set('range','90')"  style="{{ $range === '90'  ? $btnAct : $btnInact }}">Last 90 Days</button>
    <button wire:click="$set('range','all')" style="{{ $range === 'all' ? $btnAct : $btnInact }}">All Time</button>
</div>

{{-- Not moving callout --}}
@if($unused > 0)
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:0.75rem;padding:0.9rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.75rem;">
    <span style="font-size:1.25rem;">⚠️</span>
    <span style="font-weight:600;color:#92400e;">{{ $unused }} item{{ $unused > 1 ? 's have' : ' has' }} not been dispatched at all in this period — consider running a promotion.</span>
</div>
@endif

{{-- Table --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">#</th>
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Item</th>
                    <th style="text-align:left;padding:0.75rem 1rem;font-weight:600;color:#374151;">Category</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Times Dispatched</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">Qty Dispatched</th>
                    <th style="text-align:right;padding:0.75rem 1rem;font-weight:600;color:#374151;">In Storage</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $i => $row)
                    @php $unused = $row->times_dispatched == 0; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;{{ $unused ? 'background:#fffbeb;' : ($i % 2 !== 0 ? 'background:#fafafa;' : '') }}">
                        <td style="padding:0.75rem 1rem;color:#9ca3af;">{{ $i + 1 }}</td>
                        <td style="padding:0.75rem 1rem;font-weight:600;color:#111827;">
                            {{ $row->name }}
                            @if($unused)
                                <span style="margin-left:0.4rem;background:#fef3c7;color:#92400e;padding:0.15rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">Not Moving</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;">
                            <span style="background:#ede9fe;color:#6d28d9;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">{{ $row->category }}</span>
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:{{ $unused ? '#d97706' : '#374151' }};">
                            {{ $unused ? '0' : $row->times_dispatched }}
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;color:#374151;">
                            @if($unused)
                                <span style="color:#d1d5db;">—</span>
                            @else
                                {{ rtrim(rtrim(number_format((float) $row->total_dispatched, 2), '0'), '.') }} {{ $row->unit }}
                            @endif
                        </td>
                        <td style="padding:0.75rem 1rem;text-align:right;color:#374151;">
                            {{ rtrim(rtrim(number_format((float) $row->current_quantity, 2), '0'), '.') }} {{ $row->unit }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:2rem;text-align:center;color:#9ca3af;">No stock items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-filament-panels::page>
