<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Reward;
use App\Services\PushNotificationService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.order-resource.pages.view-order';

    protected function getHeaderActions(): array
    {
        return [
            // Bank Transfer: verify payment = mark as paid in one step
            Action::make('markVerified')
                ->label('Verify & Mark Paid')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (): bool =>
                    $this->record->payment_method === 'Bank Transfer' &&
                    $this->record->order_status   === 'Payment Verification'
                )
                ->requiresConfirmation()
                ->modalHeading('Verify & Mark Paid')
                ->modalDescription('Confirm the bank transfer has been received and mark this order as paid?')
                ->action(function (): void {
                    $order = $this->record;

                    $pointsEarned = (int) round((float) $order->total_price * 10);
                    $reward = Reward::firstOrCreate(
                        ['user_id' => $order->user_id],
                        ['points'  => 0]
                    );
                    $reward->points += $pointsEarned;
                    $reward->save();

                    $order->order_status  = 'Paid';
                    $order->points_earned = $pointsEarned;
                    $order->updated_by    = auth()->user()->getFilamentName();
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'payment_verified', $order->order_code);

                    $this->refreshFormData(['order_status', 'points_earned', 'updated_by']);
                }),

            // Mark Paid — Cash / EFTPOS / Points only (Bank Transfer uses markVerified above)
            Action::make('markPaid')
                ->label(fn (): string =>
                    $this->record->payment_method === 'EFTPOS' ? 'Confirm EFTPOS Payment' : 'Mark Paid'
                )
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool =>
                    $this->record->payment_method !== 'Bank Transfer' &&
                    in_array($this->record->order_status, ['Pending Payment', 'Payment Verification', 'Points Verification'])
                )
                ->requiresConfirmation()
                ->modalHeading('Confirm Payment')
                ->modalDescription('Mark this order as paid? This cannot be undone.')
                ->action(function (): void {
                    $order  = $this->record;
                    $reward = Reward::firstOrCreate(
                        ['user_id' => $order->user_id],
                        ['points'  => 0]
                    );

                    $pointsEarned = 0;

                    if ($order->payment_method === 'Points') {
                        // Deduct the points used to pay for this order
                        $reward->points = max(0, $reward->points - (int) $order->points_used);
                    } else {
                        // Earn points for cash / EFTPOS payments
                        $pointsEarned   = (int) round((float) $order->total_price * 10);
                        $reward->points += $pointsEarned;
                    }

                    $reward->save();

                    $order->order_status  = 'Paid';
                    $order->points_earned = $pointsEarned;
                    $order->updated_by    = auth()->user()->getFilamentName();
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'payment_confirmed', $order->order_code);

                    $this->refreshFormData(['order_status', 'points_earned', 'updated_by']);
                }),

            // Mark Preparing — visible once paid
            Action::make('markPreparing')
                ->label('Mark Preparing')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->visible(fn (): bool => $this->record->order_status === 'Paid' && ! $this->record->collected)
                ->requiresConfirmation()
                ->modalHeading('Start Preparing')
                ->modalDescription('Mark this order as being prepared?')
                ->action(function (): void {
                    $this->record->order_status = 'Preparing';
                    $this->record->updated_by   = auth()->user()->getFilamentName();
                    $this->record->save();

                    PushNotificationService::sendLocalized($this->record->user_id, 'order_preparing', $this->record->order_code);

                    $this->refreshFormData(['order_status', 'updated_by']);
                }),

            // Mark Ready — all payment methods except Points
            Action::make('markReady')
                ->label('Mark Ready')
                ->icon('heroicon-o-bell')
                ->color('success')
                ->visible(fn (): bool =>
                    $this->record->order_status === 'Preparing' &&
                    ! $this->record->collected &&
                    $this->record->payment_method !== 'Points'
                )
                ->requiresConfirmation()
                ->modalHeading('Order Ready')
                ->modalDescription('Mark this order as ready for collection?')
                ->action(function (): void {
                    $this->record->order_status = 'Ready';
                    $this->record->updated_by   = auth()->user()->getFilamentName();
                    $this->record->save();

                    PushNotificationService::sendLocalized($this->record->user_id, 'order_ready', $this->record->order_code);

                    $this->refreshFormData(['order_status', 'updated_by']);
                }),

            // Mark Collected
            Action::make('markCollected')
                ->label('Mark Collected')
                ->icon('heroicon-o-shopping-bag')
                ->color('primary')
                ->visible(fn (): bool =>
                    in_array($this->record->order_status, ['Paid', 'Preparing', 'Ready']) &&
                    ! $this->record->collected
                )
                ->requiresConfirmation()
                ->modalHeading('Confirm Collection')
                ->modalDescription('Mark this order as collected by the customer?')
                ->action(function (): void {
                    $this->record->collected    = true;
                    $this->record->order_status = 'Collected';
                    $this->record->updated_by   = auth()->user()->getFilamentName();
                    $this->record->save();

                    PushNotificationService::sendLocalized($this->record->user_id, 'order_collected', $this->record->order_code);

                    $this->refreshFormData(['collected', 'order_status', 'updated_by']);
                }),

            // Cancel
            Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    ! in_array($this->record->order_status, ['Cancelled']) &&
                    ! $this->record->collected
                )
                ->requiresConfirmation()
                ->modalHeading('Cancel Order')
                ->modalDescription('Are you sure you want to cancel this order? Any points changes from this order will be reversed.')
                ->action(function (): void {
                    $order = $this->record;

                    // Reverse points only if the order was already paid
                    $alreadyPaid = in_array($order->order_status, ['Paid', 'Preparing', 'Ready']);

                    if ($alreadyPaid) {
                        $reward = Reward::firstOrCreate(
                            ['user_id' => $order->user_id],
                            ['points'  => 0]
                        );

                        if ($order->payment_method === 'Points') {
                            // Refund the points that were spent
                            $reward->points += (int) $order->points_used;
                        } else {
                            // Reverse the points that were earned
                            $reward->points = max(0, $reward->points - (int) $order->points_earned);
                        }

                        $reward->save();
                    }

                    $order->order_status = 'Cancelled';
                    $order->updated_by   = auth()->user()->getFilamentName();
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'order_cancelled', $order->order_code);

                    $this->refreshFormData(['order_status', 'updated_by']);
                }),
        ];
    }
}
