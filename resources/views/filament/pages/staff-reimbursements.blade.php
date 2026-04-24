<x-filament-panels::page>

@php
    $outstanding = $this->getOutstandingByStaff();
    $history     = $this->getReimbursementHistory();
    $totalOwed   = array_sum(array_column($outstanding, 'total'));
    $methods     = ['Cash', 'EFTPOS', 'Bank Transfer'];
@endphp

{{-- ── Total owed banner ────────────────────────────────────────────────── --}}
@if($totalOwed > 0)
    <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:0.75rem; padding:1rem 1.25rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <p style="font-weight:700; color:#92400e; margin:0;">Outstanding reimbursements</p>
            <p style="font-size:0.875rem; color:#b45309; margin:0.2rem 0 0;">
                {{ count($outstanding) }} staff member{{ count($outstanding) !== 1 ? 's' : '' }} waiting to be paid back.
            </p>
        </div>
        <span style="font-size:1.75rem; font-weight:800; color:#b45309;">A${{ number_format($totalOwed, 2) }}</span>
    </div>
@endif

{{-- ── Outstanding per staff ────────────────────────────────────────────── --}}
@if(empty($outstanding))
    <x-filament::section>
        <x-slot name="heading">Outstanding Reimbursements</x-slot>
        <p style="color:#9ca3af;">No outstanding reimbursements — all staff have been paid back.</p>
    </x-filament::section>
@else
    @foreach($outstanding as $staff => $data)
        <x-filament::section style="margin-bottom:1.25rem;">
            <x-slot name="heading">
                👤 {{ $staff }}
                &nbsp;
                <span style="font-size:0.95rem; font-weight:600; color:#b45309;">A${{ number_format($data['total'], 2) }} owed</span>
            </x-slot>

            {{-- Expense list --}}
            <table style="width:100%; font-size:0.875rem; border-collapse:collapse; margin-bottom:1rem;">
                <thead>
                    <tr style="border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Description</th>
                        <th style="text-align:right; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['expenses'] as $expense)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:0.5rem; color:#374151;">{{ \Carbon\Carbon::parse($expense['expense_date'])->format('d M Y') }}</td>
                            <td style="padding:0.5rem; color:#111827;">{{ $expense['description'] }}</td>
                            <td style="padding:0.5rem; text-align:right; font-weight:600; color:#059669;">A${{ number_format($expense['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="2" style="padding:0.5rem; text-align:right; font-weight:700; color:#374151;">Total owed:</td>
                        <td style="padding:0.5rem; text-align:right; font-weight:800; color:#b45309;">A${{ number_format($data['total'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Reimburse button / form --}}
            @if(! ($this->showForm[$staff] ?? false))
                <button
                    wire:click="toggleForm('{{ $staff }}')"
                    style="background:#7c3aed; color:#fff; border:none; border-radius:0.5rem; padding:0.55rem 1.25rem; font-size:0.875rem; font-weight:600; cursor:pointer;">
                    💸 Reimburse {{ $staff }}
                </button>
            @else
                <div style="background:#f5f3ff; border:1px solid #c4b5fd; border-radius:0.75rem; padding:1rem 1.25rem;">
                    <p style="font-size:0.875rem; font-weight:600; color:#5b21b6; margin:0 0 0.75rem;">
                        Log reimbursement of <strong>A${{ number_format($data['total'], 2) }}</strong> to {{ $staff }}
                    </p>
                    <p style="font-size:0.75rem; color:#7c3aed; margin:0 0 1rem;">
                        This will clear all {{ count($data['expenses']) }} outstanding expense(s) above.
                        It will NOT be counted as a new expense — only cash will be reduced if paid in Cash.
                    </p>

                    <div style="display:grid; grid-template-columns:1fr 1fr 2fr auto; gap:0.75rem; align-items:end;">
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:0.3rem;">Payment Method</label>
                            <select
                                wire:model.live="paymentMethod.{{ $staff }}"
                                style="width:100%; border:1px solid #c4b5fd; border-radius:0.5rem; padding:0.5rem 0.6rem; font-size:0.875rem; color:#111827; background:#fff; outline:none;">
                                @foreach($methods as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:0.3rem;">Payment Date</label>
                            <input
                                wire:model.live="paymentDate.{{ $staff }}"
                                type="date"
                                style="width:100%; border:1px solid #c4b5fd; border-radius:0.5rem; padding:0.5rem 0.6rem; font-size:0.875rem; color:#111827; outline:none;"
                            />
                        </div>
                        <div>
                            <label style="display:block; font-size:0.8rem; font-weight:600; color:#374151; margin-bottom:0.3rem;">Notes (optional)</label>
                            <input
                                wire:model="paymentNotes.{{ $staff }}"
                                type="text" placeholder="e.g. paid cash from till"
                                style="width:100%; border:1px solid #c4b5fd; border-radius:0.5rem; padding:0.5rem 0.6rem; font-size:0.875rem; color:#374151; outline:none;"
                            />
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button
                                wire:click="reimburse('{{ $staff }}')"
                                wire:loading.attr="disabled"
                                style="background:#7c3aed; color:#fff; border:none; border-radius:0.5rem; padding:0.55rem 1.1rem; font-size:0.875rem; font-weight:600; cursor:pointer; white-space:nowrap;">
                                Confirm
                            </button>
                            <button
                                wire:click="toggleForm('{{ $staff }}')"
                                style="background:none; border:1px solid #e5e7eb; border-radius:0.5rem; padding:0.55rem 0.75rem; font-size:0.875rem; color:#6b7280; cursor:pointer;">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endforeach
@endif

{{-- ── Recent reimbursement history ────────────────────────────────────── --}}
<x-filament::section style="margin-top:1.5rem;">
    <x-slot name="heading">Recent Reimbursement History</x-slot>

    @if(empty($history))
        <p style="color:#9ca3af; font-size:0.875rem;">No reimbursements recorded yet.</p>
    @else
        <table style="width:100%; font-size:0.875rem; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Date</th>
                    <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Staff</th>
                    <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Method</th>
                    <th style="text-align:right; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Amount</th>
                    <th style="text-align:left; padding:0.4rem 0.5rem; color:#6b7280; font-weight:600;">Logged by</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $payment)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.5rem; color:#374151;">{{ \Carbon\Carbon::parse($payment['payment_date'])->format('d M Y') }}</td>
                        <td style="padding:0.5rem; font-weight:600; color:#111827;">{{ $payment['staff_name'] }}</td>
                        <td style="padding:0.5rem; color:#374151;">{{ $payment['payment_method'] }}</td>
                        <td style="padding:0.5rem; text-align:right; font-weight:600; color:#dc2626;">−A${{ number_format($payment['amount'], 2) }}</td>
                        <td style="padding:0.5rem; color:#6b7280;">{{ $payment['created_by'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament::section>

</x-filament-panels::page>
