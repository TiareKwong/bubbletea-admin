<x-filament-panels::page>

@php
    $methodTotals   = $this->getMethodTotals();
    $submitted      = $this->getSelectedDateReconciliations();
    $missingDates   = $this->missingDates;
    $availableDates = $this->getAvailableDates();
    $isBackfill     = $this->isBackfill();
    $isAdmin        = auth()->user()?->is_admin;
    $canSubmit      = ! $isBackfill || $isAdmin;

    $methodLabels = [
        'Cash'          => ['icon' => '💵', 'hint' => 'Count the physical cash in the till'],
        'EFTPOS'        => ['icon' => '💳', 'hint' => 'Check the EFTPOS terminal settlement report'],
        'Bank Transfer' => ['icon' => '🏦', 'hint' => 'Check the bank app for incoming transfers'],
    ];

    // Overall day totals
    $totalExpected  = array_sum(array_column($methodTotals, 'expected'));
    $totalSubmitted = 0.0;
    $allDone        = true;
    foreach ($methodTotals as $m => $data) {
        $recs = $submitted[$m] ?? [];
        $hasActivity = $data['orders_count'] > 0 || $data['topup_total'] > 0 || $data['change_total'] > 0 || ($data['reimb_total'] ?? 0) > 0;
        if ($hasActivity && count($recs) === 0) $allDone = false;
        $totalSubmitted += count($recs) > 0 ? array_sum(array_column($recs, 'actual_cash')) : 0.0;
    }
    $totalVariance  = $totalSubmitted - $totalExpected;
    $anySubmitted   = $totalSubmitted > 0;
@endphp

{{-- ── Missing days alert ──────────────────────────────────────────── --}}
@if(! empty($missingDates))
<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:1rem;align-items:flex-start;">
    <span style="font-size:1.25rem;flex-shrink:0;">⚠️</span>
    <div>
        <p style="font-weight:700;color:#92400e;margin:0 0 0.25rem;font-size:0.9rem;">Missed reconciliations</p>
        <p style="font-size:0.8rem;color:#92400e;margin:0 0 0.5rem;">These past days had orders but no reconciliation was submitted:</p>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            @foreach($missingDates as $md)
                <button wire:click="$set('selectedDate','{{ $md }}')"
                    style="background:#fef3c7;border:1px solid #fcd34d;border-radius:0.375rem;padding:0.2rem 0.65rem;font-size:0.8rem;font-weight:600;color:#92400e;cursor:pointer;">
                    {{ \Carbon\Carbon::parse($md)->format('d M Y') }}
                </button>
            @endforeach
        </div>
        @if(! $isAdmin)
            <p style="font-size:0.72rem;color:#b45309;margin:0.4rem 0 0;">Contact an admin to back-fill missed dates.</p>
        @endif
    </div>
</div>
@endif

