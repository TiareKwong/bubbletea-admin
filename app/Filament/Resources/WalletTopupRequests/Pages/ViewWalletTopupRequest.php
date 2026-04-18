<?php

namespace App\Filament\Resources\WalletTopupRequests\Pages;

use App\Filament\Resources\WalletTopupRequests\WalletTopupRequestResource;
use App\Models\WalletTransaction;
use App\Services\PushNotificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewWalletTopupRequest extends ViewRecord
{
    protected static string $resource = WalletTopupRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'Pending')
                ->requiresConfirmation()
                ->modalHeading('Approve Wallet Top-up')
                ->modalDescription('This will add $' . number_format($this->record->amount, 2) . ' to the customer\'s wallet.')
                ->action(function (): void {
                    $request   = $this->record;
                    $staffName = auth()->user()->getFilamentName();

                    // Add to wallet balance
                    $request->user->increment('wallet_balance', $request->amount);

                    // Record transaction
                    WalletTransaction::create([
                        'user_id'     => $request->user_id,
                        'type'        => 'topup',
                        'amount'      => $request->amount,
                        'reference'   => 'Request #' . $request->id,
                        'notes'       => 'Top-up via ' . $request->payment_method,
                        'actioned_by' => $staffName,
                    ]);

                    $request->status      = 'Approved';
                    $request->actioned_by = $staffName;
                    $request->save();

                    PushNotificationService::sendLocalized($request->user_id, 'wallet_topup_approved', number_format($request->amount, 2));

                    $this->refreshFormData(['status', 'actioned_by']);
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'Pending')
                ->form([
                    Textarea::make('notes')
                        ->label('Reason for rejection (optional)')
                        ->rows(3),
                ])
                ->modalHeading('Reject Wallet Top-up')
                ->action(function (array $data): void {
                    $request = $this->record;

                    $request->status      = 'Rejected';
                    $request->notes       = $data['notes'] ?? null;
                    $request->actioned_by = auth()->user()->getFilamentName();
                    $request->save();

                    PushNotificationService::sendLocalized($request->user_id, 'wallet_topup_rejected', number_format($request->amount, 2));

                    $this->refreshFormData(['status', 'notes', 'actioned_by']);
                }),
        ];
    }
}
