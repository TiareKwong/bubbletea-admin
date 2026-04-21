<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\WalletTransaction;
use App\Services\PushNotificationService;
use Livewire\Component;

class OrderChangeWallet extends Component
{
    public int $orderId;

    public bool $showEditModal     = false;
    public bool $showRemoveModal   = false;

    public string $cashReceived    = '';
    public string $changeToWallet  = '';
    public string $errorMessage    = '';

    public string $removalReason   = '';
    public string $removalError    = '';

    protected function order(): Order
    {
        return Order::findOrFail($this->orderId);
    }

    protected function amountDue(): float
    {
        $order = $this->order();
        return max(0, (float) $order->total_price - (float) $order->wallet_amount_used);
    }

    protected function existingTransaction(): ?WalletTransaction
    {
        $order = $this->order();
        return WalletTransaction::where('reference', 'Change from order #' . $order->order_code)
            ->where('user_id', $order->user_id)
            ->whereNull('removed_at')
            ->latest()
            ->first();
    }

    public function openEditModal(): void
    {
        $existing  = $this->existingTransaction();
        $amountDue = $this->amountDue();

        $this->cashReceived   = number_format($amountDue + (float) ($existing?->amount ?? 0), 2);
        $this->changeToWallet = '';
        $this->errorMessage   = '';
        $this->showEditModal  = true;
    }

    public function calculateChange(): void
    {
        $amountDue = $this->amountDue();
        $received  = (float) $this->cashReceived;

        if ($received <= $amountDue) {
            $this->errorMessage   = 'Cash received (A$' . number_format($received, 2) . ') must be more than amount due (A$' . number_format($amountDue, 2) . ').';
            $this->changeToWallet = '';
            return;
        }

        $this->errorMessage   = '';
        $this->changeToWallet = number_format(round($received - $amountDue, 2), 2);
    }

    public function saveEdit(): void
    {
        $amountDue = $this->amountDue();
        $received  = (float) $this->cashReceived;

        if ($received <= $amountDue) {
            $this->errorMessage = 'Cash received must be more than A$' . number_format($amountDue, 2) . '.';
            return;
        }

        if (blank($this->changeToWallet)) {
            $this->errorMessage = 'Please click Calculate first.';
            return;
        }

        $existing = $this->existingTransaction();
        if (! $existing) {
            $this->showEditModal = false;
            return;
        }

        $order     = $this->order();
        $newChange = round($received - $amountDue, 2);
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

        $this->showEditModal = false;
    }

    public function openRemoveModal(): void
    {
        $this->removalReason = '';
        $this->removalError  = '';
        $this->showRemoveModal = true;
    }

    public function confirmRemove(): void
    {
        if (blank(trim($this->removalReason))) {
            $this->removalError = 'Please enter a reason for removing this change transaction.';
            return;
        }

        $existing = $this->existingTransaction();
        if (! $existing) {
            $this->showRemoveModal = false;
            return;
        }

        $order     = $this->order();
        $staffName = auth()->user()->getFilamentName();

        // Reverse the wallet balance
        $order->user->decrement('wallet_balance', (float) $existing->amount);

        // Mark as removed — keep the record for audit
        $existing->removed_at     = now('UTC');
        $existing->removed_by     = $staffName;
        $existing->removal_reason = trim($this->removalReason);
        $existing->save();

        // Create a new debit record so the reversal is visible in wallet history
        WalletTransaction::create([
            'user_id'     => $order->user_id,
            'branch_id'   => $order->branch_id,
            'type'        => 'reversal',
            'amount'      => $existing->amount,
            'reference'   => 'Change from order #' . $order->order_code,
            'notes'       => 'Change reversal from order #' . $order->order_code . ' — ' . trim($this->removalReason),
            'actioned_by' => $staffName,
        ]);

        $order->updated_by = $staffName;
        $order->save();

        PushNotificationService::sendLocalized($order->user_id, 'wallet_payment', number_format($existing->amount, 2));

        $this->showRemoveModal = false;
    }

    public function render()
    {
        $order = $this->order();
        $transactions = WalletTransaction::where('user_id', $order->user_id)
            ->where('reference', 'Change from order #' . $order->order_code)
            ->orderBy('created_at', 'desc')
            ->get();

        $amountDue = $this->amountDue();
        $hasRemoved = $transactions->contains(fn ($t) => $t->isRemoved());

        return view('livewire.order-change-wallet', compact('transactions', 'amountDue', 'hasRemoved'));
    }
}
