<?php

namespace App\Filament\Resources\WalletTopupRequests\Pages;

use App\Filament\Resources\WalletTopupRequests\WalletTopupRequestResource;
use App\Models\User;
use App\Models\WalletTopupRequest;
use App\Models\WalletTransaction;
use App\Services\PushNotificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListWalletTopupRequests extends ListRecords
{
    protected static string $resource = WalletTopupRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('staffAddWallet')
                ->label('Add Wallet Balance for Customer')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('user_id')
                        ->label('Customer')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search) {
                            return User::where('is_staff', false)
                                ->where(fn ($q) => $q
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone_number', 'like', "%{$search}%")
                                )
                                ->limit(10)
                                ->get()
                                ->mapWithKeys(fn ($user) => [
                                    $user->id => "{$user->first_name} {$user->last_name} — {$user->email}" . ($user->phone_number ? " — {$user->phone_number}" : ''),
                                ]);
                        })
                        ->getOptionLabelUsing(fn ($value) => optional(User::find($value))->first_name . ' ' . optional(User::find($value))->last_name),

                    TextInput::make('amount')
                        ->label('Amount ($)')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),

                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->required()
                        ->options([
                            'Cash'          => 'Cash',
                            'EFTPOS'        => 'EFTPOS',
                            'Bank Transfer' => 'Bank Transfer',
                        ]),
                ])
                ->modalHeading('Add Wallet Balance for Customer')
                ->action(function (array $data): void {
                    $amount    = (float) $data['amount'];
                    $staffName = auth()->user()->getFilamentName();
                    $user      = User::find($data['user_id']);

                    $user->increment('wallet_balance', $amount);

                    WalletTopupRequest::create([
                        'user_id'        => $data['user_id'],
                        'amount'         => $amount,
                        'payment_method' => $data['payment_method'],
                        'status'         => 'Approved',
                        'actioned_by'    => $staffName,
                    ]);

                    WalletTransaction::create([
                        'user_id'     => $data['user_id'],
                        'type'        => 'topup',
                        'amount'      => $amount,
                        'notes'       => 'Staff top-up via ' . $data['payment_method'],
                        'actioned_by' => $staffName,
                    ]);

                    PushNotificationService::sendLocalized($data['user_id'], 'wallet_topup_approved', number_format($amount, 2));
                }),
        ];
    }
}
