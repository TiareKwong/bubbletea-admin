<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\BranchContext;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class OrderSearchWidget extends Widget
{
    protected string $view = 'filament.widgets.order-search';

    protected static ?int $sort = 0; // Show above the stats

    protected int|string|array $columnSpan = 'full';

    public string $orderCode = '';
    public ?array $result    = null;
    public ?string $error    = null;

    public function search(): void
    {
        $this->error  = null;
        $this->result = null;

        $code = strtoupper(trim($this->orderCode));
        if (blank($code)) return;

        $order = Order::with(['user', 'branch'])
            ->where('order_code', 'LIKE', '%' . $code . '%')
            ->first();

        if (! $order) {
            $this->error = "No order found with code \"{$code}\".";
            return;
        }

        $activeBranchId = app(BranchContext::class)->getId();
        $isOtherBranch  = $activeBranchId && (int) $order->branch_id !== $activeBranchId;

        $this->result = [
            'id'           => $order->id,
            'order_code'   => $order->order_code,
            'customer'     => trim(($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? '')),
            'status'       => $order->order_status,
            'total'        => number_format((float) $order->total_price, 2),
            'payment'      => $order->payment_method,
            'branch'       => $order->branch?->name ?? '—',
            'is_other_branch' => $isOtherBranch,
            'url'          => route('filament.admin.resources.orders.view', ['record' => $order->id]),
        ];
    }

    public function clear(): void
    {
        $this->orderCode = '';
        $this->result    = null;
        $this->error     = null;
    }
}
