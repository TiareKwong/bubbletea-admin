@php
    $changeTransactions = \App\Models\WalletTransaction::where('user_id', $this->record->user_id)
        ->where('reference', 'Change from order #' . $this->record->order_code)
        ->orderBy('created_at', 'desc')
        ->get();

    $walletUsed = (float) $this->record->wallet_amount_used;
    $amountDue  = max(0, (float) $this->record->total_price - $walletUsed);
@endphp

<x-filament-panels::page>

    {{-- Change Added to Wallet — first, before order details --}}
    @if($changeTransactions->isNotEmpty())
    <x-filament::section>
        <x-slot name="heading">Change Added to Wallet</x-slot>
        <livewire:order-change-wallet :order-id="$this->record->id" />
    </x-filament::section>
    @endif

    @if($walletUsed > 0)
    <x-filament::section>
        <x-slot name="heading">Wallet Payment</x-slot>
        <p style="font-size: 0.9rem; color: #374151;">
            <strong>A${{ number_format($walletUsed, 2) }}</strong> was paid from the customer's wallet for this order.
        </p>
    </x-filament::section>
    @endif

    {{ $this->content }}

</x-filament-panels::page>
