<x-filament-panels::page>

@php
    $btnBase   = 'padding:0.45rem 1.1rem; border-radius:0.5rem; font-size:0.875rem; font-weight:600; border:2px solid; cursor:pointer; transition:all .15s;';
    $btnActive = $btnBase . 'background:#7c3aed; color:#fff; border-color:#7c3aed;';
    $btnInact  = $btnBase . 'background:#fff; color:#374151; border-color:#e5e7eb;';

    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    $statusColors = [
        'Pending Payment'      => '#d97706',
        'Payment Verification' => '#2563eb',
        'Points Verification'  => '#2563eb',
        'Paid'                 => '#059669',
        'Preparing'            => '#7c3aed',
        'Ready'                => '#059669',
        'Cancelled'            => '#dc2626',
    ];
@endphp

{{-- ── Period tabs ──────────────────────────────────────────────────── --}}
<div style="display:flex; gap:0.5rem; margin-bottom:1.25rem;">
    @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $key => $label)
        @if(in_array($key, ['month', 'year']) && ! $this->canViewExtendedPeriods())
            @continue
        @endif
        <button wire:click="$set('period','{{ $key }}')"
                style="{{ $period === $key ? $btnActive : $btnInact }}">
            {{ $label }}
        </button>
    @endforeach
</div>

{{-- ── Month picker ─────────────────────────────────────────────────── --}}
@if($period === 'month')
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:0.75rem; padding:1rem 1.25rem; margin-bottom:1.25rem;">
        {{-- Year navigation --}}
        @php
            $availableYears = $this->getAvailableYears();
            $minYear        = min($availableYears);
            $maxYear        = max($availableYears);
            $atMinYear      = $selectedYear <= $minYear;
            $atMaxYear      = $selectedYear >= $maxYear;
        @endphp
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:0.75rem;">
            <button
                @if(! $atMinYear) wire:click="previousYear" @endif
                {{ $atMinYear ? 'disabled' : '' }}
                style="background:none; border:1px solid #e5e7eb; border-radius:0.375rem; padding:0.25rem 0.6rem; font-size:1rem;
                       {{ $atMinYear ? 'opacity:0.3; cursor:not-allowed; color:#9ca3af;' : 'cursor:pointer; color:#374151;' }}">‹</button>

            <span style="font-weight:700; font-size:1rem; color:#111827;">{{ $selectedYear }}</span>

            <button
                @if(! $atMaxYear) wire:click="nextYear" @endif
                {{ $atMaxYear ? 'disabled' : '' }}
                style="background:none; border:1px solid #e5e7eb; border-radius:0.375rem; padding:0.25rem 0.6rem; font-size:1rem;
                       {{ $atMaxYear ? 'opacity:0.3; cursor:not-allowed; color:#9ca3af;' : 'cursor:pointer; color:#374151;' }}">›</button>
        </div>
        {{-- Month grid --}}
        <div style="display:grid; grid-template-columns:repeat(6,1fr); gap:0.5rem;">
            @foreach($months as $i => $m)
                @php
                    $monthNum  = $i + 1;
                    $isFuture  = $selectedYear > now()->year
                                 || ($selectedYear === now()->year && $monthNum > now()->month);
                    $isActive  = $selectedMonth === $monthNum && ! $isFuture;
                @endphp
                <button
                    @if(! $isFuture) wire:click="$set('selectedMonth', {{ $monthNum }})" @endif
                    {{ $isFuture ? 'disabled' : '' }}
                    style="
                        {{ $isActive  ? $btnActive : $btnInact }}
                        padding:0.4rem 0; text-align:center; width:100%;
                        {{ $isFuture ? 'opacity:0.35; cursor:not-allowed; border-color:#e5e7eb; background:#f9fafb; color:#9ca3af;' : '' }}
                    "
                >{{ $m }}</button>
            @endforeach
        </div>
    </div>
@endif

{{-- ── Year picker ──────────────────────────────────────────────────── --}}
@if($period === 'year')
    <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.25rem;">
        @foreach($this->getAvailableYears() as $y)
            <button wire:click="$set('selectedYear', {{ $y }})"
                    style="{{ ($selectedYear === $y) ? $btnActive : $btnInact }}">
                {{ $y }}
            </button>
        @endforeach
    </div>
