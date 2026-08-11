<x-filament-panels::page>

<style>
    @media print {
        .no-print { display: none !important; }
        .print-only { display: block !important; }
    }
    .print-only { display: none; }
</style>
<script>
    window.addEventListener('print-payslip', e => {
        const w = window.open('', '_blank');
        w.document.write(e.detail.html);
        w.document.close();
        w.focus();
        w.print();
    });
</script>

@php
    $btnBase  = 'padding:0.4rem 1.1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid;cursor:pointer;';
    $btnPurple= $btnBase . 'background:#7c3aed;color:#fff;border-color:#7c3aed;';
    $btnGray  = $btnBase . 'background:#fff;color:#374151;border-color:#e5e7eb;';
    $btnGreen = $btnBase . 'background:#16a34a;color:#fff;border-color:#16a34a;';
@endphp

{{-- ── Create New Pay Run ─────────────────────────────────────── --}}
@if(!$previewing)
<div class="no-print" style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;">
    <div style="font-weight:700;font-size:0.95rem;color:#111827;margin-bottom:1rem;">Create New Pay Run</div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label style="font-size:0.75rem;font-weight:600;color:#6b7280;">Week Start</label>
            <input type="date" wire:model.live="weekStart"
                style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:0.875rem;color:#111827;">
        </div>
        <div style="display:flex;flex-direction:column;gap:0.25rem;">
            <label style="font-size:0.75rem;font-weight:600;color:#6b7280;">Week End</label>
            <input type="date" wire:model.live="weekEnd"
                style="border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.4rem 0.75rem;font-size:0.875rem;color:#111827;">
        </div>
        <button wire:click="preview" style="{{ $btnPurple }}">Preview Pay Run</button>
    </div>
</div>
@endif

