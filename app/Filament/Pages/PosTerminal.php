<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Flavor;
use App\Models\Order;
use App\Models\Reward;
use App\Models\Topping;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BranchContext;
use App\Services\PushNotificationService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosTerminal extends Page
{
    protected string $view = 'filament.pages.pos-terminal';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'POS Terminal';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    // ── UI state (small — safe for Livewire to serialize) ─────────────────────
    public string  $search         = '';
    public string  $categoryFilter = '';
    public ?int    $selectedBranchId = null;

    public string  $customerId     = 'guest';
    public string  $customerSearch = '';

    public array   $cart            = [];
    public string  $paymentMethod   = 'Cash';
    public string  $paymentReference = '';

    // Modal
    public bool    $modalOpen      = false;
    public ?int    $modalFlavorId  = null;
    public string  $modalSize      = 'Regular';
    public string  $modalIce       = 'Regular';
    public string  $modalSugar     = '100%';
    public array   $modalToppingMap = [];
    public int     $modalQty       = 1;
    public ?int    $modalEditIndex = null;

    public ?string $errorMessage   = null;

    public function mount(): void
    {
        $this->selectedBranchId = app(BranchContext::class)->getId();
    }

    // ── Modal ─────────────────────────────────────────────────────────────────

    public function openFlavorModal(int $flavorId): void
    {
        $flavor = Flavor::find($flavorId);
        if (! $flavor) {
            return;
        }

        $this->modalFlavorId   = $flavorId;
        $this->modalEditIndex  = null;
        $this->modalQty        = 1;
        $this->modalToppingMap = [];
        $this->modalIce        = 'Regular';
        $this->modalSugar      = '100%';
        $this->modalSize       = (float) $flavor->regular_price > 0 ? 'Regular'
            : ((float) $flavor->small_price > 0 ? 'Small' : 'Large');
        $this->modalOpen       = true;
    }

    public function editCartItem(int $index): void
    {
        $item = $this->cart[$index] ?? null;
        if (! $item) {
            return;
        }
        $this->modalFlavorId   = $item['flavor_id'];
        $this->modalEditIndex  = $index;
        $this->modalSize       = $item['size'];
        $this->modalIce        = $item['ice'];
        $this->modalSugar      = $item['sugar'];
        $this->modalToppingMap = $item['topping_map'];
        $this->modalQty        = $item['qty'];
        $this->modalOpen       = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen      = false;
        $this->modalFlavorId  = null;
        $this->modalEditIndex = null;
    }

    public function setModalSize(string $size): void { $this->modalSize = $size; }
    public function setModalIce(string $ice): void   { $this->modalIce  = $ice;  }
    public function setModalSugar(string $sugar): void { $this->modalSugar = $sugar; }
    public function setCategoryFilter(string $cat): void { $this->categoryFilter = $cat; }
    public function setPaymentMethod(string $method): void { $this->paymentMethod = $method; }

    public function adjustModalTopping(int $toppingId, int $delta): void
    {
        $current = $this->modalToppingMap[$toppingId] ?? 0;
        $total   = array_sum($this->modalToppingMap);
        $other   = $total - $current;
        $newQty  = max(0, $current + $delta);

        if ($delta > 0 && ($other + $newQty) > 4) {
            return;
        }

        if ($newQty === 0) {
            unset($this->modalToppingMap[$toppingId]);
        } else {
            $this->modalToppingMap[$toppingId] = $newQty;
        }

        $this->modalToppingMap = $this->modalToppingMap;
    }

    public function adjustModalQty(int $delta): void
    {
        $this->modalQty = max(1, $this->modalQty + $delta);
    }

    public function addToCart(): void
    {
        $flavor = $this->modalFlavorId ? Flavor::find($this->modalFlavorId) : null;
        if (! $flavor) {
            return;
        }

        $base = match ($this->modalSize) {
            'Small' => (float) $flavor->small_price,
            'Large' => (float) $flavor->large_price,
            default => (float) $flavor->regular_price,
        };

        $toppings     = [];
        $toppingLabel = '';
        $toppingTotal = 0.0;
        $filteredMap  = array_filter($this->modalToppingMap, fn ($q) => $q > 0);

        if ($filteredMap) {
            $models     = Topping::whereIn('id', array_keys($filteredMap))->get(['id', 'name', 'price'])->keyBy('id');
            $labelParts = [];
            foreach ($filteredMap as $tid => $qty) {
                $t = $models[$tid] ?? null;
                if (! $t) {
                    continue;
                }
                $toppingTotal += (float) $t->price * $qty;
                for ($i = 0; $i < $qty; $i++) {
                    $toppings[] = ['id' => $t->id, 'name' => $t->name, 'price' => (string) $t->price];
                }
                $labelParts[] = $qty > 1 ? $t->name . ' ×' . $qty : $t->name;
            }
            $toppingLabel = implode(', ', $labelParts);
        }

        $unitPrice = $base + $toppingTotal;
        $lineTotal = round($unitPrice * $this->modalQty, 2);

        $item = [
            'flavor_id'     => (int) $this->modalFlavorId,
            'flavor_name'   => $flavor->name,
            'flavor_type'   => $flavor->type,
            'size'          => $this->modalSize,
            'ice'           => $this->modalIce,
            'sugar'         => $this->modalSugar,
            'topping_map'   => $filteredMap,
            'toppings'      => $toppings,
            'topping_label' => $toppingLabel,
            'qty'           => $this->modalQty,
            'unit_price'    => round($unitPrice, 2),
            'line_total'    => $lineTotal,
        ];

        if ($this->modalEditIndex !== null && isset($this->cart[$this->modalEditIndex])) {
            $this->cart[$this->modalEditIndex] = $item;
        } else {
            $this->cart[] = $item;
        }

        $this->closeModal();
    }

    public function removeCartItem(int $index): void
    {
        array_splice($this->cart, $index, 1);
        $this->cart = array_values($this->cart);
    }

    public function adjustCartQty(int $index, int $delta): void
    {
        $item = $this->cart[$index] ?? null;
        if (! $item) {
            return;
        }
        $item['qty']        = max(1, $item['qty'] + $delta);
        $item['line_total'] = round($item['unit_price'] * $item['qty'], 2);
        $this->cart[$index] = $item;
    }

    public function selectCustomer(int $userId): void
    {
        $this->customerId     = (string) $userId;
        $this->customerSearch = '';
    }

    public function clearCustomer(): void
    {
        $this->customerId     = 'guest';
        $this->customerSearch = '';
        if (in_array($this->paymentMethod, ['Wallet', 'Points'])) {
            $this->paymentMethod = 'Cash';
        }
    }

    // ── Place order ───────────────────────────────────────────────────────────

    public function placeOrder(): void
    {
        $this->errorMessage = null;

        if (empty($this->cart)) {
            $this->errorMessage = 'Cart is empty.';
            return;
        }

        if (! $this->selectedBranchId) {
            $this->errorMessage = 'Please select a branch.';
            return;
        }

        if ($this->paymentMethod === 'Bank Transfer' && blank($this->paymentReference)) {
            $this->errorMessage = 'Enter a bank transfer reference.';
            return;
        }

        $isGuest = $this->customerId === 'guest';

        if ($isGuest) {
            $user = User::firstOrCreate(
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
        } else {
            $user = User::find((int) $this->customerId);
            if (! $user) {
                $this->errorMessage = 'Customer not found.';
                return;
            }
        }

        if (in_array($this->paymentMethod, ['Wallet', 'Points']) && $isGuest) {
            $this->errorMessage = 'Wallet and Points payment require a registered customer.';
            return;
        }

        $totalPrice   = 0.0;
        $pendingItems = [];
        foreach ($this->cart as $item) {
            $rowTotal       = round((float) $item['line_total'], 2);
            $totalPrice    += $rowTotal;
            $pendingItems[] = [
                'flavor_id' => $item['flavor_id'],
                'size'      => $item['size'],
                'ice'       => $item['ice'],
                'sugar'     => $item['sugar'],
                'toppings'  => $item['toppings'],
                'quantity'  => $item['qty'],
                'price'     => $rowTotal,
            ];
        }
        $totalPrice = round($totalPrice, 2);

        $orderStatus = match ($this->paymentMethod) {
            'Cash', 'EFTPOS', 'Wallet' => 'Paid',
            'Bank Transfer'            => 'Payment Verification',
            'Points'                   => 'Points Verification',
            default                    => 'Pending Payment',
        };

        $pointsEarned = 0;
        if (! $isGuest && $this->paymentMethod !== 'Points') {
            $pointableIds   = Flavor::whereIn('id', collect($pendingItems)->pluck('flavor_id'))
                ->whereIn('type', ['drink', 'ice_cream'])
                ->pluck('id')->toArray();
            $pointableTotal = collect($pendingItems)
                ->filter(fn ($i) => in_array($i['flavor_id'], $pointableIds))
                ->sum(fn ($i) => $i['price']);
            $pointsEarned   = (int) round($pointableTotal * 10);
            $reward         = Reward::firstOrCreate(['user_id' => $user->id], ['points' => 0]);
            $reward->points += $pointsEarned;
            $reward->save();
        }

        $walletAmountUsed = 0.0;
        if ($this->paymentMethod === 'Wallet') {
            $walletAmountUsed = $totalPrice;
            $user->decrement('wallet_balance', $walletAmountUsed);
        }

        $order = Order::create([
            'user_id'            => $user->id,
            'branch_id'          => $this->selectedBranchId,
            'total_price'        => $totalPrice,
            'payment_method'     => $this->paymentMethod,
            'payment_reference'  => $this->paymentMethod === 'Bank Transfer' ? trim($this->paymentReference) : null,
            'order_code'         => Order::generateCode(),
            'order_status'       => $orderStatus,
            'points_used'        => 0,
            'points_earned'      => $pointsEarned,
            'reward_redeemed'    => false,
            'collected'          => false,
            'wallet_amount_used' => $walletAmountUsed,
            'updated_by'         => auth()->user()->getFilamentName(),
        ]);

        foreach ($pendingItems as $item) {
            $order->orderItems()->create($item);
        }

        if ($walletAmountUsed > 0) {
            WalletTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'payment',
                'amount'      => $walletAmountUsed,
                'reference'   => $order->order_code,
                'notes'       => 'Wallet payment for order #' . $order->order_code,
                'actioned_by' => auth()->user()->getFilamentName(),
            ]);
        }

        if (! $isGuest) {
            PushNotificationService::sendLocalized($user->id, 'payment_confirmed', $order->order_code);
        }

        $this->redirect(
            route('filament.admin.resources.orders.view', ['record' => $order->id])
        );
    }
}
