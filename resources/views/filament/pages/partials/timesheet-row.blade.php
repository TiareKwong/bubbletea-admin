@php
    $i = $row['_index'];
    $hours = (! empty($row['time_in']) && ! empty($row['time_out']))
        ? \App\Models\Timesheet::calculateHours($row['time_in'], $row['time_out'])
        : 0.0;
    $total = round($hours * $rate, 2);
@endphp
<tr wire:key="timesheet-row-{{ $i }}" style="border-bottom:1px solid #f3f4f6;">
    <td style="padding:0.4rem 0.75rem;">
        <div style="display:flex;align-items:center;gap:0.4rem;">
            @if(! empty($row['work_date']))
                <span style="font-size:0.7rem;font-weight:700;color:#9ca3af;width:1.8rem;">{{ \Carbon\Carbon::parse($row['work_date'])->format('D') }}</span>
            @endif
            <input type="date" wire:model.live="rows.{{ $i }}.work_date"
                style="border:1px solid #e5e7eb;border-radius:0.4rem;padding:0.3rem 0.5rem;font-size:0.8rem;color:#111827;width:9.5rem;">
        </div>
    </td>
    <td style="padding:0.4rem 0.75rem;">
        <input type="time" wire:model.live="rows.{{ $i }}.time_in"
            style="border:1px solid #e5e7eb;border-radius:0.4rem;padding:0.3rem 0.5rem;font-size:0.8rem;color:#111827;width:7rem;">
    </td>
    <td style="padding:0.4rem 0.75rem;">
        <input type="time" wire:model.live="rows.{{ $i }}.time_out"
            style="border:1px solid #e5e7eb;border-radius:0.4rem;padding:0.3rem 0.5rem;font-size:0.8rem;color:#111827;width:7rem;">
    </td>
    <td style="padding:0.4rem 0.75rem;text-align:right;color:#374151;">{{ number_format($hours, 2) }}</td>
    <td style="padding:0.4rem 0.75rem;text-align:right;color:#6b7280;">A${{ number_format($rate, 2) }}</td>
    <td style="padding:0.4rem 0.75rem;text-align:right;font-weight:600;color:#111827;">A${{ number_format($total, 2) }}</td>
    <td style="padding:0.4rem 0.75rem;">
        <input type="text" wire:model="rows.{{ $i }}.notes" placeholder="—"
            style="border:1px solid #e5e7eb;border-radius:0.4rem;padding:0.3rem 0.5rem;font-size:0.8rem;color:#111827;width:100%;min-width:8rem;">
    </td>
    <td style="padding:0.4rem 0.75rem;text-align:right;">
        <button wire:click="removeRow({{ $i }})" wire:confirm="Remove this row?"
            style="background:none;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.2rem 0.5rem;font-size:0.75rem;cursor:pointer;color:#dc2626;">
            ✕
        </button>
    </td>
</tr>