{{-- ── Preview ────────────────────────────────────────────────── --}}
@if($previewing)
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;">
    <div style="font-weight:700;font-size:0.95rem;color:#111827;margin-bottom:0.25rem;">
        Pay Run Preview — {{ \Carbon\Carbon::parse($weekStart)->format('d M Y') }} to {{ \Carbon\Carbon::parse($weekEnd)->format('d M Y') }}
    </div>
    <div style="font-size:0.8rem;color:#6b7280;margin-bottom:1rem;">Review before confirming. KPF is 7.5% employee + 7.5% employer.</div>

    <div style="overflow-x:auto;margin-bottom:1rem;">
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Staff</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Rate</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Hours</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Gross Pay</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">KPF (Emp)</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Net Pay</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">KPF (Employer)</th>
                    <th style="text-align:right;padding:0.6rem 0.75rem;font-weight:600;color:#374151;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview as $row)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:0.6rem 0.75rem;font-weight:600;color:#111827;">{{ $row['name'] }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;color:#6b7280;">A${{ number_format($row['hourly_rate'], 2) }}/hr</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;color:#374151;">{{ $row['total_hours'] }} hrs</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;color:#111827;">A${{ number_format($row['gross_pay'], 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;color:#dc2626;">− A${{ number_format($row['employee_kpf'], 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#16a34a;">A${{ number_format($row['net_pay'], 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;color:#d97706;">A${{ number_format($row['employer_kpf'], 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:600;color:#7c3aed;">A${{ number_format($row['gross_pay'] + $row['employer_kpf'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f9fafb;border-top:2px solid #e5e7eb;">
                    <td colspan="3" style="padding:0.6rem 0.75rem;font-weight:700;color:#374151;">Totals</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;">A${{ number_format(array_sum(array_column($preview,'gross_pay')), 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#dc2626;">− A${{ number_format(array_sum(array_column($preview,'employee_kpf')), 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#16a34a;">A${{ number_format(array_sum(array_column($preview,'net_pay')), 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#d97706;">A${{ number_format(array_sum(array_column($preview,'employer_kpf')), 2) }}</td>
                    <td style="padding:0.6rem 0.75rem;text-align:right;font-weight:700;color:#7c3aed;">A${{ number_format(array_sum(array_column($preview,'gross_pay')) + array_sum(array_column($preview,'employer_kpf')), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div style="margin-bottom:1rem;">
        <label style="font-size:0.75rem;font-weight:600;color:#6b7280;display:block;margin-bottom:0.25rem;">Notes (optional)</label>
        <textarea wire:model="notes" rows="2"
            style="width:100%;border:1px solid #e5e7eb;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:0.875rem;color:#111827;resize:vertical;"></textarea>
    </div>

    <div style="display:flex;gap:0.75rem;">
        <button wire:click="createPayRun" style="{{ $btnGreen }}">✓ Confirm & Save Pay Run</button>
        <button wire:click="cancelPreview" style="{{ $btnGray }}">Cancel</button>
    </div>
</div>
@endif

{{-- ── Past Pay Runs ───────────────────────────────────────────── --}}
@php $payRuns = $this->payRuns; @endphp

@if($payRuns->isNotEmpty())
<div style="display:flex;flex-direction:column;gap:1rem;">
    @foreach($payRuns as $run)
    @php
        $entries   = $run->entries;
        $totalNet  = $entries->sum('net_pay');
        $totalGross= $entries->sum('gross_pay');
        $totalEmpKpf = $entries->sum('employee_kpf');
        $totalErKpf  = $entries->sum('employer_kpf');
        $totalCost = $totalGross + $totalErKpf;
    @endphp
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
        {{-- Header --}}
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <span style="font-weight:700;color:#111827;">
                    {{ $run->week_start->format('d M Y') }} — {{ $run->week_end->format('d M Y') }}
                </span>
                @if($run->status === 'paid')
                    <span style="margin-left:0.5rem;background:#d1fae5;color:#065f46;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">Paid</span>
                @else
                    <span style="margin-left:0.5rem;background:#fef3c7;color:#92400e;padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">Draft</span>
                @endif
                @if($run->paid_at)
                    <span style="margin-left:0.5rem;font-size:0.75rem;color:#9ca3af;">Paid on {{ $run->paid_at->timezone('Pacific/Tarawa')->format('d M Y') }}</span>
                @endif
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <span style="font-size:0.875rem;color:#6b7280;">Total cost to you: <strong style="color:#7c3aed;">A${{ number_format($totalCost, 2) }}</strong></span>
                @if($run->status === 'draft')
                <button wire:click="markAsPaid({{ $run->id }})"
                    wire:confirm="Mark this pay run as paid?"
                    style="{{ $btnGreen }}font-size:0.8rem;padding:0.3rem 0.8rem;">Mark as Paid</button>
                @endif
            </div>
        </div>

        {{-- Entries table --}}
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                <thead>
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left;padding:0.5rem 1rem;font-weight:600;color:#374151;">Staff</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">Rate</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">Hours</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">Gross</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">KPF (Emp)</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">Net Pay</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">KPF (Employer)</th>
                        <th style="text-align:right;padding:0.5rem 1rem;font-weight:600;color:#374151;">Payslip</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                    @php
                        $timesheets = \App\Models\Timesheet::with('branch')
                            ->where('user_id', $entry->user_id)
                            ->whereBetween('work_date', [$run->week_start->toDateString(), $run->week_end->toDateString()])
                            ->orderBy('work_date')
                            ->get();

                        $payslipRows = '';
                        foreach ($timesheets as $ts) {
                            $dayPay = round((float)$ts->hours_worked * (float)$entry->hourly_rate, 2);
                            $payslipRows .= '<tr>
                                <td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;">' . $ts->work_date->format('D, d M Y') . '</td>
                                <td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;">' . ($ts->branch?->name ?? '—') . '</td>
                                <td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;text-align:right;">' . $ts->hours_worked . ' hrs</td>
                                <td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;text-align:right;">A$' . number_format($dayPay, 2) . '</td>
                            </tr>';
                        }

                        $payslipHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8">
                            <title>Payslip</title>
                            <style>body{font-family:sans-serif;max-width:700px;margin:2rem auto;font-size:14px;}
                            h2{margin:0 0 4px;}table{width:100%;border-collapse:collapse;margin-top:12px;}
                            th{background:#f3f4f6;text-align:left;padding:8px 12px;font-size:12px;}
                            td{padding:6px 12px;border-bottom:1px solid #f3f4f6;}</style></head><body>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;">
                                <div><h2>Payslip</h2><p style="margin:0;color:#6b7280;">Vicky\'s Bubble-Fruit Tea</p></div>
                                <div style="text-align:right;font-size:13px;color:#374151;">
                                    <div><strong>Week:</strong> ' . $run->week_start->format('d M Y') . ' – ' . $run->week_end->format('d M Y') . '</div>
                                    <div><strong>Staff:</strong> ' . $entry->user?->full_name . '</div>
                                    <div><strong>Hourly Rate:</strong> A$' . number_format((float)$entry->hourly_rate, 2) . '/hr</div>
                                </div>
                            </div>
                            <table><thead><tr>
                                <th>Date</th><th>Branch</th><th style="text-align:right">Hours</th><th style="text-align:right">Pay</th>
                            </tr></thead><tbody>' . $payslipRows . '</tbody></table>
                            <table style="margin-top:16px;width:300px;margin-left:auto;">
                                <tr><td>Total Hours</td><td style="text-align:right;font-weight:600;">' . $entry->total_hours . ' hrs</td></tr>
                                <tr><td>Gross Pay</td><td style="text-align:right;font-weight:600;">A$' . number_format((float)$entry->gross_pay, 2) . '</td></tr>
                                <tr><td>KPF Deduction (7.5%)</td><td style="text-align:right;color:#dc2626;">− A$' . number_format((float)$entry->employee_kpf, 2) . '</td></tr>
                                <tr style="border-top:2px solid #e5e7eb;"><td><strong>Net Pay</strong></td><td style="text-align:right;font-weight:700;color:#16a34a;">A$' . number_format((float)$entry->net_pay, 2) . '</td></tr>
                                <tr><td style="color:#6b7280;font-size:12px;">Employer KPF (7.5%)</td><td style="text-align:right;color:#d97706;font-size:12px;">A$' . number_format((float)$entry->employer_kpf, 2) . '</td></tr>
                            </table>
                            <p style="margin-top:2rem;font-size:12px;color:#9ca3af;">Generated: ' . now('Pacific/Tarawa')->format('d M Y, h:i A') . ' (Kiribati Time)</p>
                            </body></html>';
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.6rem 1rem;font-weight:600;color:#111827;">{{ $entry->user?->full_name }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;color:#6b7280;">A${{ number_format((float)$entry->hourly_rate, 2) }}/hr</td>
                        <td style="padding:0.6rem 1rem;text-align:right;">{{ $entry->total_hours }} hrs</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:600;">A${{ number_format((float)$entry->gross_pay, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;color:#dc2626;">− A${{ number_format((float)$entry->employee_kpf, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:700;color:#16a34a;">A${{ number_format((float)$entry->net_pay, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;color:#d97706;">A${{ number_format((float)$entry->employer_kpf, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;">
                            <button
                                onclick="window.dispatchEvent(new CustomEvent('print-payslip', { detail: { html: {{ Js::from($payslipHtml) }} } }))"
                                style="background:none;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.25rem 0.6rem;font-size:0.75rem;cursor:pointer;color:#374151;">
                                🖨 Print
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:#f9fafb;border-top:2px solid #e5e7eb;">
                        <td colspan="3" style="padding:0.6rem 1rem;font-weight:700;">Totals</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:700;">A${{ number_format($totalGross, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:700;color:#dc2626;">− A${{ number_format($totalEmpKpf, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:700;color:#16a34a;">A${{ number_format($totalNet, 2) }}</td>
                        <td style="padding:0.6rem 1rem;text-align:right;font-weight:700;color:#d97706;">A${{ number_format($totalErKpf, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if($run->notes)
        <div style="padding:0.75rem 1.25rem;border-top:1px solid #f3f4f6;font-size:0.8rem;color:#6b7280;">Notes: {{ $run->notes }}</div>
        @endif
    </div>
    @endforeach
</div>
@else
<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:0.75rem;padding:2rem;text-align:center;color:#9ca3af;">
    No pay runs yet. Create one above.
</div>
@endif

</x-filament-panels::page>