@endif

@php
    $summary  = $this->getSummary();
    $payments = $this->getPaymentBreakdown();
    $flavors  = $this->getTopFlavors();
    $statuses = $this->getStatusBreakdown();
    $expenses = $this->getExpenseSummary();

    $netProfit = $summary['total_revenue'] - $expenses['total'];

    $statCards = [
        ['label' => 'Total Revenue',   'value' => 'A$' . number_format($summary['total_revenue'], 2),   'color' => '#059669'],
        ['label' => 'Total Orders',    'value' => $summary['total_orders'],                              'color' => '#2563eb'],
        ['label' => 'Avg Order Value', 'value' => 'A$' . number_format($summary['avg_order_value'], 2), 'color' => '#7c3aed'],
        ['label' => 'Collected',       'value' => $summary['collected_count'],                           'color' => '#059669'],
    ];
@endphp

{{-- ── Summary cards ────────────────────────────────────────────────── --}}
<x-filament::section>
    <x-slot name="heading">Summary — {{ $this->getPeriodLabel() }}</x-slot>

    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1rem;">
        @foreach($statCards as $card)
            <div style="border:1px solid #e5e7eb; border-radius:0.75rem; background:#fff; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.06);">
                <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.35rem;">{{ $card['label'] }}</p>
                <p style="font-size:1.75rem; font-weight:700; color:{{ $card['color'] }}; margin:0; line-height:1.2;">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Expenses & Net Profit bar ──────────────────────────────────── --}}
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-top:1rem; padding-top:1rem; border-top:1px solid #f3f4f6;">
        <div style="border:1px solid #fee2e2; border-radius:0.75rem; background:#fff7f7; padding:1.25rem;">
            <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.35rem;">Total Expenses</p>
            <p style="font-size:1.75rem; font-weight:700; color:#dc2626; margin:0; line-height:1.2;">A${{ number_format($expenses['total'], 2) }}</p>
        </div>
        <div></div>
        <div style="border:1px solid {{ $netProfit >= 0 ? '#d1fae5' : '#fee2e2' }}; border-radius:0.75rem; background:{{ $netProfit >= 0 ? '#f0fdf4' : '#fff7f7' }}; padding:1.25rem;">
            <p style="font-size:0.8rem; color:#6b7280; margin:0 0 0.35rem;">Net Profit</p>
            <p style="font-size:1.75rem; font-weight:700; color:{{ $netProfit >= 0 ? '#059669' : '#dc2626' }}; margin:0; line-height:1.2;">A${{ number_format(abs($netProfit), 2) }}{{ $netProfit < 0 ? ' loss' : '' }}</p>
        </div>
    </div>
</x-filament::section>

