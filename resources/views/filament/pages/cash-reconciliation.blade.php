<x-filament-panels::page>

@php
    $methodTotals   = $this->getMethodTotals();
    $submitted      = $this->getSelectedDateReconciliations(); // keyed by method → array of rows
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
            $methodRecs   = $submitted[$method] ?? [];
            $hasAny       = count($methodRecs) > 0;
            $totalActual  = $hasAny ? array_sum(array_column($methodRecs, 'actual_cash')) : null;
            $cumulativeDiff = $hasAny ? ($totalActual - $data['expected']) : null;
        @endphp
        <div style="border:1px solid {{ $hasAny ? ($cumulativeDiff >= 0 ? '#d1fae5' : '#fee2e2') : '#e5e7eb' }};
                    border-radius:0.75rem; background:{{ $hasAny ? ($cumulativeDiff >= 0 ? '#f0fdf4' : '#fff7f7') : '#fff' }};
                    padding:1.25rem;">
            <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.25rem;">
                {{ $methodLabels[$method]['icon'] }} {{ $method }}
            </p>
            <p style="font-size:1.5rem; font-weight:700; color:#111827; margin:0;">
                A${{ number_format($data['expected'], 2) }}
            </p>
            <p style="font-size:0.75rem; color:#6b7280; margin:0.2rem 0 0;">
                {{ $data['orders_count'] }} order(s)
                @if($data['topup_total'] > 0)
                    &nbsp;·&nbsp; +A${{ number_format($data['topup_total'], 2) }} top-up
                @endif
                @if($data['change_total'] > 0)
                    &nbsp;·&nbsp; +A${{ number_format($data['change_total'], 2) }} change
                @endif
                @if(($data['expense_total'] ?? 0) > 0)
                    &nbsp;·&nbsp; −A${{ number_format($data['expense_total'], 2) }} expenses
                @endif
                @if($hasAny)
                    &nbsp;·&nbsp;
                    <span style="font-weight:600; color:{{ $cumulativeDiff >= 0 ? '#059669' : '#dc2626' }};">
                        {{ $cumulativeDiff == 0 ? '✓ Balanced' : ($cumulativeDiff > 0 ? '▲ +A$'.number_format(abs($cumulativeDiff),2) : '▼ -A$'.number_format(abs($cumulativeDiff),2)) }}
                    </span>
                    @if(count($methodRecs) > 1)
                        &nbsp;·&nbsp; <span style="color:#7c3aed; font-weight:600;">{{ count($methodRecs) }} entries</span>
                    @endif
                @endif
            </p>
        </div>
    @endforeach
</div>

