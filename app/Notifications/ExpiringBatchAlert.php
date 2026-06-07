<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class ExpiringBatchAlert extends Notification
{
    public function __construct(private Collection $batches) {}

    public function via(mixed $notifiable): array
    {
        return [\App\Channels\ResendChannel::class];
    }

    public function toResend(mixed $notifiable): array
    {
        $count = $this->batches->count();
        $url   = config('app.url') . '/admin/stock-items';
        $today = now('Pacific/Tarawa')->startOfDay();

        $rows = $this->batches->map(function ($batch) use ($today) {
            $daysLeft = (int) $today->diffInDays($batch->expiry_date->startOfDay(), false);
            $qty      = rtrim(rtrim(number_format((float) $batch->quantity, 2), '0'), '.');
            $color    = $daysLeft <= 7 ? '#dc2626' : '#d97706';
            $label    = $daysLeft === 0 ? 'Today' : ($daysLeft === 1 ? '1 day' : "{$daysLeft} days");

            return "
                <tr style='border-bottom:1px solid #f3f4f6;'>
                    <td style='padding:10px 8px;color:#111827;font-weight:600;'>{$batch->stockItem->name}</td>
                    <td style='padding:10px 8px;color:#374151;'>{$batch->expiry_date->format('d M Y')}</td>
                    <td style='padding:10px 8px;font-weight:700;color:{$color};'>{$label}</td>
                    <td style='padding:10px 8px;color:#374151;'>{$qty} {$batch->stockItem->unit}</td>
                </tr>";
        })->join('');

        $subject = $count === 1
            ? '[Expiry Alert] 1 batch expiring within 30 days'
            : "[Expiry Alert] {$count} batches expiring within 30 days";

        return [
            'from'    => "Vicky's Bubble-Fruit Tea <noreply@vickysbubbletea.com>",
            'to'      => ['vickys.bubble.tea@gmail.com'],
            'subject' => $subject,
            'html'    => "
                <div style='font-family:sans-serif;max-width:580px;margin:auto;'>
                    <div style='background:#7E57C2;padding:20px 24px;border-radius:12px 12px 0 0;'>
                        <h2 style='color:#fff;margin:0;'>🥤 Expiry Alert</h2>
                        <p style='color:#e9d5ff;margin:4px 0 0;font-size:0.9rem;'>Vicky's Bubble-Fruit Tea</p>
                    </div>
                    <div style='background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;'>
                        <p style='color:#374151;margin:0 0 16px;'>
                            The following stock batches are expiring within the next <strong>30 days</strong>.
                            Please use or dispatch them before they expire.
                        </p>

                        <table style='width:100%;border-collapse:collapse;font-size:0.875rem;'>
                            <thead>
                                <tr style='border-bottom:2px solid #e5e7eb;'>
                                    <th style='padding:8px;text-align:left;color:#6b7280;font-weight:600;'>Item</th>
                                    <th style='padding:8px;text-align:left;color:#6b7280;font-weight:600;'>Expiry Date</th>
                                    <th style='padding:8px;text-align:left;color:#6b7280;font-weight:600;'>Days Left</th>
                                    <th style='padding:8px;text-align:left;color:#6b7280;font-weight:600;'>Qty Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$rows}
                            </tbody>
                        </table>

                        <a href='{$url}'
                           style='display:inline-block;margin-top:24px;padding:12px 24px;background:#7E57C2;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>
                            View Stock
                        </a>
                        <p style='color:#6b7280;font-size:0.8rem;margin-top:16px;'>
                            This alert is sent daily while batches remain within the 30-day expiry window.
                        </p>
                    </div>
                </div>
            ",
        ];
    }
}
