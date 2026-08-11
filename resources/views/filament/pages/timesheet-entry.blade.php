<x-filament-panels::page>

@php
    $btnBase   = 'padding:0.4rem 1.1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid;cursor:pointer;';
    $btnPurple = $btnBase . 'background:#7c3aed;color:#fff;border-color:#7c3aed;';
    $btnGray   = $btnBase . 'background:#fff;color:#374151;border-color:#e5e7eb;';
    $btnGreen  = $btnBase . 'background:#16a34a;color:#fff;border-color:#16a34a;';
    $btnNav    = 'padding:0.4rem 0.75rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:1px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;';

    $rate    = (float) ($this->selectedStaff?->hourly_rate ?? 0);
    $undated = $this->undatedRows;
    $dated   = $this->datedRows;
    $isCurrentWeek = $this->isCurrentWeek;

    $weekHours = 0.0;
    $weekTotal = 0.0;
    foreach ($rows as $row) {
        if (empty($row['time_in']) || empty($row['time_out'])) continue;
        $h = \App\Models\Timesheet::calculateHours($row['time_in'], $row['time_out']);
        $weekHours += $h;
        $weekTotal += round($h * $rate, 2);
    }

    // KPF (Kiribati Provident Fund): 7.5% employee deduction + 7.5% employer
    // contribution = 15% total, same split used on the Pay Runs page.
    $empKpf    = round($weekTotal * 0.075, 2);
    $erKpf     = round($weekTotal * 0.075, 2);
    $totalCost = round($weekTotal + $erKpf, 2);

    // Cash-short and manual deductions for this staff member/week (e.g. a
    // shortage attributed from Daily Reconciliation). Employer KPF/Total
    // Cost stay based on gross — these are amounts owed back by the staff
    // member, not a change in wage cost to the business.
    $deductions       = $this->deductions;
    $totalDeductions  = (float) $deductions->sum('amount');
    $netPay           = round($weekTotal - $empKpf - $totalDeductions, 2);
@endphp