{{-- ── One section per payment method ─────────────────────────────────── --}}
@foreach($methodTotals as $method => $data)
    @php
        $methodRecs = $submitted[$method] ?? [];
        $hasAny     = count($methodRecs) > 0;
        $hint = $methodLabels[$method]['hint'];
        $icon = $methodLabels[$method]['icon'];

        $previewActual    = $method === 'Cash'
            ? $this->getCashDenominationTotal()
            : (float) str_replace(',', '', $this->actualAmounts[$method] ?: '0');
        $alreadySubmitted = $hasAny ? array_sum(array_column($methodRecs, 'actual_cash')) : 0.0;
        $residualExpected = $data['expected'] - $alreadySubmitted;
        $previewDiff      = $previewActual - $residualExpected;
        $previewColor     = $previewDiff >= 0 ? '#059669' : '#dc2626';
        $canSubmit     = ! $isBackfill || $isAdmin;
    @endphp

    <x-filament::section style="margin-top:1.5rem;">
        <x-slot name="heading">{{ $icon }} {{ $method }}</x-slot>

        @if($data['count'] === 0 && ! $hasAny)
            <p style="color:#9ca3af; font-size:0.875rem;">No {{ $method }} orders on this date — nothing to reconcile.</p>
        @else
            {{-- Summary row --}}
            <div style="margin-bottom:1.25rem; padding:0.85rem 1rem; background:#f9fafb; border-radius:0.5rem; font-size:0.875rem;">
                @php $hasBreakdown = $data['topup_total'] > 0 || $data['change_total'] > 0 || ($data['expense_total'] ?? 0) > 0; @endphp
                <div style="display:flex; flex-wrap:wrap; gap:1.5rem; margin-bottom:{{ $hasBreakdown ? '0.6rem' : '0' }};">
                    <span style="color:#6b7280;">
                        Orders: <strong style="color:#111827;">{{ $data['orders_count'] }}</strong>
                        &nbsp;·&nbsp;
                        <strong style="color:#059669;">A${{ number_format($data['orders_total'], 2) }}</strong>
                    </span>
                    @if($data['topup_total'] > 0)
                        <span style="color:#6b7280;">
                            Wallet top-ups: <strong style="color:#111827;">{{ $data['topup_count'] }}</strong>
                            &nbsp;·&nbsp;
                            <strong style="color:#7c3aed;">A${{ number_format($data['topup_total'], 2) }}</strong>
                        </span>
                    @endif
                    @if($data['change_total'] > 0)
                        <span style="color:#6b7280;">
                            Change to wallets:
                            <strong style="color:#b45309;">A${{ number_format($data['change_total'], 2) }}</strong>
                        </span>
                    @endif
                    @if(($data['expense_total'] ?? 0) > 0)
                        <span style="color:#6b7280;">
                            Cash box expenses:
                            <strong style="color:#dc2626;">−A${{ number_format($data['expense_total'], 2) }}</strong>
                        </span>
                    @endif
                </div>
                @if($hasBreakdown)
                    <div style="border-top:1px solid #e5e7eb; padding-top:0.5rem; margin-top:0.5rem;">
                        <span style="color:#6b7280; font-weight:600;">
                            Total expected in till:
                            <strong style="color:#059669; font-size:1rem;">A${{ number_format($data['expected'], 2) }}</strong>
                        </span>
                    </div>
                @endif
            </div>

            {{-- All past submissions --}}
            @foreach($methodRecs as $i => $rec)
                @php $diff = (float) $rec['difference']; @endphp
                <div style="background:{{ $diff >= 0 ? '#f0fdf4' : '#fff7f7' }}; border:1px solid {{ $diff >= 0 ? '#d1fae5' : '#fee2e2' }}; border-radius:0.75rem; padding:1rem 1.25rem; margin-bottom:0.75rem;">
                    <p style="font-size:0.75rem; color:#9ca3af; margin:0 0 0.2rem; font-weight:600; letter-spacing:0.03em; text-transform:uppercase;">
                        Entry #{{ $i + 1 }}
                    </p>
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
            @endforeach

            {{-- New submission form — only when there is remaining cash to reconcile --}}
            @if($hasAny && $residualExpected <= 0)
                {{-- All cash accounted for, nothing more to submit --}}
            @elseif(! $canSubmit)
                <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1rem 1.25rem;">
                    <p style="font-size:0.875rem; color:#6b7280; margin:0;">Only an admin can submit reconciliation for past dates.</p>
                </div>
            @else
                @if($hasAny)
                    <p style="font-size:0.8rem; font-weight:600; color:#7c3aed; margin:0.5rem 0 0.75rem;">➕ Add another entry (e.g. cash received from wallet top-ups)</p>
                @else
                    <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.75rem;">{{ $hint }}</p>
                @endif

                {{-- Cash denomination counter --}}
                @if($method === 'Cash')
                    @php
                        $coins = ['5c','10c','20c','50c','$1','$2'];
                        $notes_denom = ['$5','$10','$20','$50','$100'];
                        $denomValues = ['5c'=>0.05,'10c'=>0.10,'20c'=>0.20,'50c'=>0.50,'$1'=>1.00,'$2'=>2.00,'$5'=>5.00,'$10'=>10.00,'$20'=>20.00,'$50'=>50.00,'$100'=>100.00];
                        $cashTotal = $this->getCashDenominationTotal();
                    @endphp

                    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1rem 1.25rem; margin-bottom:1rem;">
                        <p style="font-size:0.8rem; font-weight:600; color:#374151; margin:0 0 0.75rem;">🪙 Coins</p>
                        <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:0.6rem; margin-bottom:1rem;">
                            @foreach($coins as $denom)
                                @php $subtotal = max(0,(int)($this->denominations[$denom]??0)) * $denomValues[$denom]; @endphp
                                <div style="text-align:center;">
                                    <div style="font-size:0.75rem; font-weight:700; color:#4b5563; margin-bottom:0.3rem;">{{ $denom }}</div>
                                    <input
                                        wire:model.live="denominations.{{ $denom }}"
                                        type="number" min="0" step="1" placeholder="0"
                                        style="width:100%; border:1px solid #d1d5db; border-radius:0.4rem; padding:0.4rem 0.3rem; font-size:0.9rem; font-weight:600; color:#111827; text-align:center; outline:none;"
                                    />
                                    @if($subtotal > 0)
                                        <div style="font-size:0.7rem; color:#7c3aed; margin-top:0.2rem;">= A${{ number_format($subtotal,2) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <p style="font-size:0.8rem; font-weight:600; color:#374151; margin:0 0 0.75rem;">💵 Notes</p>
                        <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:0.6rem;">
                            @foreach($notes_denom as $denom)
                                @php $subtotal = max(0,(int)($this->denominations[$denom]??0)) * $denomValues[$denom]; @endphp
                                <div style="text-align:center;">
                                    <div style="font-size:0.75rem; font-weight:700; color:#4b5563; margin-bottom:0.3rem;">{{ $denom }}</div>
                                    <input
                                        wire:model.live="denominations.{{ $denom }}"
                                        type="number" min="0" step="1" placeholder="0"
                                        style="width:100%; border:1px solid #d1d5db; border-radius:0.4rem; padding:0.4rem 0.3rem; font-size:0.9rem; font-weight:600; color:#111827; text-align:center; outline:none;"
                                    />
                                    @if($subtotal > 0)
                                        <div style="font-size:0.7rem; color:#7c3aed; margin-top:0.2rem;">= A${{ number_format($subtotal,2) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div style="margin-top:1rem; padding:0.75rem 1rem; background:#ede9fe; border-radius:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.875rem; font-weight:600; color:#5b21b6;">Cash counted total</span>
                            <span style="font-size:1.25rem; font-weight:700; color:#5b21b6;">A${{ number_format($cashTotal, 2) }}</span>
                        </div>
                    </div>
                @endif

                <div style="display:grid; grid-template-columns:1fr 2fr auto; gap:1rem; align-items:end;">
                    <div>
                        <label style="display:block; font-size:0.875rem; font-weight:600; color:#374151; margin-bottom:0.4rem;">
                            Actual Amount (A$) <span style="color:#dc2626;">*</span>
                        </label>
                        @if($method === 'Cash')
                            <input
                                type="text" readonly
                                value="A${{ number_format($cashTotal, 2) }}"
                                style="width:100%; border:1px solid #c4b5fd; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:1rem; font-weight:700; color:#5b21b6; background:#f5f3ff; outline:none; cursor:default;"
                            />
                        @else
                            <input
                                wire:model.live="actualAmounts.{{ $method }}"
                                type="number" min="0" step="0.01" placeholder="0.00"
                                style="width:100%; border:1px solid #d1d5db; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:1rem; font-weight:600; color:#111827; outline:none;"
                            />
                        @endif
                    </div>
                    <div>
                        <label style="display:block; font-size:0.875rem; font-weight:600; color:#374151; margin-bottom:0.4rem;">Notes (optional)</label>
                        <input
                            wire:model="notes.{{ $method }}"
                            type="text" placeholder="e.g. wallet top-up cash received after close"
                            style="width:100%; border:1px solid #d1d5db; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:0.875rem; color:#374151; outline:none;"
                        />
                    </div>
                    <button
                        wire:click="submitMethod('{{ $method }}')"
                        wire:loading.attr="disabled"
                        style="background:#7c3aed; color:#fff; border:none; border-radius:0.5rem; padding:0.6rem 1.4rem; font-size:0.875rem; font-weight:600; cursor:pointer; white-space:nowrap; height:fit-content;">
                        {{ $isBackfill ? 'Back-fill' : ($hasAny ? 'Add Entry' : 'Submit') }}
                    </button>
                </div>

                @if($previewActual > 0)
                    <div style="margin-top:0.75rem; padding:0.75rem 1rem; border-radius:0.5rem; background:{{ $previewDiff >= 0 ? '#f0fdf4' : '#fff7f7' }}; border:1px solid {{ $previewDiff >= 0 ? '#d1fae5' : '#fee2e2' }};">
                        <span style="font-size:0.875rem; font-weight:600; color:{{ $previewColor }};">
                            @if($previewDiff == 0) ✓ Balanced — amount matches exactly
                            @elseif($previewDiff > 0) ▲ A${{ number_format(abs($previewDiff), 2) }} over — more than expected
                            @else ▼ A${{ number_format(abs($previewDiff), 2) }} short — less than expected
                            @endif
                        </span>
                        @if($hasAny)
                            <span style="font-size:0.8rem; color:#6b7280; margin-left:0.75rem;">
                                (remaining to cover: A${{ number_format(max(0, $residualExpected), 2) }})
                            </span>
                        @endif
                    </div>
                @endif
            @endif
        @endif
    </x-filament::section>
@endforeach

</x-filament-panels::page>
