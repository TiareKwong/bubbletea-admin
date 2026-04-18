<x-filament-panels::page>

@php
    $methodTotals   = $this->getMethodTotals();
    $submitted      = $this->getSelectedDateReconciliations();
    $missingDates   = $this->missingDates;
    $availableDates = $this->getAvailableDates();
    $isBackfill    = $this->isBackfill();
    $isAdmin       = auth()->user()?->is_admin;
    $selectedLabel = \Carbon\Carbon::parse($this->selectedDate)->isToday()
                        ? 'Today — ' . \Carbon\Carbon::parse($this->selectedDate)->format('d M Y')
                        : \Carbon\Carbon::parse($this->selectedDate)->format('d M Y');

    $methodLabels = [
        'Cash'          => ['icon' => '💵', 'hint' => 'Count the physical cash in the till'],
        'EFTPOS'        => ['icon' => '💳', 'hint' => 'Check the EFTPOS terminal settlement report'],
        'Bank Transfer' => ['icon' => '🏦', 'hint' => 'Check the bank app for incoming transfers'],
    ];
@endphp

{{-- ── Missing days alert ───────────────────────────────────────────── --}}
@if(! empty($missingDates))
    <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:0.75rem; padding:1rem 1.25rem; margin-bottom:1.25rem; display:flex; gap:1rem; align-items:flex-start;">
        <span style="font-size:1.2rem;">⚠️</span>
        <div>
            <p style="font-weight:600; color:#92400e; margin:0 0 0.35rem;">Missed reconciliations</p>
            <p style="font-size:0.875rem; color:#92400e; margin:0 0 0.5rem;">
                The following past days had orders but no reconciliation was submitted:
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                @foreach($missingDates as $md)
                    <button
                        wire:click="$set('selectedDate', '{{ $md }}')"
                        style="background:#fef3c7; border:1px solid #fcd34d; border-radius:0.375rem; padding:0.25rem 0.75rem; font-size:0.8rem; font-weight:600; color:#92400e; cursor:pointer;">
                        {{ \Carbon\Carbon::parse($md)->format('d M Y') }}
                    </button>
                @endforeach
            </div>
            @if(! $isAdmin)
                <p style="font-size:0.75rem; color:#b45309; margin:0.5rem 0 0;">Contact an admin to back-fill missed dates.</p>
            @endif
        </div>
    </div>
@endif

{{-- ── Date picker ──────────────────────────────────────────────────── --}}
<div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
    <div style="display:flex; align-items:center; gap:0.75rem; background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:0.75rem 1rem;">
        <label style="font-size:0.875rem; font-weight:600; color:#374151; white-space:nowrap;">Reconciling date:</label>
        <select
            wire:model.live="selectedDate"
            style="border:1px solid #d1d5db; border-radius:0.5rem; padding:0.4rem 0.6rem; font-size:0.875rem; color:#111827; outline:none; cursor:pointer; background:#fff;">
            @foreach($availableDates as $d)
                <option value="{{ $d }}">
                    {{ \Carbon\Carbon::parse($d)->isToday() ? 'Today — ' : '' }}{{ \Carbon\Carbon::parse($d)->format('d M Y') }}
                </option>
            @endforeach
        </select>
        <span style="font-size:0.875rem; font-weight:600; color:#7c3aed;">{{ $selectedLabel }}</span>
    </div>

    @if($isBackfill)
        <div style="background:#fef3c7; border:1px solid #fcd34d; border-radius:0.5rem; padding:0.5rem 0.85rem; font-size:0.8rem; font-weight:600; color:#92400e;">
            ⚠️ Back-filling a past date{{ $isAdmin ? '' : ' — admin only' }}
        </div>
        <button
            wire:click="$set('selectedDate', '{{ now('Pacific/Tarawa')->toDateString() }}')"
            style="background:none; border:1px solid #e5e7eb; border-radius:0.5rem; padding:0.45rem 0.85rem; font-size:0.8rem; color:#6b7280; cursor:pointer;">
            Back to today
        </button>
    @endif
</div>

