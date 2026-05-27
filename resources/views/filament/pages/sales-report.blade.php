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
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        @foreach(['day' => 'Today', 'week' => 'This Week', 'month' => 'Month', 'year' => 'Year', 'custom' => 'Custom Range'] as $key => $label)
            @if(in_array($key, ['month','year']) && ! $this->canViewExtendedPeriods())
                @continue
            @endif
            @if($key === 'custom' && ! $this->canViewCustomRange())
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

{{-- ── Custom date range picker ──────────────────────────────────── --}}
@if($period === 'custom')
@php $today = now('Pacific/Tarawa')->toDateString(); @endphp
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <label style="font-size:0.875rem;font-weight:600;color:#374151;">From</label>
            <input type="date" wire:model.live="customFrom"
                max="{{ $today }}"
                style="border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.375rem 0.625rem;font-size:0.875rem;color:#374151;cursor:pointer;">
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <label style="font-size:0.875rem;font-weight:600;color:#374151;">To</label>
            <input type="date" wire:model.live="customTo"
                max="{{ $today }}"
                style="border:1px solid #e5e7eb;border-radius:0.375rem;padding:0.375rem 0.625rem;font-size:0.875rem;color:#374151;cursor:pointer;">
        </div>
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
    $timeSeries     = $this->getRevenueTimeSeries();
    $dailyBreakdown = $this->getDailyBreakdown();
    $netProfit      = $summary['total_revenue'] - $expenses['total'];

    $prevLabel = match($period) {
        'day'    => 'vs yesterday',
        'week'   => 'vs last week',
        'month'  => 'vs last month',
        'year'   => 'vs last year',
        'custom' => 'vs previous period',
        default  => 'vs previous',
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

{{-- ── Daily breakdown (custom range only) ─────────────────────── --}}
@if($period === 'custom' && !empty($dailyBreakdown))
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">Daily reconciled cash breakdown ({{ $this->getPeriodLabel() }})</p>

    @php $grandTotal = array_sum(array_column($dailyBreakdown, 'total')); @endphp

    @foreach($dailyBreakdown as $branch)
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;margin-bottom:1.25rem;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.4rem 0.75rem;background:#f5f3ff;color:#6d28d9;font-weight:700;border-radius:0.375rem 0 0 0;border-bottom:2px solid #ede9fe;">
                    {{ $branch['branch'] }}
                </th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;border-bottom:2px solid #ede9fe;">
                    Actual Counted
                </th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;border-bottom:2px solid #ede9fe;">
                    Opening Float
                </th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6d28d9;font-weight:700;font-size:0.75rem;border-radius:0 0.375rem 0 0;border-bottom:2px solid #ede9fe;">
                    Cash on Hand
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($branch['days'] as $day)
            <tr style="border-bottom:1px solid #f9fafb;">
                <td style="padding:0.35rem 0.75rem;color:#374151;">
                    {{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}
                </td>
                <td style="padding:0.35rem 0.75rem;text-align:right;color:#6b7280;">
                    @if($day['actual'] !== null)
                        <span style="color:#d1d5db;margin-right:0.2rem;">$</span>{{ number_format($day['actual'], 2) }}
                    @endif
                </td>
                <td style="padding:0.35rem 0.75rem;text-align:right;color:#6b7280;">
                    @if($day['float'] !== null && $day['float'] > 0)
                        <span style="color:#d1d5db;margin-right:0.2rem;">−$</span>{{ number_format($day['float'], 2) }}
                    @elseif($day['actual'] !== null)
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>
                <td style="padding:0.35rem 0.75rem;text-align:right;color:#111827;font-weight:{{ $day['reconciled'] !== null ? '600' : '400' }};">
                    @if($day['reconciled'] !== null)
                        <span style="color:#9ca3af;margin-right:0.2rem;">$</span>{{ number_format($day['reconciled'], 2) }}
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="border-top:2px solid #e5e7eb;background:#f9fafb;">
                <td style="padding:0.4rem 0.75rem;font-weight:700;color:#374151;">Total</td>
                <td colspan="2"></td>
                <td style="padding:0.4rem 0.75rem;text-align:right;font-weight:700;color:#111827;">
                    <span style="color:#9ca3af;margin-right:0.2rem;">$</span>{{ number_format($branch['total'], 2) }}
                </td>
            </tr>
        </tfoot>
    </table>
    @endforeach

    @if(count($dailyBreakdown) > 1)
    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem;background:#f5f3ff;border-radius:0.5rem;border:2px solid #ede9fe;">
        <span style="font-size:0.875rem;font-weight:700;color:#6d28d9;">Total cash on Hand</span>
        <span style="font-size:1rem;font-weight:800;color:#6d28d9;">$&nbsp;{{ number_format($grandTotal, 2) }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Cup tracking (Today tab only) ────────────────────────────── --}}
@if($period === 'day')
@php $cupData = $this->getCupTrackingData(); @endphp
@if(!empty($cupData['cups']))
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1rem;">
        <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0;">🥤 Cup Inventory</p>
        <p style="font-size:0.75rem;color:#9ca3af;margin:0;">{{ now('Pacific/Tarawa')->format('d M Y') }}</p>
    </div>

    @php $missingClosing = collect($cupData['cups'])->filter(fn($c) => $c['opening'] !== null && $c['closing'] === null); @endphp
    @if($missingClosing->isNotEmpty() && now('Pacific/Tarawa')->hour >= 17)
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;padding:0.6rem 0.875rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
        <span style="font-size:1rem;">⚠️</span>
        <span style="font-size:0.8rem;color:#92400e;font-weight:600;">Closing count not yet entered for: {{ $missingClosing->pluck('name')->join(', ') }}</span>
    </div>
    @endif

    @foreach($cupData['cups'] as $cup)
    <div style="border:1px solid #f3f4f6;border-radius:0.75rem;padding:1rem;margin-bottom:0.75rem;">

        {{-- Cup type header --}}
        <p style="font-size:0.8rem;font-weight:700;color:#374151;margin:0 0 0.75rem;">{{ $cup['name'] }}</p>

        {{-- Opening / Closing row --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">

            <div>
                <label style="font-size:0.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:0.3rem;">Opening</label>
                <div style="display:flex;gap:0.4rem;">
                    <input wire:model.live="cupOpening.{{ $cup['id'] }}" type="number" min="0" step="1" placeholder="0"
                        style="width:100%;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.35rem 0.5rem;font-size:0.9rem;font-weight:600;color:#111827;outline:none;" />
                    <button wire:click="saveCupLog({{ $cup['id'] }}, 'opening')"
                        style="background:#7c3aed;color:#fff;border:none;border-radius:0.375rem;padding:0.35rem 0.6rem;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap;">Save</button>
                </div>
                @if($cup['opening'] !== null)
                    <p style="font-size:0.7rem;color:#059669;margin:0.2rem 0 0;">✓ {{ $cup['opening'] }} saved{{ $cup['opening_by'] ? ' · ' . $cup['opening_by'] : '' }}</p>
                @elseif($cup['carried'])
                    <p style="font-size:0.7rem;color:#9ca3af;font-style:italic;margin:0.2rem 0 0;">From yesterday's closing — verify &amp; save</p>
                @endif
            </div>

            <div>
                <label style="font-size:0.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:0.3rem;">Closing</label>
                <div style="display:flex;gap:0.4rem;">
                    <input wire:model.live="cupClosing.{{ $cup['id'] }}" type="number" min="0" step="1" placeholder="0"
                        style="width:100%;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.35rem 0.5rem;font-size:0.9rem;font-weight:600;color:#111827;outline:none;" />
                    <button wire:click="saveCupLog({{ $cup['id'] }}, 'closing')"
                        style="background:#7c3aed;color:#fff;border:none;border-radius:0.375rem;padding:0.35rem 0.6rem;font-size:0.75rem;font-weight:600;cursor:pointer;white-space:nowrap;">Save</button>
                </div>
                @if($cup['closing'] !== null)
                    <p style="font-size:0.7rem;color:#059669;margin:0.2rem 0 0;">✓ {{ $cup['closing'] }} saved{{ $cup['closing_by'] ? ' · ' . $cup['closing_by'] : '' }}</p>
                @endif
            </div>
        </div>

        {{-- Top-ups --}}
        <div style="border-top:1px solid #f3f4f6;padding-top:0.75rem;margin-top:0.25rem;">
            <p style="font-size:0.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">
                Top-ups
                @if($cup['topup_total'] > 0)
                    <span style="color:#7c3aed;font-weight:700;margin-left:0.4rem;">+{{ $cup['topup_total'] }} total</span>
                @endif
            </p>

            @foreach($cup['topups'] as $topup)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.25rem 0.5rem;background:#f9fafb;border-radius:0.375rem;margin-bottom:0.3rem;font-size:0.8rem;">
                <span style="color:#374151;font-weight:600;">+{{ $topup['quantity'] }} cups</span>
                <span style="color:#9ca3af;">{{ \Carbon\Carbon::parse($topup['logged_at'], 'UTC')->setTimezone('Pacific/Tarawa')->format('h:i A') }} · {{ $topup['logged_by'] }}</span>
                <button wire:click="deleteCupTopup({{ $topup['id'] }})"
                    style="background:none;border:none;color:#fca5a5;cursor:pointer;font-size:0.75rem;padding:0 0.25rem;">✕</button>
            </div>
            @endforeach

            <div style="display:flex;gap:0.4rem;margin-top:0.4rem;">
                <input wire:model.live="cupTopupQty.{{ $cup['id'] }}" type="number" min="1" step="1" placeholder="Qty"
                    style="width:6rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0.35rem 0.5rem;font-size:0.9rem;color:#111827;outline:none;" />
                <button wire:click="addCupTopup({{ $cup['id'] }})"
                    style="background:#059669;color:#fff;border:none;border-radius:0.375rem;padding:0.35rem 0.75rem;font-size:0.8rem;font-weight:600;cursor:pointer;">+ Add Top-up</button>
            </div>
        </div>

        {{-- Cups used summary --}}
        @if($cup['used'] !== null)
        <div style="margin-top:0.75rem;padding:0.6rem 0.75rem;border-radius:0.5rem;background:#f5f3ff;">
            <span style="font-size:0.8rem;color:#374151;">Cups used today: <strong style="color:#7c3aed;">{{ $cup['used'] }}</strong></span>
        </div>
        @endif

    </div>
    @endforeach
</div>
@endif
@endif

{{-- ── Cup summary (week / month / year / custom) ────────────────── --}}
@if($period !== 'day')
@php $cupSummary = $this->getCupSummary(); @endphp
@if(!empty($cupSummary))
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">🥤 Cup Usage Summary</p>
    <table style="width:100%;border-collapse:collapse;font-size:0.8125rem;">
        <thead>
            <tr style="border-bottom:2px solid #ede9fe;">
                <th style="text-align:left;padding:0.4rem 0.75rem;background:#f5f3ff;color:#6d28d9;font-weight:700;border-radius:0.375rem 0 0 0;">Cup Type</th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;">Total Used</th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;">Avg / Day</th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;">Top-ups Added</th>
                <th style="padding:0.4rem 0.75rem;text-align:right;background:#f5f3ff;color:#6b7280;font-weight:600;font-size:0.75rem;border-radius:0 0.375rem 0 0;">Days Logged</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cupSummary as $row)
            <tr style="border-bottom:1px solid #f9fafb;">
                <td style="padding:0.4rem 0.75rem;font-weight:600;color:#374151;">{{ $row['name'] }}</td>
                <td style="padding:0.4rem 0.75rem;text-align:right;font-weight:700;color:#7c3aed;">{{ $row['total_used'] }}</td>
                <td style="padding:0.4rem 0.75rem;text-align:right;color:#6b7280;">{{ $row['avg_per_day'] ?? '—' }}</td>
                <td style="padding:0.4rem 0.75rem;text-align:right;color:#059669;">{{ $row['topup_total'] > 0 ? '+' . $row['topup_total'] : '—' }}</td>
                <td style="padding:0.4rem 0.75rem;text-align:right;color:#6b7280;">{{ $row['days_logged'] }} / {{ $row['days_total'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endif

{{-- ── Sales by hour (custom range only) ────────────────────────── --}}
@if($period === 'custom')
@php $byHour = $this->getSalesByHour(); @endphp
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:1rem;">
        <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0;">Sales by Hour</p>
        <p style="font-size:0.75rem;color:#9ca3af;margin:0;">Across all days in range · Tarawa time</p>
    </div>
    <div wire:key="hour-chart-{{ $customFrom }}-{{ $customTo }}"
         x-data
         x-init="
            const labels  = {{ Js::from($byHour['labels']) }};
            const orders  = {{ Js::from($byHour['orders']) }};
            const revenue = {{ Js::from($byHour['revenue']) }};
            const max     = Math.max(...orders);
            const colors  = orders.map(v => {
                if (max === 0) return 'rgba(124,58,237,0.25)';
                const ratio = v / max;
                if (ratio >= 0.75) return '#7c3aed';
                if (ratio >= 0.4)  return '#a78bfa';
                return 'rgba(167,139,250,0.35)';
            });
            const ctx = $el.querySelector('canvas').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Orders',
                        data: orders,
                        backgroundColor: colors,
                        borderRadius: 4,
                        yAxisID: 'yOrders',
                    }, {
                        label: 'Revenue (A$)',
                        data: revenue,
                        type: 'line',
                        borderColor: '#059669',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.35,
                        yAxisID: 'yRevenue',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: true, position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label === 'Revenue (A$)'
                                    ? 'Revenue: A$' + ctx.parsed.y.toFixed(2)
                                    : 'Orders: ' + ctx.parsed.y,
                            }
                        }
                    },
                    scales: {
                        yOrders:  { position: 'left',  beginAtZero: true, ticks: { stepSize: 1, color: '#7c3aed' }, grid: { color: '#f3f4f6' } },
                        yRevenue: { position: 'right', beginAtZero: true, ticks: { callback: v => 'A$' + v, color: '#059669' }, grid: { display: false } },
                        x: { grid: { display: false } }
                    }
                }
            });
         ">
        <canvas style="max-height:240px;"></canvas>
    </div>

    {{-- Busy / Quiet summary --}}
    @php
        $maxOrders  = max($byHour['orders'] ?: [0]);
        $busyHours  = collect($byHour['orders'])->map(fn($v,$i) => ['h'=>$i,'v'=>$v])->sortByDesc('v')->take(3)->sortBy('h')->pluck('h')->map(fn($i) => $byHour['labels'][$i]);
        $quietHours = collect($byHour['orders'])->filter(fn($v) => $v > 0)->map(fn($v,$i) => ['h'=>$i,'v'=>$v])->sortBy('v')->take(3)->sortBy('h')->pluck('h')->map(fn($i) => $byHour['labels'][$i]);
    @endphp
    @if($maxOrders > 0)
    <div style="display:flex;gap:1.5rem;margin-top:1rem;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="font-size:1rem;">🔥</span>
            <span style="font-size:0.8rem;font-weight:600;color:#374151;">Busiest:</span>
            <span style="font-size:0.8rem;color:#7c3aed;font-weight:700;">{{ $busyHours->implode(', ') }}</span>
        </div>
        @if($quietHours->isNotEmpty())
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="font-size:1rem;">🌙</span>
            <span style="font-size:0.8rem;font-weight:600;color:#374151;">Quietest:</span>
            <span style="font-size:0.8rem;color:#9ca3af;font-weight:700;">{{ $quietHours->implode(', ') }}</span>
        </div>
        @endif
    </div>
    @endif