{{-- ── Two-column breakdown ─────────────────────────────────────────── --}}
<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-top:1.5rem;">

    <x-filament::section>
        <x-slot name="heading">Revenue by Payment Method</x-slot>
        @if(empty($payments))
            <p style="color:#9ca3af; font-size:0.875rem;">No orders for this period.</p>
        @else
            <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                <thead>
                    <tr style="border-bottom:2px solid #f3f4f6;">
                        <th style="text-align:left; padding:0.5rem 0; color:#6b7280; font-weight:600;">Method</th>
                        <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Orders</th>
                        <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $row)
                        <tr style="border-bottom:1px solid #f9fafb;">
                            <td style="padding:0.6rem 0; font-weight:500;">{{ $row['payment_method'] }}</td>
                            <td style="padding:0.6rem 0; text-align:right; color:#4b5563;">{{ $row['orders'] }}</td>
                            <td style="padding:0.6rem 0; text-align:right; font-weight:600; color:#059669;">A${{ number_format($row['revenue'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Orders by Status</x-slot>
        @if(empty($statuses))
            <p style="color:#9ca3af; font-size:0.875rem;">No orders for this period.</p>
        @else
            <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
                <thead>
                    <tr style="border-bottom:2px solid #f3f4f6;">
                        <th style="text-align:left; padding:0.5rem 0; color:#6b7280; font-weight:600;">Status</th>
                        <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statuses as $row)
                        @php $sc = $statusColors[$row['order_status']] ?? '#6b7280'; @endphp
                        <tr style="border-bottom:1px solid #f9fafb;">
                            <td style="padding:0.6rem 0;">
                                <span style="display:inline-block; padding:0.15rem 0.6rem; border-radius:9999px; font-size:0.75rem; font-weight:600; background:{{ $sc }}1a; color:{{ $sc }};">
                                    {{ $row['order_status'] }}
                                </span>
                            </td>
                            <td style="padding:0.6rem 0; text-align:right; font-weight:600; color:#111827;">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

</div>

{{-- ── Expenses breakdown ───────────────────────────────────────────── --}}
<x-filament::section style="margin-top:1.5rem;">
    <x-slot name="heading">Expenses by Category — {{ $this->getPeriodLabel() }}</x-slot>
    @if(empty($expenses['rows']))
        <p style="color:#9ca3af; font-size:0.875rem;">No expenses logged for this period.</p>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
            <thead>
                <tr style="border-bottom:2px solid #f3f4f6;">
                    <th style="text-align:left; padding:0.5rem 0; color:#6b7280; font-weight:600;">Category</th>
                    <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Total</th>
                    <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">% of Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenses['rows'] as $row)
                    @php
                        $pct = $summary['total_revenue'] > 0
                            ? round(($row['total'] / $summary['total_revenue']) * 100, 1)
                            : 0;
                        $catColors = ['Ingredients'=>'#d97706','Supplies'=>'#2563eb','Equipment'=>'#7c3aed'];
                        $cc = $catColors[$row['category']] ?? '#6b7280';
                    @endphp
                    <tr style="border-bottom:1px solid #f9fafb;">
                        <td style="padding:0.6rem 0;">
                            <span style="display:inline-block; padding:0.15rem 0.6rem; border-radius:9999px; font-size:0.75rem; font-weight:600; background:{{ $cc }}1a; color:{{ $cc }};">
                                {{ $row['category'] }}
                            </span>
                        </td>
                        <td style="padding:0.6rem 0; text-align:right; font-weight:600; color:#dc2626;">A${{ number_format($row['total'], 2) }}</td>
                        <td style="padding:0.6rem 0; text-align:right; color:#6b7280;">{{ $pct }}%</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding:0.75rem 0; font-weight:700; color:#111827;">Total</td>
                    <td style="padding:0.75rem 0; text-align:right; font-weight:700; color:#dc2626;">A${{ number_format($expenses['total'], 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endif
</x-filament::section>

{{-- ── Top flavors ──────────────────────────────────────────────────── --}}
<x-filament::section style="margin-top:1.5rem;">
    <x-slot name="heading">Top Flavors — {{ $this->getPeriodLabel() }}</x-slot>
    @if(empty($flavors))
        <p style="color:#9ca3af; font-size:0.875rem;">No orders for this period.</p>
    @else
        <table style="width:100%; border-collapse:collapse; font-size:0.875rem;">
            <thead>
                <tr style="border-bottom:2px solid #f3f4f6;">
                    <th style="text-align:left; padding:0.5rem 0; color:#6b7280; font-weight:600; width:2rem;">#</th>
                    <th style="text-align:left; padding:0.5rem 0; color:#6b7280; font-weight:600;">Flavor</th>
                    <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Qty Sold</th>
                    <th style="text-align:right; padding:0.5rem 0; color:#6b7280; font-weight:600;">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($flavors as $i => $row)
                    <tr style="border-bottom:1px solid #f9fafb;">
                        <td style="padding:0.6rem 0; color:#9ca3af; font-size:0.75rem;">{{ $i + 1 }}</td>
                        <td style="padding:0.6rem 0; font-weight:500;">{{ $row->name }}</td>
                        <td style="padding:0.6rem 0; text-align:right; color:#4b5563;">{{ $row->qty }}</td>
                        <td style="padding:0.6rem 0; text-align:right; font-weight:600; color:#059669;">A${{ number_format($row->revenue, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-filament::section>

</x-filament-panels::page>
