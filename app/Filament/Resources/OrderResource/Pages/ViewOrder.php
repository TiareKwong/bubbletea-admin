<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderItem;
use App\Models\Reward;
use App\Models\WalletTransaction;
use App\Services\BranchContext;
use App\Services\PushNotificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.order-resource.pages.view-order';

    /**
     * Returns true if the current user can action this order.
     * Admins can action any order. Staff can only action orders for their active branch.
     */
    protected function canActionOrder(): bool
    {
        $user = auth()->user();
        if ($user?->is_admin) return true;

        // Use the staff's assigned branch, falling back to the session branch.
        // This means staff can switch to "All Branches" to find a cross-branch order
        // but they can still only action orders from their own branch.
        $staffBranchId = $user?->branch_id ?? app(BranchContext::class)->getId();
        if (! $staffBranchId) return false;

        return (int) $this->record->branch_id === (int) $staffBranchId;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->canActionOrder()) {
            $orderBranch = $this->record->branch?->name ?? 'another branch';
            Notification::make()
                ->title('Wrong branch')
                ->body("This order belongs to {$orderBranch}. You cannot action it from your current branch.")
                ->warning()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Bank Transfer: verify payment = mark as paid in one step
            Action::make('markVerified')
                ->label('Verify & Mark Paid')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (): bool =>
                    $this->canActionOrder() &&
                    $this->record->payment_method === 'Bank Transfer' &&
                    $this->record->order_status   === 'Payment Verification'
                )
                ->requiresConfirmation()
                ->modalHeading('Verify & Mark Paid')
                ->modalDescription('Confirm the bank transfer has been received and mark this order as paid?')
                ->action(function (): void {
                    $order     = $this->record;
                    $staffName = auth()->user()->getFilamentName();

                    // Deduct wallet amount if used (guard against double-deduction)
                    $walletUsed = (float) $order->wallet_amount_used;
                    $alreadyDeducted = $walletUsed > 0 && WalletTransaction::where('reference', $order->order_code)
                        ->where('user_id', $order->user_id)
                        ->where('type', 'payment')
                        ->exists();
                    if ($walletUsed > 0 && $order->user_id && ! $alreadyDeducted) {
                        $order->user->decrement('wallet_balance', $walletUsed);
                        WalletTransaction::create([
                            'user_id'     => $order->user_id,
                            'branch_id'   => $order->branch_id,
                            'type'        => 'payment',
                            'amount'      => $walletUsed,
                            'reference'   => $order->order_code,
                            'notes'       => 'Wallet payment for order #' . $order->order_code,
                            'actioned_by' => $staffName,
                        ]);
                    }

                    // Earn points only on drink/ice_cream items
                    $pointsEarned = (int) round($order->pointableTotal() * 10);
                    $reward = Reward::firstOrCreate(
                        ['user_id' => $order->user_id],
                        ['points'  => 0]
                    );
                    $reward->points += $pointsEarned;
                    $reward->save();

                    $order->order_status  = 'Paid';
                    $order->points_earned = $pointsEarned;
                    $order->updated_by    = $staffName;
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
                    $this->canActionOrder() &&
                    $this->record->payment_method !== 'Bank Transfer' &&
                    in_array($this->record->order_status, ['Pending Payment', 'Payment Verification', 'Points Verification'])
                )
                ->requiresConfirmation()
                ->modalHeading('Confirm Payment')
                ->modalDescription('Mark this order as paid? This cannot be undone.')
                ->action(function (): void {
                    $order     = $this->record;
                    $staffName = auth()->user()->getFilamentName();
                    $reward    = Reward::firstOrCreate(
                        ['user_id' => $order->user_id],
                        ['points'  => 0]
                    );

                    $pointsEarned = 0;

                    if ($order->payment_method === 'Points') {
                        // Deduct points used
                        $reward->points = max(0, $reward->points - (int) $order->points_used);
                    } else {
                        // Earn points only on drink/ice_cream items
                        $pointsEarned   = (int) round($order->pointableTotal() * 10);
                        $reward->points += $pointsEarned;
                    }

                    $reward->save();

                    // Deduct wallet amount if used (guard against double-deduction)
                    $walletUsed = (float) $order->wallet_amount_used;
                    $alreadyDeducted = $walletUsed > 0 && WalletTransaction::where('reference', $order->order_code)
                        ->where('user_id', $order->user_id)
                        ->where('type', 'payment')
                        ->exists();
                    if ($walletUsed > 0 && $order->user_id && ! $alreadyDeducted) {
                        $order->user->decrement('wallet_balance', $walletUsed);
                        WalletTransaction::create([
                            'user_id'     => $order->user_id,
                            'branch_id'   => $order->branch_id,
                            'type'        => 'payment',
                            'amount'      => $walletUsed,
                            'reference'   => $order->order_code,
                            'notes'       => 'Wallet payment for order #' . $order->order_code,
                            'actioned_by' => $staffName,
                        ]);
                    }

                    $order->order_status  = 'Paid';
                    $order->points_earned = $pointsEarned;
                    $order->updated_by    = $staffName;
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'payment_confirmed', $order->order_code);

                    $this->refreshFormData(['order_status', 'points_earned', 'updated_by']);
                }),

            // Change Payment Method — before the order is paid
            Action::make('changePaymentMethod')
                ->label('Change Payment Method')
                ->icon('heroicon-o-credit-card')
                ->color('gray')
                ->visible(fn (): bool =>
                    $this->canActionOrder() &&
                    in_array($this->record->order_status, ['Pending Payment', 'Payment Verification', 'Points Verification'])
                )
                ->fillForm(fn (): array => [
                    'payment_method' => $this->record->payment_method,
                ])
                ->form([
                    Select::make('payment_method')
                        ->label('New Payment Method')
                        ->options([
                            'Cash'          => 'Cash',
                            'EFTPOS'        => 'EFTPOS',
                            'Bank Transfer' => 'Bank Transfer',
                        ])
                        ->required(),
                ])
                ->modalHeading('Change Payment Method')
                ->modalDescription('Update the payment method for this order. The order status will be adjusted accordingly.')
                ->action(function (array $data): void {
                    $order  = $this->record;
                    $method = $data['payment_method'];

                    $order->payment_method = $method;

                    // Adjust status to match the new payment method
                    if ($method === 'Bank Transfer') {
                        $order->order_status = 'Payment Verification';
                    } else {
                        // Cash or EFTPOS — reset to Pending Payment and clear any points reservation
                        $order->order_status = 'Pending Payment';
                        if ($order->reward_redeemed) {
                            $order->reward_redeemed = false;
                            $order->points_used     = 0;
                        }
                    }

                    $order->updated_by = auth()->user()->getFilamentName();
                    $order->save();

                    $this->refreshFormData(['payment_method', 'order_status', 'reward_redeemed', 'points_used', 'updated_by']);

                    Notification::make()
                        ->title('Payment method updated')
                        ->success()
                        ->send();
                }),

            // Edit Order Items — adjust quantities before the order is paid
            Action::make('editOrderItems')
                ->label('Edit Order Items')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool =>
                    $this->canActionOrder() &&
                    in_array($this->record->order_status, ['Pending Payment', 'Payment Verification', 'Points Verification'])
                )
                ->fillForm(function (): array {
                    $items = $this->record->orderItems()->with('flavor')->get()->map(function ($item) {
                        $label = $item->flavor?->name ?? 'Unknown item';
                        $label .= ' (' . $item->size . ')';
                        if (! empty($item->toppings)) {
                            $label .= ' + ' . implode(', ', $item->toppings);
                        }
                        $unitPrice = $item->quantity > 0
                            ? round((float) $item->price / $item->quantity, 4)
                            : (float) $item->price;

                        return [
                            'item_id'    => $item->id,
                            'label'      => $label,
                            'unit_price' => $unitPrice,
                            'quantity'   => $item->quantity,
                        ];
                    })->values()->toArray();

                    return ['items' => $items];
                })
                ->form([
                    Repeater::make('items')
                        ->label('Items')
                        ->schema([
                            Hidden::make('item_id'),
                            Hidden::make('unit_price'),
                            Hidden::make('label'),
                            Placeholder::make('item_label')
                                ->label('Item')
                                ->content(fn (Get $get): string => $get('label') ?? ''),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->required()
                                ->helperText('Set to 0 to remove this item'),
                        ])
                        ->columns(2)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->modalHeading('Edit Order Items')
                ->modalDescription('Adjust quantities below. Set an item to 0 to remove it from the order.')
                ->action(function (array $data): void {
                    $order     = $this->record;
                    $staffName = auth()->user()->getFilamentName();
                    $newTotal  = 0;

                    foreach ($data['items'] as $itemData) {
                        $item = OrderItem::find($itemData['item_id']);
                        if (! $item) continue;

                        $qty = (int) $itemData['quantity'];
                        if ($qty <= 0) {
                            $item->delete();
                        } else {
                            $unitPrice      = (float) $itemData['unit_price'];
                            $item->quantity = $qty;
                            $item->price    = round($qty * $unitPrice, 2);
                            $item->save();
                            $newTotal += $item->price;
                        }
                    }

                    // Preserve any existing discount
                    $discount           = (float) ($order->discount_applied ?? 0);
                    $order->total_price = max(0, round($newTotal - $discount, 2));
                    $order->updated_by  = $staffName;
                    $order->save();

                    $this->refreshFormData(['total_price', 'updated_by']);

                    Notification::make()
                        ->title('Order items updated')
                        ->success()
                        ->send();
                }),

            // Mark Preparing — visible once paid
            Action::make('markPreparing')
                ->label('Mark Preparing')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->visible(fn (): bool => $this->canActionOrder() && $this->record->order_status === 'Paid' && ! $this->record->collected)
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
                    $this->canActionOrder() &&
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
                    $this->canActionOrder() &&
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

            // Add Change to Wallet — only if no change record exists for this order yet
            Action::make('addChangeToWallet')
                ->label('Add Change to Wallet')
                ->icon('heroicon-o-wallet')
                ->color('info')
                ->visible(fn (): bool =>
                    $this->canActionOrder() &&
                    $this->record->user_id !== null &&
                    $this->record->user?->email !== 'guest@internal.local' &&
                    $this->record->payment_method === 'Cash' &&
                    in_array($this->record->order_status, ['Paid', 'Preparing', 'Ready', 'Collected']) &&
                    ! WalletTransaction::where('reference', 'Change from order #' . $this->record->order_code)
                        ->where('user_id', $this->record->user_id)
                        ->exists()
                )
                ->form(function (): array {
                    $amountDue = max(0, (float) $this->record->total_price - (float) $this->record->wallet_amount_used);
                    return [
                        TextInput::make('cash_received')
                            ->label('Cash Received ($)')
                            ->numeric()
                            ->minValue($amountDue + 0.01)
                            ->step(0.01)
                            ->required()
                            ->placeholder('Min: A$' . number_format($amountDue + 0.01, 2))
                            ->helperText('Must be more than A$' . number_format($amountDue, 2) . ' (amount due) to have change to return')
                            ->extraInputAttributes(['onkeydown' => "if(event.key==='Enter'){event.preventDefault(); event.stopPropagation();}"]),

                        \Filament\Schemas\Components\Actions::make([
                            Action::make('calculate')
                                ->label('Calculate Change')
                                ->icon('heroicon-o-calculator')
                                ->color('info')
                                ->action(function (
                                    \Filament\Schemas\Components\Utilities\Get $get,
                                    \Filament\Schemas\Components\Utilities\Set $set
                                ) use ($amountDue): void {
                                    $received = (float) $get('cash_received');
                                    if ($received <= $amountDue) {
                                        $set('change_to_wallet', '');
                                        \Filament\Notifications\Notification::make()
                                            ->title('Cash received must be more than A$' . number_format($amountDue, 2))
                                            ->danger()
                                            ->send();
                                        return;
                                    }
                                    $change = round($received - $amountDue, 2);
                                    $set('change_to_wallet', number_format($change, 2));
                                }),
                        ]),

                        \Filament\Forms\Components\Placeholder::make('change_display')
                            ->label('Change to Wallet')
                            ->content(fn (\Filament\Schemas\Components\Utilities\Get $get): string =>
                                filled($get('change_to_wallet'))
                                    ? 'A$' . $get('change_to_wallet') . ' will be added to the customer\'s wallet'
                                    : '— click Calculate first'
                            )
                            ->live(),

                        \Filament\Forms\Components\Hidden::make('change_to_wallet'),

                        \Filament\Schemas\Components\Actions::make([
                            Action::make('submit_add')
                                ->label('Add Change to Wallet')
                                ->color('success')
                                ->icon('heroicon-o-check')
                                ->hidden(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => blank($get('change_to_wallet')))
                                ->submit('callMountedAction'),
                        ]),
                    ];
                })
                ->modalSubmitAction(false)
                ->modalHeading('Add Change to Wallet')
                ->modalDescription('Enter the cash received from the customer, then click Calculate.')
                ->action(function (array $data): void {
                    $order     = $this->record;
                    $amountDue = max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
                    $received  = (float) $data['cash_received'];

                    if ($received <= $amountDue) return; // no change to give

                    $changeAmount = round($received - $amountDue, 2);
                    $staffName    = auth()->user()->getFilamentName();

                    $order->user->increment('wallet_balance', $changeAmount);

                    WalletTransaction::create([
                        'user_id'     => $order->user_id,
                        'branch_id'   => $order->branch_id,
                        'type'        => 'change',
                        'amount'      => $changeAmount,
                        'reference'   => 'Change from order #' . $order->order_code,
                        'notes'       => 'Change from order #' . $order->order_code . ' (cash received: A$' . number_format($received, 2) . ')',
                        'actioned_by' => $staffName,
                    ]);

                    $order->updated_by = $staffName;
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'change_to_wallet', number_format($changeAmount, 2));

                    $this->refreshFormData(['updated_by']);
                }),

            // Edit Change to Wallet — hidden from header, triggered from the Change Added to Wallet table
            Action::make('editChangeToWallet')
                ->label('Edit Change to Wallet')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(false)
                ->fillForm(function (): array {
                    $existing  = WalletTransaction::where('reference', 'Change from order #' . $this->record->order_code)
                        ->where('user_id', $this->record->user_id)
                        ->latest()
                        ->first();

                    $amountDue = max(0, (float) $this->record->total_price - (float) $this->record->wallet_amount_used);
                    $oldChange = (float) ($existing?->amount ?? 0);

                    return ['cash_received' => number_format($amountDue + $oldChange, 2)];
                })
                ->form(function (): array {
                    $amountDue = max(0, (float) $this->record->total_price - (float) $this->record->wallet_amount_used);
                    return [
                        TextInput::make('cash_received')
                            ->label('Cash Received ($)')
                            ->numeric()
                            ->minValue($amountDue)
                            ->required()
                            ->helperText('Amount due from customer: A$' . number_format($amountDue, 2))
                            ->extraInputAttributes(['onkeydown' => "if(event.key==='Enter'){event.preventDefault();}"]),

                        \Filament\Schemas\Components\Actions::make([
                            Action::make('calculate')
                                ->label('Calculate Change')
                                ->icon('heroicon-o-calculator')
                                ->color('info')
                                ->action(function (
                                    \Filament\Schemas\Components\Utilities\Get $get,
                                    \Filament\Schemas\Components\Utilities\Set $set
                                ) use ($amountDue): void {
                                    $received = (float) $get('cash_received');
                                    $change   = $received > $amountDue ? round($received - $amountDue, 2) : 0;
                                    $set('change_to_wallet', $change > 0 ? number_format($change, 2) : '0.00');
                                }),
                        ]),

                        TextInput::make('change_to_wallet')
                            ->label('Change to Wallet ($)')
                            ->readOnly()
                            ->required()
                            ->helperText('Amount that will be added to the customer\'s wallet — click Calculate first'),
                    ];
                })
                ->modalHeading('Edit Change to Wallet')
                ->modalDescription('Update the cash received, then click Calculate.')
                ->action(function (array $data): void {
                    $order    = $this->record;
                    $existing = WalletTransaction::where('reference', 'Change from order #' . $order->order_code)
                        ->where('user_id', $order->user_id)
                        ->latest()
                        ->first();

                    if (! $existing) return;

                    $amountDue = max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
                    $received  = (float) $data['cash_received'];
                    $newChange = $received > $amountDue ? round($received - $amountDue, 2) : 0;
                    $diff      = $newChange - (float) $existing->amount;
                    $staffName = auth()->user()->getFilamentName();

                    if ($diff > 0) {
                        $order->user->increment('wallet_balance', $diff);
                    } elseif ($diff < 0) {
                        $order->user->decrement('wallet_balance', abs($diff));
                    }

                    $existing->amount      = $newChange;
                    $existing->notes       = 'Change from order #' . $order->order_code . ' (cash received: A$' . number_format($received, 2) . ')';
                    $existing->actioned_by = $staffName;
                    $existing->save();

                    $order->updated_by = $staffName;
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'change_to_wallet', number_format($newChange, 2));

                    $this->refreshFormData(['updated_by']);
                }),

            // Remove Change to Wallet — handled by the Livewire OrderChangeWallet component inline
            // Kept here as a no-op placeholder so mountAction('removeChangeToWallet') resolves if needed
            Action::make('removeChangeToWallet')
                ->label('Remove Change to Wallet')
                ->visible(false)
                ->action(fn () => null),

            // Cancel
            Action::make('cancel')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->canActionOrder() &&
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

                    // Refund wallet amount if it was deducted — wallet is now deducted at
                    // order placement (before Paid), so check the transaction record, not order status
                    $walletUsed = (float) $order->wallet_amount_used;
                    if ($walletUsed > 0 && $order->user_id) {
                        $wasDeducted = WalletTransaction::where('reference', $order->order_code)
                            ->where('user_id', $order->user_id)
                            ->where('type', 'payment')
                            ->exists();
                        $alreadyRefunded = WalletTransaction::where('reference', $order->order_code)
                            ->where('user_id', $order->user_id)
                            ->where('type', 'refund')
                            ->exists();

                        if ($wasDeducted && ! $alreadyRefunded) {
                            $order->user->increment('wallet_balance', $walletUsed);
                            WalletTransaction::create([
                                'user_id'     => $order->user_id,
                                'branch_id'   => $order->branch_id,
                                'type'        => 'refund',
                                'amount'      => $walletUsed,
                                'reference'   => $order->order_code,
                                'notes'       => 'Refund for cancelled order #' . $order->order_code,
                                'actioned_by' => auth()->user()->getFilamentName(),
                            ]);
                        }
                    }

                    $order->order_status  = 'Cancelled';
                    $order->points_earned = 0;
                    $order->updated_by    = auth()->user()->getFilamentName();
                    $order->save();

                    PushNotificationService::sendLocalized($order->user_id, 'order_cancelled', $order->order_code);

                    $this->refreshFormData(['order_status', 'points_earned', 'updated_by']);
                }),
        ];
    }

    // ── Direct Livewire methods called from blade change-to-wallet table ──────

    public bool $showEditChangeModal  = false;
    public bool $showRemoveChangeConfirm = false;
    public string $editCashReceived   = '';
    public string $editChangeToWallet = '';

    public function openEditChange(): void
    {
        $order     = $this->record;
        $amountDue = max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
        $existing  = WalletTransaction::where('reference', 'Change from order #' . $order->order_code)
            ->where('user_id', $order->user_id)->latest()->first();

        $this->editCashReceived   = number_format($amountDue + (float) ($existing?->amount ?? 0), 2);
        $this->editChangeToWallet = '';
        $this->showEditChangeModal = true;
    }

    public function calculateEditChange(): void
    {
        $order     = $this->record;
        $amountDue = max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
        $received  = (float) $this->editCashReceived;
        $change    = $received > $amountDue ? round($received - $amountDue, 2) : 0;
        $this->editChangeToWallet = $change > 0 ? number_format($change, 2) : '0.00';
    }

    public function saveEditChange(): void
    {
        $order    = $this->record;
        $existing = WalletTransaction::where('reference', 'Change from order #' . $order->order_code)
            ->where('user_id', $order->user_id)->latest()->first();

        if (! $existing) { $this->showEditChangeModal = false; return; }

        $amountDue = max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
        $received  = (float) $this->editCashReceived;
        $newChange = $received > $amountDue ? round($received - $amountDue, 2) : 0;
        $diff      = $newChange - (float) $existing->amount;
        $staffName = auth()->user()->getFilamentName();

        if ($diff > 0) { $order->user->increment('wallet_balance', $diff); }
        elseif ($diff < 0) { $order->user->decrement('wallet_balance', abs($diff)); }

        $existing->amount      = $newChange;
        $existing->notes       = 'Change from order #' . $order->order_code . ' (cash received: A$' . number_format($received, 2) . ')';
        $existing->actioned_by = $staffName;
        $existing->save();

        $order->updated_by = $staffName;
        $order->save();

        PushNotificationService::sendLocalized($order->user_id, 'change_to_wallet', number_format($newChange, 2));

        $this->showEditChangeModal = false;
        $this->refreshFormData(['updated_by']);
    }

    public function confirmRemoveChange(): void
    {
        $this->showRemoveChangeConfirm = true;
    }

    public function removeChange(): void
    {
        $order    = $this->record;
        $existing = WalletTransaction::where('reference', 'Change from order #' . $order->order_code)
            ->where('user_id', $order->user_id)->latest()->first();

        if (! $existing) { $this->showRemoveChangeConfirm = false; return; }

        $staffName = auth()->user()->getFilamentName();
        $order->user->decrement('wallet_balance', (float) $existing->amount);
        $existing->delete();

        $order->updated_by = $staffName;
        $order->save();

        $this->showRemoveChangeConfirm = false;
        $this->refreshFormData(['updated_by']);
    }
}
