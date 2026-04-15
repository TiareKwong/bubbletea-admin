<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Topping;
use App\Models\User;
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
            'Cash', 'EFTPOS' => 'Paid',
            'Bank Transfer'  => 'Payment Verification',
            'Points'         => 'Points Verification',
            default          => 'Pending Payment',
        };

        // ── 4. Financials and metadata ───────────────────────────────────────
        $data['total_price']     = round($totalPrice, 2);
        $data['points_earned']   = $isGuest ? 0 : (int) floor($totalPrice);
        $data['points_used']     = 0;
        $data['reward_redeemed'] = false;
        $data['collected']       = false;
        $data['order_code']      = Order::generateCode();
        $data['updated_by']      = auth()->user()->getFilamentName();

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->pendingItems as $item) {
            $this->record->orderItems()->create($item);
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }
}
