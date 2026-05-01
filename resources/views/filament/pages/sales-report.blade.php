<x-filament-panels::page>

{{-- Chart.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    window.addEventListener('download-csv', function (e) {
        const encoder  = new TextEncoder();
        const bom      = new Uint8Array([0xEF, 0xBB, 0xBF]);
        const body     = encoder.encode(e.detail.content);
        const combined = new Uint8Array(bom.length + body.length);
        combined.set(bom);
        combined.set(body, bom.length);
        const blob = new Blob([combined], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = e.detail.filename;
        a.click();
        URL.revokeObjectURL(url);
    });
</script>


@php
    $months    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $btnBase   = 'padding:0.4rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid;cursor:pointer;';
    $btnActive = $btnBase . 'background:#7c3aed;color:#fff;border-color:#7c3aed;';
    $btnInact  = $btnBase . 'background:#fff;color:#374151;border-color:#e5e7eb;';
@endphp

{{-- ── Top bar: period tabs + export ───────────────────────────── --}}
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
    <div style="display:flex;gap:0.5rem;">
        @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $key => $label)
            @if(in_array($key, ['month','year']) && ! $this->canViewExtendedPeriods())
                @continue
            @endif
            <button wire:click="$set('period','{{ $key }}')" style="{{ $period === $key ? $btnActive : $btnInact }}">{{ $label }}</button>
        @endforeach
    </div>
    <button wire:click="exportCsv"
        style="padding:0.4rem 1rem;border-radius:0.5rem;font-size:0.875rem;font-weight:600;border:2px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;display:flex;align-items:center;gap:0.4rem;">
        ⬇ Export CSV
    </button>
</div>

{{-- ── Month picker ─────────────────────────────────────────────── --}}
@if($period === 'month')
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;">
    @php
        $availableYears = $this->getAvailableYears();
        $atMinYear      = $selectedYear <= min($availableYears);
        $atMaxYear      = $selectedYear >= max($availableYears);
    @endphp
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem;">
        <button @if(!$atMinYear) wire:click="previousYear" @endif {{ $atMinYear ? 'disabled' : '' }}
            style="background:none;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.25rem 0.6rem;font-size:1rem;{{ $atMinYear ? 'opacity:0.3;cursor:not-allowed;' : 'cursor:pointer;' }}">‹</button>
        <span style="font-weight:700;font-size:1rem;color:#111827;">{{ $selectedYear }}</span>
        <button @if(!$atMaxYear) wire:click="nextYear" @endif {{ $atMaxYear ? 'disabled' : '' }}
            style="background:none;border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.25rem 0.6rem;font-size:1rem;{{ $atMaxYear ? 'opacity:0.3;cursor:not-allowed;' : 'cursor:pointer;' }}">›</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:0.5rem;">
        @foreach($months as $i => $m)
            @php
                $mn       = $i + 1;
                $isFuture = $selectedYear > now('Pacific/Tarawa')->year || ($selectedYear === now('Pacific/Tarawa')->year && $mn > now('Pacific/Tarawa')->month);
                $isActive = $selectedMonth === $mn && !$isFuture;
            @endphp
            <button @if(!$isFuture) wire:click="$set('selectedMonth',{{ $mn }})" @endif {{ $isFuture ? 'disabled' : '' }}
                style="{{ $isActive ? $btnActive : $btnInact }}padding:0.4rem 0;text-align:center;width:100%;{{ $isFuture ? 'opacity:0.35;cursor:not-allowed;' : '' }}">{{ $m }}</button>
        @endforeach
    </div>
</div>
@endif

{{-- ── Year picker ───────────────────────────────────────────────── --}}
@if($period === 'year')
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    @foreach($this->getAvailableYears() as $y)
        <button wire:click="$set('selectedYear',{{ $y }})" style="{{ $selectedYear === $y ? $btnActive : $btnInact }}">{{ $y }}</button>
    @endforeach
</div>
@endif

@php
    $summary    = $this->getSummary();
    $prev       = $this->getComparisonSummary();
    $payments   = $this->getPaymentBreakdown();
    $flavors    = $this->getTopFlavors();
    $expenses   = $this->getExpenseSummary();
    $float      = $this->getFloatSummary();
    $topups     = $this->getTopupSummary();
    $timeSeries = $this->getRevenueTimeSeries();
    $netProfit  = $summary['total_revenue'] - $expenses['total'];

    $prevLabel = match($period) {
        'day'   => 'vs yesterday',
        'week'  => 'vs last week',
        'month' => 'vs last month',
        'year'  => 'vs last year',
        default => 'vs previous',
    };

    $pctChange = fn(float $now, float $prev): ?float =>
        $prev > 0 ? round((($now - $prev) / $prev) * 100, 1) : null;

    $revChange  = $pctChange($summary['total_revenue'],   $prev['total_revenue']);
    $ordChange  = $pctChange($summary['total_orders'],    $prev['total_orders']);
    $avgChange  = $pctChange($summary['avg_order_value'], $prev['avg_order_value']);

    $maxPayRev  = collect($payments)->max('revenue') ?: 1;
    $maxFlavQty = collect($flavors)->max('qty') ?: 1;
@endphp

{{-- ── Summary cards ────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem;">

    @php
    $summaryCards = [
        ['label'=>'Revenue','value'=>'A$'.number_format($summary['total_revenue'],2),'color'=>'#059669','change'=>$revChange],
        ['label'=>'Orders','value'=>$summary['total_orders'],'color'=>'#2563eb','change'=>$ordChange],
        ['label'=>'Avg Order','value'=>'A$'.number_format($summary['avg_order_value'],2),'color'=>'#7c3aed','change'=>$avgChange],
        ['label'=>'Net Profit','value'=>'A$'.number_format(abs($netProfit),2).($netProfit<0?' loss':''),'color'=>$netProfit>=0?'#059669':'#dc2626','change'=>null],
    ];
    @endphp

    @foreach($summaryCards as $card)
    <div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <p style="font-size:0.75rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.4rem;">{{ $card['label'] }}</p>
        <p style="font-size:1.625rem;font-weight:800;color:{{ $card['color'] }};margin:0;line-height:1.2;">{{ $card['value'] }}</p>
        @if($card['change'] !== null)
            @php $up = $card['change'] >= 0; @endphp
            <p style="font-size:0.75rem;margin:0.35rem 0 0;color:{{ $up ? '#059669' : '#dc2626' }};">
                {{ $up ? '▲' : '▼' }} {{ abs($card['change']) }}% <span style="color:#9ca3af;">{{ $prevLabel }}</span>
            </p>
        @else
            <p style="font-size:0.75rem;margin:0.35rem 0 0;color:#9ca3af;">Revenue − Expenses</p>
        @endif
    </div>
    @endforeach
</div>

{{-- ── Revenue trend chart ──────────────────────────────────────── --}}
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">Revenue - {{ $this->getPeriodLabel() }}</p>
    <div wire:key="chart-{{ $period }}-{{ $selectedMonth }}-{{ $selectedYear }}"
         x-data
         x-init="
            const ctx = $el.querySelector('canvas').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: {{ Js::from($timeSeries['labels']) }},
                    datasets: [{
                        label: 'Revenue (A$)',
                        data: {{ Js::from($timeSeries['data']) }},
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124,58,237,0.08)',
                        borderWidth: 2.5,
                        pointRadius: {{ count($timeSeries['labels']) > 20 ? 0 : 3 }},
                        pointHoverRadius: 5,
                        fill: true,
                        tension: 0.35,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => 'A$' + v.toFixed(0) },
                            grid: { color: '#f3f4f6' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
         ">
        <canvas style="max-height:220px;"></canvas>
    </div>
</div>

{{-- ── Two-column: Payment methods + Top items ──────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

    {{-- Payment Methods --}}
    <div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">Revenue by Payment Method</p>
        @if(empty($payments))
            <p style="color:#9ca3af;font-size:0.875rem;">No orders for this period.</p>
        @else
            @foreach($payments as $row)
                @php $barPct = $maxPayRev > 0 ? round(($row['revenue'] / $maxPayRev) * 100) : 0; @endphp
                <div style="margin-bottom:0.875rem;">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.25rem;">
                        <span style="font-size:0.8rem;font-weight:600;color:#374151;">{{ $row['payment_method'] }}</span>
                        <span style="font-size:0.8rem;font-weight:700;color:#059669;">A${{ number_format($row['revenue'],2) }}</span>
                    </div>
                    <div style="background:#f3f4f6;border-radius:9999px;height:6px;width:100%;">
                        <div style="background:#7c3aed;border-radius:9999px;height:6px;width:{{ $barPct }}%;"></div>
                    </div>
                    <p style="font-size:0.7rem;color:#9ca3af;margin:0.2rem 0 0;">{{ $row['orders'] }} order{{ $row['orders'] != 1 ? 's' : '' }}</p>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Top Items --}}
    <div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">Top Items</p>
        @if(empty($flavors))
            <p style="color:#9ca3af;font-size:0.875rem;">No orders for this period.</p>
        @else
            @foreach($flavors as $i => $row)
                @php $barPct = $maxFlavQty > 0 ? round(($row->qty / $maxFlavQty) * 100) : 0; @endphp
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                    <span style="width:1.25rem;text-align:center;font-size:0.7rem;font-weight:700;color:#9ca3af;flex-shrink:0;">{{ $i + 1 }}</span>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.2rem;">
                            <span style="font-size:0.8rem;font-weight:600;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%;">{{ $row->name }}</span>
                            <span style="font-size:0.8rem;color:#6b7280;flex-shrink:0;">{{ $row->qty }} sold</span>
                        </div>
                        <div style="background:#f3f4f6;border-radius:9999px;height:5px;width:100%;">
                            <div style="background:#059669;border-radius:9999px;height:5px;width:{{ $barPct }}%;"></div>
                        </div>
                    </div>
                    <span style="font-size:0.75rem;font-weight:700;color:#059669;flex-shrink:0;width:4.5rem;text-align:right;">A${{ number_format($row->revenue,2) }}</span>
                </div>
            @endforeach
        @endif
    </div>
</div>

{{-- ── Three-column: Expenses + Float + Topups ─────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

    {{-- Expenses --}}
    <div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.875rem;">
            <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0;">Expenses</p>
            <p style="font-size:1rem;font-weight:800;color:#dc2626;margin:0;">A${{ number_format($expenses['total'],2) }}</p>
        </div>
        @if(empty($expenses['rows']))
            <p style="color:#9ca3af;font-size:0.8rem;">No expenses logged.</p>
        @else
            @foreach($expenses['rows'] as $row)
                @php $pct = $summary['total_revenue'] > 0 ? round(($row['total']/$summary['total_revenue'])*100,1) : 0; @endphp
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0;border-bottom:1px solid #f9fafb;">
                    <span style="font-size:0.8rem;color:#374151;">{{ $row['category'] }}</span>
                    <div style="text-align:right;">
                        <span style="font-size:0.8rem;font-weight:600;color:#dc2626;">A${{ number_format($row['total'],2) }}</span>
                        <span style="font-size:0.7rem;color:#9ca3af;margin-left:0.25rem;">{{ $pct }}%</span>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Opening Float --}}
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <p style="font-size:0.875rem;font-weight:700;color:#1d4ed8;margin:0 0 0.5rem;">💰 Opening Float</p>
        <p style="font-size:1.5rem;font-weight:800;color:#1d4ed8;margin:0;">{{ $float['total'] > 0 ? 'A$'.number_format($float['total'],2) : '—' }}</p>
        <p style="font-size:0.7rem;color:#3b82f6;margin:0.5rem 0 0;">Reference only · not included in revenue</p>
    </div>

    {{-- Wallet Top-ups --}}
    <div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.875rem;">
            <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0;">Wallet Top-ups</p>
            <p style="font-size:1rem;font-weight:800;color:#7c3aed;margin:0;">A${{ number_format($topups['total'],2) }}</p>
        </div>
        @if(empty($topups['rows']))
            <p style="color:#9ca3af;font-size:0.8rem;">No top-ups this period.</p>
        @else
            @foreach($topups['rows'] as $row)
                <div style="display:flex;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid #f9fafb;">
                    <span style="font-size:0.8rem;color:#374151;">{{ $row['payment_method'] }}</span>
                    <div style="text-align:right;">
                        <span style="font-size:0.8rem;font-weight:600;color:#7c3aed;">A${{ number_format($row['total'],2) }}</span>
                        <span style="font-size:0.7rem;color:#9ca3af;margin-left:0.25rem;">×{{ $row['count'] }}</span>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>

</x-filament-panels::page>