{{-- ── Staff & Pay Week selector ─────────────────────────────────────── --}}
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;">
    <div style="font-weight:700;font-size:0.95rem;color:#111827;margin-bottom:1rem;">Enter Timesheet</div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:0.25rem;min-width:220px;">
            <label style="font-size:0.75rem;font-weight:600;color:#6b7280;">Staff Member</label>
            <select wire:model.live="staffId"
                style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:0.875rem;color:#111827;">
                <option value="">— Select staff —</option>
                @foreach($this->staffOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label style="font-size:0.75rem;font-weight:600;color:#6b7280;">Pay Week (Fri – Thu)</label>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button wire:click="prevWeek" style="{{ $btnNav }}" title="Previous week">←</button>
                <input type="date" wire:model.live="weekStart"
                    style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:0.875rem;color:#111827;">
                <button wire:click="nextWeek" style="{{ $btnNav }}" title="Next week">→</button>
            </div>
        </div>

        @if(! $isCurrentWeek)
            <button wire:click="goToCurrentWeek" style="{{ $btnGray }}">This Week</button>
        @endif

        @if($rate <= 0 && $this->selectedStaff)
            <div style="font-size:0.8rem;color:#d97706;">No hourly rate set for this staff member — Total will show as A$0.00.</div>
        @endif
    </div>

    <div style="margin-top:0.75rem;font-size:0.8rem;color:#6b7280;">
        <strong style="color:#111827;">{{ \Carbon\Carbon::parse($weekStart)->format('D, d M Y') }} – {{ \Carbon\Carbon::parse($this->weekEnd)->format('D, d M Y') }}</strong>
        · Paid on {{ \Carbon\Carbon::parse($this->weekEnd)->format('D, d M Y') }}
        @if($isCurrentWeek) <span style="margin-left:0.4rem;background:#ede9fe;color:#6d28d9;padding:0.1rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">CURRENT WEEK</span> @endif
    </div>
</div>

@if(! $this->selectedStaff)
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:0.75rem;padding:2rem;text-align:center;color:#9ca3af;">
        Select a staff member to view or enter their timesheet for this pay week.
    </div>
@else
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;margin-bottom:1.5rem;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Date</th>
                        <th style="text-align:left;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Time In</th>
                        <th style="text-align:left;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Time Out</th>
                        <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Hrs</th>
                        <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Rate</th>
                        <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Gross</th>
                        <th style="text-align:left;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Notes</th>
                        <th style="padding:0.6rem 0.75rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($undated))
                        <tr>
                            <td colspan="8" style="padding:0.5rem 0.75rem;background:#fffbeb;color:#92400e;font-size:0.75rem;">
                                Rows below are missing a date — add one to include them in this week's total.
                            </td>
                        </tr>
                        @foreach($undated as $row)
                            @include('filament.pages.partials.timesheet-row', ['row' => $row, 'rate' => $rate])
                        @endforeach
                    @endif

                    @foreach($dated as $row)
                        @include('filament.pages.partials.timesheet-row', ['row' => $row, 'rate' => $rate])
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:#f3f4f6;border-top:2px solid #e5e7eb;">
                        <td colspan="3" style="padding:0.6rem 0.75rem;font-weight:700;color:#111827;">Week total</td>
                        <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#111827;">{{ number_format($weekHours, 2) }}</td>
                        <td></td>
                        <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#7c3aed;">A${{ number_format($weekTotal, 2) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="padding:1.25rem;border-top:1px solid #f3f4f6;background:#f9fafb;display:flex;flex-wrap:wrap;gap:1.25rem;align-items:stretch;">
            <div style="flex:1;min-width:260px;">
                <div style="font-weight:700;font-size:0.8rem;color:#374151;margin-bottom:0.6rem;">Breakdown (KPF: 7.5% employee + 7.5% employer = 15% total)</div>
                <div style="display:flex;flex-wrap:wrap;gap:1.25rem 2.5rem;font-size:0.875rem;">
                    <div>
                        <div style="color:#6b7280;font-size:0.75rem;">Gross Pay</div>
                        <div style="font-weight:700;color:#111827;">A${{ number_format($weekTotal, 2) }}</div>
                    </div>
                    <div>
                        <div style="color:#6b7280;font-size:0.75rem;">KPF (Employee 7.5%)</div>
                        <div style="font-weight:700;color:#dc2626;">− A${{ number_format($empKpf, 2) }}</div>
                    </div>
                    @if($totalDeductions > 0)
                        <div>
                            <div style="color:#6b7280;font-size:0.75rem;">Deductions</div>
                            <div style="font-weight:700;color:#dc2626;">− A${{ number_format($totalDeductions, 2) }}</div>
                        </div>
                    @endif
                    <div>
                        <div style="color:#6b7280;font-size:0.75rem;">KPF (Employer 7.5%)</div>
                        <div style="font-weight:700;color:#d97706;">A${{ number_format($erKpf, 2) }}</div>
                    </div>
                    <div>
                        <div style="color:#6b7280;font-size:0.75rem;">Total Cost to You</div>
                        <div style="font-weight:700;color:#7c3aed;">A${{ number_format($totalCost, 2) }}</div>
                    </div>
                </div>
            </div>

            <div style="background:#fff;border:2px solid #16a34a;border-radius:0.75rem;padding:1rem 1.5rem;min-width:240px;">
                <div style="color:#16a34a;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.03em;">Net Pay</div>
                <div style="font-size:2.1rem;font-weight:800;color:#16a34a;line-height:1.2;margin-top:0.15rem;">A${{ number_format($netPay, 2) }}</div>
            </div>
        </div>

        <div style="padding:1rem 1.25rem;border-top:1px solid #f3f4f6;display:flex;gap:0.75rem;align-items:center;">
            <button wire:click="addRow" style="{{ $btnGray }}">+ Add Row (extra shift)</button>
            <button wire:click="save" style="{{ $btnGreen }}">Save Timesheet</button>
            <span style="font-size:0.75rem;color:#9ca3af;">All 7 days of the pay week are shown above — use "Add Row" only for a second shift on the same day.</span>
        </div>
    </div>

    {{-- ── Deductions ─────────────────────────────────────────────── --}}
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;margin-bottom:1.5rem;">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f3f4f6;font-weight:700;font-size:0.95rem;color:#111827;">
            Deductions This Week
        </div>

        @if($deductions->isEmpty())
            <div style="padding:1.25rem;color:#9ca3af;font-size:0.875rem;">No deductions for this pay week.</div>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;color:#374151;">Date</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;color:#374151;">Source</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem;font-weight:600;color:#374151;">Reason</th>
                            <th style="text-align:right;padding:0.5rem 0.75rem;font-weight:600;color:#374151;">Amount</th>
                            <th style="padding:0.5rem 0.75rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deductions as $d)
                            <tr wire:key="deduction-{{ $d->id }}" style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:0.5rem 0.75rem;color:#374151;">{{ $d->date->format('D, d M Y') }}</td>
                                <td style="padding:0.5rem 0.75rem;">
                                    @if($d->source === 'cash_short')
                                        <span style="background:#fee2e2;color:#991b1b;padding:0.1rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">CASH SHORT</span>
                                    @else
                                        <span style="background:#f3f4f6;color:#374151;padding:0.1rem 0.5rem;border-radius:9999px;font-size:0.7rem;font-weight:700;">MANUAL</span>
                                    @endif
                                </td>
                                <td style="padding:0.5rem 0.75rem;color:#6b7280;">{{ $d->reason ?: '—' }}</td>
                                <td style="padding:0.5rem 0.75rem;text-align:right;font-weight:700;color:#dc2626;">− A${{ number_format((float) $d->amount, 2) }}</td>
                                <td style="padding:0.5rem 0.75rem;text-align:right;">
                                    <button wire:click="removeDeduction({{ $d->id }})" wire:confirm="Remove this deduction?"
                                        style="background:none;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.2rem 0.5rem;font-size:0.75rem;cursor:pointer;color:#dc2626;">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#f3f4f6;border-top:2px solid #e5e7eb;">
                            <td colspan="3" style="padding:0.6rem 0.75rem;font-weight:700;color:#111827;">Total deductions</td>
                            <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#dc2626;">− A${{ number_format($totalDeductions, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <div style="padding:1rem 1.25rem;border-top:1px solid #f3f4f6;background:#f9fafb;">
            <div style="font-weight:600;font-size:0.8rem;color:#374151;margin-bottom:0.6rem;">Add a Deduction</div>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
                <div style="display:flex;flex-direction:column;gap:0.25rem;">
                    <label style="font-size:0.72rem;font-weight:600;color:#6b7280;">Date</label>
                    <input type="date" wire:model="newDeductionDate"
                        style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.6rem;font-size:0.8rem;color:#111827;">
                </div>
                <div style="display:flex;flex-direction:column;gap:0.25rem;">
                    <label style="font-size:0.72rem;font-weight:600;color:#6b7280;">Amount (A$)</label>
                    <input type="number" min="0" step="0.01" placeholder="0.00" wire:model="newDeductionAmount"
                        style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.6rem;font-size:0.8rem;color:#111827;width:8rem;">
                </div>
                <div style="display:flex;flex-direction:column;gap:0.25rem;flex:1;min-width:200px;">
                    <label style="font-size:0.72rem;font-weight:600;color:#6b7280;">Reason</label>
                    <input type="text" placeholder="e.g. advance repayment" wire:model="newDeductionReason"
                        style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.6rem;font-size:0.8rem;color:#111827;width:100%;">
                </div>
                <button wire:click="addDeduction" style="{{ $btnGray }}">+ Add Deduction</button>
            </div>
        </div>
    </div>
@endif

</x-filament-panels::page>