</div>
@endif

{{-- ── Three-column: Expenses + Float + Topups ─────────────────── --}}
@if($period !== 'custom')
<div style="display:grid;grid-template-columns:{{ $period === 'day' ? '1fr 1fr 1fr' : '1fr 1fr' }};gap:1.25rem;margin-bottom:1.25rem;">

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

    {{-- Opening Float (Today only) --}}
    @if($period === 'day')
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:0.875rem;padding:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <p style="font-size:0.875rem;font-weight:700;color:#1d4ed8;margin:0 0 0.75rem;">💰 Opening Float</p>
        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.5rem;">
            <input wire:model.live="floatAmount" type="number" min="0" step="0.01" placeholder="0.00"
                style="width:8rem;border:1px solid #93c5fd;border-radius:0.375rem;padding:0.4rem 0.6rem;font-size:0.95rem;font-weight:700;color:#1e40af;background:#fff;outline:none;" />
            <button wire:click="saveFloat"
                style="background:#1d4ed8;color:#fff;border:none;border-radius:0.375rem;padding:0.4rem 0.85rem;font-size:0.8rem;font-weight:600;cursor:pointer;">
                Save
            </button>
            @if($float['total'] > 0)
                <span style="font-size:0.85rem;color:#1e40af;font-weight:600;">A${{ number_format($float['total'], 2) }} set</span>
            @else
                <span style="font-size:0.8rem;color:#93c5fd;">Not set</span>
            @endif
        </div>
        @if($floatSetBy)
            <p style="font-size:0.7rem;color:#93c5fd;margin:0;">Set by {{ $floatSetBy }}{{ $floatUpdatedAt ? ' · '.$floatUpdatedAt : '' }}</p>
        @endif
        <p style="font-size:0.7rem;color:#3b82f6;margin:0.35rem 0 0;">Reference only · not included in revenue</p>
    </div>
    @endif

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
@endif

{{-- ── Revenue trend chart ──────────────────────────────────────── --}}
<div style="background:white;border:1px solid #e5e7eb;border-radius:0.875rem;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
    <p style="font-size:0.875rem;font-weight:700;color:#111827;margin:0 0 1rem;">Revenue - {{ $this->getPeriodLabel() }}</p>
    <div wire:key="chart-{{ $period }}-{{ $selectedMonth }}-{{ $selectedYear }}-{{ $customFrom }}-{{ $customTo }}"
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


</x-filament-panels::page>