{{-- ── Header: date selector + backfill badge ─────────────────────── --}}
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.6rem;background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.6rem 1rem;">
            <span style="font-size:0.8rem;font-weight:600;color:#6b7280;white-space:nowrap;">Date</span>
            <select wire:model.live="selectedDate"
                style="border:none;font-size:0.95rem;font-weight:700;color:#111827;outline:none;cursor:pointer;background:transparent;">
                @foreach($availableDates as $d)
                    <option value="{{ $d }}">
                        {{ \Carbon\Carbon::parse($d)->isToday() ? 'Today  ' : '' }}{{ \Carbon\Carbon::parse($d)->format('d M Y') }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($isBackfill)
            <span style="background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;padding:0.35rem 0.75rem;font-size:0.8rem;font-weight:700;color:#92400e;">
                ⚠️ Back-filling{{ $isAdmin ? '' : ' — admin only' }}
            </span>
            <button wire:click="$set('selectedDate','{{ now('Pacific/Tarawa')->toDateString() }}')"
                style="background:none;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.35rem 0.75rem;font-size:0.8rem;color:#6b7280;cursor:pointer;">
                Back to today
            </button>
        @endif
    </div>

    {{-- Day status badge --}}
    <span style="padding:0.4rem 1rem;border-radius:9999px;font-size:0.8rem;font-weight:700;
        {{ $allDone && $anySubmitted ? 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;' : 'background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;' }}">
        {{ $allDone && $anySubmitted ? '✓ Day closed' : 'In progress' }}
    </span>
</div>

{{-- ── Overview summary bar ────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.75rem;">
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.35rem;">Expected in Till</p>
        <p style="font-size:1.75rem;font-weight:800;color:#111827;margin:0;line-height:1.1;">A${{ number_format($totalExpected, 2) }}</p>
        <p style="font-size:0.75rem;color:#9ca3af;margin:0.3rem 0 0;">all payment methods</p>
    </div>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.35rem;">Counted</p>
        <p style="font-size:1.75rem;font-weight:800;color:{{ $anySubmitted ? '#2563eb' : '#9ca3af' }};margin:0;line-height:1.1;">
            {{ $anySubmitted ? 'A$'.number_format($totalSubmitted, 2) : '—' }}
        </p>
        <p style="font-size:0.75rem;color:#9ca3af;margin:0.3rem 0 0;">{{ $anySubmitted ? 'submitted so far' : 'not yet submitted' }}</p>
    </div>
    <div style="background:{{ $anySubmitted ? ($totalVariance == 0 ? '#f0fdf4' : ($totalVariance > 0 ? '#f0fdf4' : '#fff7f7')) : '#fff' }};
                border:1px solid {{ $anySubmitted ? ($totalVariance == 0 ? '#6ee7b7' : ($totalVariance > 0 ? '#6ee7b7' : '#fca5a5')) : '#e5e7eb' }};
                border-radius:0.875rem;padding:1.25rem;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.35rem;">Variance</p>
        <p style="font-size:1.75rem;font-weight:800;margin:0;line-height:1.1;color:{{ $anySubmitted ? ($totalVariance == 0 ? '#059669' : ($totalVariance > 0 ? '#059669' : '#dc2626')) : '#9ca3af' }};">
            @if(! $anySubmitted) —
            @elseif($totalVariance == 0) Balanced
            @elseif($totalVariance > 0) +A${{ number_format(abs($totalVariance), 2) }}
            @else -A${{ number_format(abs($totalVariance), 2) }}
            @endif
        </p>
        <p style="font-size:0.75rem;color:#9ca3af;margin:0.3rem 0 0;">
            {{ $anySubmitted ? ($totalVariance == 0 ? 'all balanced' : ($totalVariance > 0 ? 'over' : 'short')) : 'submit to see' }}
        </p>
    </div>
</div>

{{-- ── Per-method cards ─────────────────────────────────────────────── --}}
@foreach($methodTotals as $method => $data)
@php
    $icon       = $methodLabels[$method]['icon'];
    $hint       = $methodLabels[$method]['hint'];
    $methodRecs = $submitted[$method] ?? [];
    $hasAny     = count($methodRecs) > 0;
    $totalActual    = $hasAny ? array_sum(array_column($methodRecs, 'actual_cash')) : 0.0;
    $cumulativeDiff = $hasAny ? ($totalActual - $data['expected']) : null;
    $alreadySubmitted = $hasAny ? $totalActual : 0.0;
    $residualExpected = $data['expected'] - $alreadySubmitted;

    $hasActivity = $data['orders_count'] > 0 || $data['topup_total'] > 0 || $data['change_total'] > 0
                 || ($data['reimb_total'] ?? 0) > 0 || ($data['float_amount'] ?? 0) > 0;

    $previewActual = $method === 'Cash'
        ? $this->getCashDenominationTotal()
        : (float) str_replace(',', '', $this->actualAmounts[$method] ?: '0');
    $previewDiff  = $previewActual - $residualExpected;

    $statusColor = $hasAny
        ? ($cumulativeDiff == 0 ? ['bg'=>'#f0fdf4','border'=>'#6ee7b7','text'=>'#059669','label'=>'Balanced']
                                : ($cumulativeDiff > 0
                                    ? ['bg'=>'#f0fdf4','border'=>'#6ee7b7','text'=>'#059669','label'=>'+A$'.number_format(abs($cumulativeDiff),2).' over']
                                    : ['bg'=>'#fff7f7','border'=>'#fca5a5','text'=>'#dc2626','label'=>'A$'.number_format(abs($cumulativeDiff),2).' short']))
        : ['bg'=>'#fff','border'=>'#e5e7eb','text'=>'#9ca3af','label'=>$hasActivity ? 'Pending' : 'No activity'];
@endphp

<div style="background:#fff;border:1px solid #e5e7eb;border-radius:1rem;overflow:hidden;margin-bottom:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,0.05);">

    {{-- Card header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;background:#fafafa;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <span style="font-size:1.5rem;">{{ $icon }}</span>
            <span style="font-size:1.05rem;font-weight:700;color:#111827;">{{ $method }}</span>
            @if($hasAny && count($methodRecs) > 1)
                <span style="font-size:0.72rem;font-weight:600;color:#7c3aed;background:#ede9fe;border-radius:9999px;padding:0.15rem 0.6rem;">
                    {{ count($methodRecs) }} entries
                </span>
            @endif
        </div>
        <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-size:1.25rem;font-weight:800;color:#111827;">A${{ number_format($data['expected'], 2) }}</span>
            <span style="font-size:0.78rem;font-weight:700;padding:0.3rem 0.85rem;border-radius:9999px;
                background:{{ $statusColor['bg'] }};color:{{ $statusColor['text'] }};border:1px solid {{ $statusColor['border'] }};">
                {{ $statusColor['label'] }}
            </span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;min-height:0;">

        {{-- Left: breakdown + history --------------------------------- --}}
        <div style="padding:1.25rem 1.5rem;border-right:1px solid #f3f4f6;">

            {{-- Opening float (Cash only) --}}
            @if($method === 'Cash')
                <div style="margin-bottom:1rem;padding:0.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:0.625rem;">
                    <p style="font-size:0.7rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">Opening Float</p>
                    <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                        <input wire:model.live="floatAmount" type="number" min="0" step="0.01" placeholder="0.00"
                            style="width:8rem;border:1px solid #93c5fd;border-radius:0.375rem;padding:0.4rem 0.6rem;font-size:0.95rem;font-weight:700;color:#1e40af;background:#fff;outline:none;" />
                        <button wire:click="saveFloat"
                            style="background:#1d4ed8;color:#fff;border:none;border-radius:0.375rem;padding:0.4rem 0.85rem;font-size:0.8rem;font-weight:600;cursor:pointer;">
                            Save
                        </button>
                        @if(($data['float_amount'] ?? 0) > 0)
                            <span style="font-size:0.8rem;color:#1e40af;font-weight:600;">A${{ number_format($data['float_amount'], 2) }} set</span>
                        @else
                            <span style="font-size:0.8rem;color:#9ca3af;">Not set</span>
                        @endif
                    </div>
                    @if($floatSetBy)
                        <p style="font-size:0.7rem;color:#93c5fd;margin:0.35rem 0 0;">Set by {{ $floatSetBy }}{{ $floatUpdatedAt ? ' · '.$floatUpdatedAt : '' }}</p>
                    @endif
                </div>
            @endif

            {{-- Expected breakdown --}}
            @if($hasActivity)
                <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.6rem;">Expected Breakdown</p>
                <div style="font-size:0.85rem;">
                    @if(($data['float_amount'] ?? 0) > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Opening float</span>
                            <span style="font-weight:600;color:#1d4ed8;">+A${{ number_format($data['float_amount'], 2) }}</span>
                        </div>
                    @endif
                    @if($data['orders_count'] > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Orders ({{ $data['orders_count'] }})</span>
                            <span style="font-weight:600;color:#059669;">+A${{ number_format($data['orders_total'], 2) }}</span>
                        </div>
                    @endif
                    @if($data['topup_total'] > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Wallet top-ups ({{ $data['topup_count'] }})</span>
                            <span style="font-weight:600;color:#7c3aed;">+A${{ number_format($data['topup_total'], 2) }}</span>
                        </div>
                    @endif
                    @if($data['change_total'] > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Change to wallets</span>
                            <span style="font-weight:600;color:#b45309;">+A${{ number_format($data['change_total'], 2) }}</span>
                        </div>
                    @endif
                    @if(($data['expense_total'] ?? 0) > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Cash box expenses</span>
                            <span style="font-weight:600;color:#dc2626;">-A${{ number_format($data['expense_total'], 2) }}</span>
                        </div>
                    @endif
                    @if(($data['reimb_total'] ?? 0) > 0)
                        <div style="display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #f9fafb;">
                            <span style="color:#6b7280;">Staff reimbursements</span>
                            <span style="font-weight:600;color:#dc2626;">-A${{ number_format($data['reimb_total'], 2) }}</span>
                        </div>
                    @endif
                    <div style="display:flex;justify-content:space-between;padding:0.5rem 0;margin-top:0.25rem;">
                        <span style="font-weight:700;color:#111827;">Expected in till</span>
                        <span style="font-weight:800;color:#111827;font-size:1rem;">A${{ number_format($data['expected'], 2) }}</span>
                    </div>
                </div>
            @else
                <p style="color:#9ca3af;font-size:0.875rem;margin:0;">No {{ $method }} activity on this date.</p>
            @endif

            {{-- Past submissions --}}
            @if($hasAny)
                <div style="margin-top:1rem;border-top:1px dashed #e5e7eb;padding-top:0.75rem;">
                    <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">Submitted Entries</p>
                    @foreach($methodRecs as $i => $rec)
                        @php $diff = (float) $rec['difference']; @endphp
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.75rem;border-radius:0.5rem;margin-bottom:0.4rem;
                            background:{{ $diff >= 0 ? '#f0fdf4' : '#fff7f7' }};border:1px solid {{ $diff >= 0 ? '#d1fae5' : '#fee2e2' }};">
                            <div>
                                <p style="font-size:0.75rem;color:#6b7280;margin:0;">
                                    #{{ $i + 1 }} · {{ $rec['submitted_by'] }}
                                    @if(! empty($rec['submitted_at']))
                                        · {{ \Carbon\Carbon::parse($rec['submitted_at'], 'UTC')->setTimezone('Pacific/Tarawa')->format('h:i A') }}
                                    @endif
                                </p>
                                @if($rec['notes'])
                                    <p style="font-size:0.75rem;color:#9ca3af;margin:0.1rem 0 0;">{{ $rec['notes'] }}</p>
                                @endif
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <p style="font-size:0.9rem;font-weight:700;color:#111827;margin:0;">A${{ number_format($rec['actual_cash'], 2) }}</p>
                                <p style="font-size:0.72rem;font-weight:600;margin:0;color:{{ $diff >= 0 ? '#059669' : '#dc2626' }};">
                                    {{ $diff == 0 ? 'Balanced' : ($diff > 0 ? '+A$'.number_format(abs($diff),2) : '-A$'.number_format(abs($diff),2)) }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right: counting form -------------------------------------- --}}
        <div style="padding:1.25rem 1.5rem;background:#fafafa;">
            @if(! $hasActivity && ! $hasAny)
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:0.875rem;text-align:center;">
                    No activity to reconcile
                </div>
            @elseif($hasAny && $residualExpected <= 0)
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:0.5rem;">
                    <span style="font-size:2rem;">✅</span>
                    <p style="font-weight:700;color:#059669;margin:0;font-size:0.95rem;">Fully reconciled</p>
                    <p style="font-size:0.8rem;color:#9ca3af;margin:0;">A${{ number_format($totalActual, 2) }} counted</p>
                </div>
            @elseif(! $canSubmit)
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:0.875rem;text-align:center;">
                    Only an admin can submit for past dates.
                </div>
            @else
                <p style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.85rem;">
                    {{ $hasAny ? 'Add Another Entry' : 'Count & Submit' }}
                </p>
                <p style="font-size:0.78rem;color:#6b7280;margin:0 0 1rem;">{{ $hint }}</p>

                {{-- Cash denomination counter --}}
                @if($method === 'Cash')
                    @php
                        $coins      = ['5c','10c','20c','50c','$1','$2'];
                        $noteDenoms = ['$5','$10','$20','$50','$100'];
                        $denomValues = ['5c'=>0.05,'10c'=>0.10,'20c'=>0.20,'50c'=>0.50,'$1'=>1.00,'$2'=>2.00,'$5'=>5.00,'$10'=>10.00,'$20'=>20.00,'$50'=>50.00,'$100'=>100.00];
                        $cashTotal  = $this->getCashDenominationTotal();
                    @endphp

                    <div style="margin-bottom:1rem;">
                        <p style="font-size:0.75rem;font-weight:600;color:#6b7280;margin:0 0 0.5rem;">Coins</p>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.4rem;margin-bottom:0.75rem;">
                            @foreach($coins as $denom)
                                @php $sub = max(0,(int)($this->denominations[$denom]??0)) * $denomValues[$denom]; @endphp
                                <div style="text-align:center;">
                                    <div style="font-size:0.72rem;font-weight:700;color:#4b5563;margin-bottom:0.2rem;">{{ $denom }}</div>
                                    <input wire:model.live="denominations.{{ $denom }}"
                                        type="number" min="0" step="1" placeholder="0"
                                        style="width:100%;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.35rem 0.25rem;font-size:0.9rem;font-weight:600;color:#111827;text-align:center;outline:none;background:#fff;" />
                                    @if($sub > 0)
                                        <div style="font-size:0.67rem;color:#7c3aed;margin-top:0.15rem;">A${{ number_format($sub,2) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <p style="font-size:0.75rem;font-weight:600;color:#6b7280;margin:0 0 0.5rem;">Notes</p>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.4rem;margin-bottom:0.75rem;">
                            @foreach($noteDenoms as $denom)
                                @php $sub = max(0,(int)($this->denominations[$denom]??0)) * $denomValues[$denom]; @endphp
                                <div style="text-align:center;">
                                    <div style="font-size:0.72rem;font-weight:700;color:#4b5563;margin-bottom:0.2rem;">{{ $denom }}</div>
                                    <input wire:model.live="denominations.{{ $denom }}"
                                        type="number" min="0" step="1" placeholder="0"
                                        style="width:100%;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.35rem 0.25rem;font-size:0.9rem;font-weight:600;color:#111827;text-align:center;outline:none;background:#fff;" />
                                    @if($sub > 0)
                                        <div style="font-size:0.67rem;color:#7c3aed;margin-top:0.15rem;">A${{ number_format($sub,2) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div style="padding:0.65rem 1rem;background:#ede9fe;border-radius:0.5rem;display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:0.8rem;font-weight:600;color:#5b21b6;">Cash counted</span>
                            <span style="font-size:1.15rem;font-weight:800;color:#5b21b6;">A${{ number_format($cashTotal, 2) }}</span>
                        </div>
                    </div>

                    {{-- Read-only actual for cash --}}
                    <div style="margin-bottom:0.75rem;">
                        <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.35rem;">Actual Amount</label>
                        <input type="text" readonly value="A${{ number_format($cashTotal, 2) }}"
                            style="width:100%;border:1px solid #c4b5fd;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:1rem;font-weight:700;color:#5b21b6;background:#f5f3ff;outline:none;cursor:default;" />
                    </div>
                @else
                    <div style="margin-bottom:0.75rem;">
                        <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.35rem;">Actual Amount (A$)</label>
                        <input wire:model.live="actualAmounts.{{ $method }}"
                            type="number" min="0" step="0.01" placeholder="0.00"
                            style="width:100%;border:1px solid #d1d5db;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:1rem;font-weight:600;color:#111827;outline:none;background:#fff;" />
                    </div>
                @endif

                {{-- Variance preview --}}
                @if($previewActual > 0)
                    <div style="padding:0.6rem 0.85rem;border-radius:0.5rem;margin-bottom:0.75rem;
                        background:{{ $previewDiff >= 0 ? '#f0fdf4' : '#fff7f7' }};border:1px solid {{ $previewDiff >= 0 ? '#d1fae5' : '#fee2e2' }};">
                        <span style="font-size:0.85rem;font-weight:600;color:{{ $previewDiff >= 0 ? '#059669' : '#dc2626' }};">
                            @if($previewDiff == 0) ✓ Balanced
                            @elseif($previewDiff > 0) +A${{ number_format(abs($previewDiff),2) }} over
                            @else -A${{ number_format(abs($previewDiff),2) }} short
                            @endif
                        </span>
                        @if($hasAny)
                            <span style="font-size:0.75rem;color:#9ca3af;margin-left:0.5rem;">remaining: A${{ number_format(max(0,$residualExpected),2) }}</span>
                        @endif
                    </div>
                @endif

                <div style="margin-bottom:0.6rem;">
                    <label style="display:block;font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:0.35rem;">Notes (optional)</label>
                    <input wire:model="notes.{{ $method }}" type="text"
                        placeholder="e.g. slight discrepancy in change given"
                        style="width:100%;border:1px solid #d1d5db;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:0.875rem;color:#374151;outline:none;background:#fff;" />
                </div>

                <button wire:click="submitMethod('{{ $method }}')" wire:loading.attr="disabled"
                    style="width:100%;background:#7c3aed;color:#fff;border:none;border-radius:0.5rem;padding:0.7rem;font-size:0.9rem;font-weight:700;cursor:pointer;">
                    {{ $isBackfill ? 'Back-fill Entry' : ($hasAny ? 'Add Entry' : 'Submit Reconciliation') }}
                </button>
            @endif
        </div>
    </div>
</div>
@endforeach

</x-filament-panels::page>
