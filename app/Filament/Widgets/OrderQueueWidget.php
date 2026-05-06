<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Services\BranchContext;
use App\Services\PushNotificationService;
use Filament\Widgets\Widget;

class OrderQueueWidget extends Widget
{
    protected string $view = 'filament.widgets.order-queue';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected function getViewData(): array
    {
        $branchId = app(BranchContext::class)->getId();

        $query = Order::with(['user', 'branch', 'orderItems.flavor'])
            ->whereIn('order_status', ['Paid', 'Preparing', 'Ready'])
            ->where('collected', false)
            ->orderBy('created_at', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get();

        return [
            'orders'    => $orders,
            'paid'      => $orders->where('order_status', 'Paid'),
            'preparing' => $orders->where('order_status', 'Preparing'),
            'ready'     => $orders->where('order_status', 'Ready'),
        ];
    }

    public function markPreparing(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || $order->order_status !== 'Paid') {
            return;
        }

        $staffName = auth()->user()->getFilamentName();
        $order->order_status = 'Preparing';
        $order->updated_by   = $staffName;
        $order->appendStatusLog('Started Preparing', $staffName);
        $order->save();

        PushNotificationService::sendLocalized($order->user_id, 'order_preparing', $order->order_code);
    }

    public function markReady(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || $order->order_status !== 'Preparing') {
            return;
        }

        $staffName = auth()->user()->getFilamentName();
        $order->order_status = 'Ready';
        $order->updated_by   = $staffName;
        $order->appendStatusLog('Marked Ready', $staffName);
        $order->save();

        PushNotificationService::sendLocalized($order->user_id, 'order_ready', $order->order_code);
    }

    public function markCollected(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || $order->order_status !== 'Ready') {
            return;
        }

        $staffName = auth()->user()->getFilamentName();
        $order->order_status = 'Collected';
        $order->collected    = true;
        $order->updated_by   = $staffName;
        $order->appendStatusLog('Marked Collected', $staffName);
        $order->save();

        PushNotificationService::sendLocalized($order->user_id, 'order_collected', $order->order_code);
    }
}
