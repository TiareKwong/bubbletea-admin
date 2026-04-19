<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Flavor;
use App\Models\Order;
use App\Models\Reward;
use App\Models\Topping;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Items are stored here after mutateFormDataBeforeCreate so they can be
     * persisted to order_items in afterCreate().
     */
    private array $pendingItems = [];

    /** Wallet amount to record as a transaction after the order is created. */
    private float $pendingWalletAmount = 0.0;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label('Cancel')
                ->color('gray')
                ->url(OrderResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ── 1. Resolve customer ──────────────────────────────────────────────
        if ($data['customer_id'] === 'guest') {
            $guest = User::firstOrCreate(
                ['email' => 'guest@internal.local'],
                [
                    'first_name'         => 'Guest',
                    'last_name'          => 'Walk-in',
                    'password'           => Hash::make(Str::random(32)),
                    'is_staff'           => false,
                    'is_verified'        => false,
                    'phone_number'       => '0000000000',
                    'verification_token' => null,
                ]
            );
            $data['user_id'] = $guest->id;
            $isGuest = true;
        } else {
            $data['user_id'] = (int) $data['customer_id'];
            $isGuest = false;
        }
        unset($data['customer_id']);

        // ── 2. Process items ─────────────────────────────────────────────────
        $totalPrice = 0.0;
        $this->pendingItems = [];

        foreach ($data['items'] ?? [] as $item) {
            $unitPrice = (float) ($item['price'] ?? 0);
            $quantity  = (int)   ($item['quantity'] ?? 1);
            $totalPrice += round($unitPrice * $quantity, 2);

            // Transform topping repeater rows → snapshot objects (expanded by qty)
            $toppingRows = array_values(array_filter((array) ($item['toppings'] ?? [])));
            $toppings    = [];
            if (! empty($toppingRows)) {
                $ids        = array_filter(array_column($toppingRows, 'topping_id'));
                $toppingMap = Topping::whereIn('id', $ids)
                    ->get(['id', 'name', 'price'])
                    ->keyBy('id');

                foreach ($toppingRows as $row) {
                    $id  = $row['topping_id'] ?? null;
                    $qty = max(1, (int) ($row['qty'] ?? 1));
                    if ($id && $toppingMap->has($id)) {
                        $t = $toppingMap[$id];
                        for ($i = 0; $i < $qty; $i++) {
                            $toppings[] = [
                                'id'    => $t->id,
                                'name'  => $t->name,
                                'price' => (string) $t->price,
                            ];
                        }
                    }
                }
            }

            $this->pendingItems[] = [
                'flavor_id' => (int) $item['flavor_id'],
                'size'      => $item['size'],
                'ice'       => $item['ice'] ?? null,
                'sugar'     => $item['sugar'] ?? null,
                'toppings'  => $toppings,
                'quantity'  => $quantity,
                'price'     => $unitPrice,
            ];
        }
        unset($data['items']);

        // ── 3. Determine initial order status ────────────────────────────────
        $data['order_status'] = match ($data['payment_method']) {
            'Cash', 'EFTPOS', 'Wallet' => 'Paid',
            'Bank Transfer'            => 'Payment Verification',
            'Points'                   => 'Points Verification',
            default                    => 'Pending Payment',
        };

        // ── 4. Financials and metadata ───────────────────────────────────────
        $data['total_price']     = round($totalPrice, 2);
        $data['points_used']     = 0;
        $data['reward_redeemed'] = false;
        $data['collected']       = false;
        $data['order_code']      = Order::generateCode();
        $data['updated_by']      = auth()->user()->getFilamentName();

        $pointsEarned = 0;
        if (! $isGuest && $data['payment_method'] !== 'Points') {
            $pointableIds = Flavor::whereIn('id', collect($this->pendingItems)->pluck('flavor_id'))
                ->whereIn('type', ['drink', 'ice_cream'])
                ->pluck('id')->toArray();
            $pointableTotal = collect($this->pendingItems)
                ->filter(fn($item) => in_array($item['flavor_id'], $pointableIds))
                ->sum(fn($item) => $item['price'] * $item['quantity']);
            $pointsEarned = (int) round($pointableTotal * 10);
            $reward = Reward::firstOrCreate(
                ['user_id' => $data['user_id']],
                ['points'  => 0]
            );
            $reward->points += $pointsEarned;
            $reward->save();
        }
        $data['points_earned'] = $pointsEarned;

        // ── 5. Wallet: deduct balance and record transaction ─────────────────
        if ($data['payment_method'] === 'Wallet' && ! $isGuest) {
            $walletAmount = round($totalPrice, 2);
            $data['wallet_amount_used'] = $walletAmount;

            $user = User::find($data['user_id']);
            if ($user) {
                $user->decrement('wallet_balance', $walletAmount);
                // Transaction will be recorded in afterCreate once order_code is set
                $this->pendingWalletAmount = $walletAmount;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingItems as $item) {
            $this->record->orderItems()->create($item);
        }

        if ($this->pendingWalletAmount > 0) {
            WalletTransaction::create([
                'user_id'     => $this->record->user_id,
                'type'        => 'payment',
                'amount'      => $this->pendingWalletAmount,
                'reference'   => $this->record->order_code,
                'notes'       => 'Wallet payment for order #' . $this->record->order_code,
                'actioned_by' => auth()->user()->getFilamentName(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