{{-- ── Day overview strip ───────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem;">
    @foreach($methodTotals as $method => $data)
        @php
            $done = isset($submitted[$method]);
            $diff = $done ? (float) $submitted[$method]['difference'] : null;
        @endphp
        <div style="border:1px solid {{ $done ? ($diff >= 0 ? '#d1fae5' : '#fee2e2') : '#e5e7eb' }};
                    border-radius:0.75rem; background:{{ $done ? ($diff >= 0 ? '#f0fdf4' : '#fff7f7') : '#fff' }};
                    padding:1.25rem;">
            <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.25rem;">
                {{ $methodLabels[$method]['icon'] }} {{ $method }}
            </p>
            <p style="font-size:1.5rem; font-weight:700; color:#111827; margin:0;">
                A${{ number_format($data['expected'], 2) }}
            </p>
            <p style="font-size:0.75rem; color:#6b7280; margin:0.2rem 0 0;">
                {{ $data['count'] }} order(s)
                @if($done)
                    &nbsp;·&nbsp;
                    <span style="font-weight:600; color:{{ $diff >= 0 ? '#059669' : '#dc2626' }};">
                        {{ $diff == 0 ? '✓ Balanced' : ($diff > 0 ? '▲ +A$'.number_format(abs($diff),2) : '▼ -A$'.number_format(abs($diff),2)) }}
                    </span>
                @endif
            </p>
        </div>
    @endforeach
</div>

{{-- ── One section per payment method ─────────────────────────────────── --}}
@foreach($methodTotals as $method => $data)
    @php
        $done = isset($submitted[$method]);
        $rec  = $submitted[$method] ?? null;
        $hint = $methodLabels[$method]['hint'];
        $icon = $methodLabels[$method]['icon'];

        $previewActual = (float) str_replace(',', '', $this->actualAmounts[$method] ?: '0');
        $previewDiff   = $previewActual - $data['expected'];
        $previewColor  = $previewDiff >= 0 ? '#059669' : '#dc2626';
        $canSubmit     = ! $isBackfill || $isAdmin;
    @endphp

    <x-filament::section style="margin-top:1.5rem;">
        <x-slot name="heading">{{ $icon }} {{ $method }}</x-slot>

        @if($data['count'] === 0 && ! $done)
            <p style="color:#9ca3af; font-size:0.875rem;">No {{ $method }} orders on this date — nothing to reconcile.</p>
        @else
            {{-- Summary row --}}
            <div style="display:flex; gap:2rem; margin-bottom:1.25rem; padding:0.85rem 1rem; background:#f9fafb; border-radius:0.5rem; font-size:0.875rem;">
                <span style="color:#6b7280;">Orders: <strong style="color:#111827;">{{ $data['count'] }}</strong></span>
                <span style="color:#6b7280;">Expected total: <strong style="color:#059669;">A${{ number_format($data['expected'], 2) }}</strong></span>
            </div>

            @if($done)
                @php $diff = (float) $rec['difference']; @endphp
                <div style="background:{{ $diff >= 0 ? '#f0fdf4' : '#fff7f7' }}; border:1px solid {{ $diff >= 0 ? '#d1fae5' : '#fee2e2' }}; border-radius:0.75rem; padding:1rem 1.25rem;">
                    <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.2rem;">
                        Submitted by {{ $rec['submitted_by'] }}
                        @if(! empty($rec['submitted_at']))
                            &nbsp;·&nbsp;{{ \Carbon\Carbon::parse($rec['submitted_at'], 'UTC')->setTimezone('Pacific/Tarawa')->format('d M Y, h:i A') }}
                        @endif
                    </p>
                    <p style="font-size:1rem; font-weight:600; color:#111827; margin:0;">
                        Actual: A${{ number_format($rec['actual_cash'], 2) }}
                        &nbsp;·&nbsp;
                        <span style="color:{{ $diff >= 0 ? '#059669' : '#dc2626' }};">
                            {{ $diff == 0 ? '✓ Balanced' : ($diff > 0 ? '▲ +A$'.number_format(abs($diff),2).' over' : '▼ A$'.number_format(abs($diff),2).' short') }}
                        </span>
                    </p>
                    @if($rec['notes'])
                        <p style="font-size:0.8rem; color:#6b7280; margin:0.25rem 0 0;">Notes: {{ $rec['notes'] }}</p>
                    @endif
                </div>

            @elseif(! $canSubmit)
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1rem 1.25rem;">
                    <p style="font-size:0.875rem; color:#6b7280; margin:0;">Only an admin can submit reconciliation for past dates.</p>
                </div>

            @else
                <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.75rem;">{{ $hint }}</p>
                <div style="display:grid; grid-template-columns:1fr 2fr auto; gap:1rem; align-items:end;">
                    <div>
                        <label style="display:block; font-size:0.875rem; font-weight:600; color:#374151; margin-bottom:0.4rem;">
                            Actual Amount (A$) <span style="color:#dc2626;">*</span>
                        </label>
                        <input
                            wire:model.live="actualAmounts.{{ $method }}"
                            type="number" min="0" step="0.01" placeholder="0.00"
                            style="width:100%; border:1px solid #d1d5db; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:1rem; font-weight:600; color:#111827; outline:none;"
                        />
                    </div>
                    <div>
                        <label style="display:block; font-size:0.875rem; font-weight:600; color:#374151; margin-bottom:0.4rem;">Notes (optional)</label>
                        <input
                            wire:model="notes.{{ $method }}"
                            type="text" placeholder="e.g. terminal showed different amount"
                            style="width:100%; border:1px solid #d1d5db; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:0.875rem; color:#374151; outline:none;"
                        />
                    </div>
                    <button
                        wire:click="submitMethod('{{ $method }}')"
                        wire:loading.attr="disabled"
                        style="background:#7c3aed; color:#fff; border:none; border-radius:0.5rem; padding:0.6rem 1.4rem; font-size:0.875rem; font-weight:600; cursor:pointer; white-space:nowrap; height:fit-content;">
                        {{ $isBackfill ? 'Back-fill' : 'Submit' }}
                    </button>
                </div>

                @if(($this->actualAmounts[$method] ?? '') !== '' && ($this->actualAmounts[$method] ?? '0') !== '0')
                    <div style="margin-top:0.75rem; padding:0.75rem 1rem; border-radius:0.5rem; background:{{ $previewDiff >= 0 ? '#f0fdf4' : '#fff7f7' }}; border:1px solid {{ $previewDiff >= 0 ? '#d1fae5' : '#fee2e2' }};">
                        <span style="font-size:0.875rem; font-weight:600; color:{{ $previewColor }};">
                            @if($previewDiff == 0) ✓ Balanced — amount matches exactly
                            @elseif($previewDiff > 0) ▲ A${{ number_format(abs($previewDiff), 2) }} over — more than expected
                            @else ▼ A${{ number_format(abs($previewDiff), 2) }} short — less than expected
                            @endif
                        </span>
                    </div>
                @endif
            @endif
        @endif
    </x-filament::section>
@endforeach

</x-filament-panels::page>
