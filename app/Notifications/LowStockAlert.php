<?php

namespace App\Notifications;

use App\Models\StockItem;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    public function __construct(private StockItem $item) {}

    public function via(mixed $notifiable): array
    {
        return [\App\Channels\ResendChannel::class];
    }

    public function toResend(mixed $notifiable): array
    {
        $item    = $this->item;
        $current = number_format((float) $item->current_quantity, 2);
        $min     = number_format((float) $item->min_quantity, 2);
        $status  = $item->current_quantity <= 0 ? 'Out of Stock' : 'Low Stock';
        $color   = $item->current_quantity <= 0 ? '#dc2626' : '#d97706';
        $url     = config('app.url') . '/admin/stock-items/' . $item->id . '/edit';

        return [
            'from'    => "Vicky's Bubble-Fruit Tea <noreply@vickysbubbletea.com>",
            'to'      => ['charlie.hongkai@gmail.com'],
            'subject' => "[{$status}] {$item->name} needs restocking",
            'html'    => "
                <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
                    <div style='background:#7E57C2;padding:20px 24px;border-radius:12px 12px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>🥤 Stock Alert</h2>
                        <p style='color:#e9d5ff;margin:4px 0 0;font-size:0.9rem;'>Vicky's Bubble-Fruit Tea</p>
                    </div>
                    <div style='background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                        <div style='background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-bottom:20px;'>
                            <span style='font-weight:700;color:{$color};font-size:1rem;'>{$status}: {$item->name}</span>
                        </div>

                        <table style='width:100%;border-collapse:collapse;font-size:0.9rem;'>
                            <tr style='border-bottom:1px solid #f3f4f6;'>
                                <td style='padding:8px 0;color:#6b7280;'>Category</td>
                                <td style='padding:8px 0;font-weight:600;color:#111827;'>{$item->category}</td>
                            </tr>
                            <tr style='border-bottom:1px solid #f3f4f6;'>
                                <td style='padding:8px 0;color:#6b7280;'>Current Stock</td>
                                <td style='padding:8px 0;font-weight:700;color:{$color};'>{$current} {$item->unit}</td>
                            </tr>
                            <tr>
                                <td style='padding:8px 0;color:#6b7280;'>Reorder Point</td>
                                <td style='padding:8px 0;font-weight:600;color:#111827;'>{$min} {$item->unit}</td>
                            </tr>
                        </table>

                        <a href='{$url}'
                           style='display:inline-block;margin-top:20px;padding:12px 24px;background:#7E57C2;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>
                            View in Admin Panel
                        </a>
                        <p style='color:#6b7280;font-size:0.8rem;margin-top:16px;'>This alert was triggered automatically when the stock level reached the reorder point.</p>
                    </div>
                </div>
            ",
        ];
    }
}
