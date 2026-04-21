<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Find Order</x-slot>
        <x-slot name="description">Search by order code to quickly view and action an order.</x-slot>

        <div style="display:flex; align-items:center; gap:0.75rem;">
            <div style="flex:1;">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model="orderCode"
                        wire:keydown.enter="search"
                        placeholder="Enter order code (e.g. A1B2C)"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
            </div>

            <div style="flex-shrink:0;">
                <x-filament::button wire:click="search" icon="heroicon-m-magnifying-glass">
                    Search
                </x-filament::button>
            </div>

            @if($result || $error)
                <div style="flex-shrink:0;">
                    <x-filament::button wire:click="clear" color="gray" icon="heroicon-m-x-mark">
                        Clear
                    </x-filament::button>
                </div>
            @endif
        </div>

        {{-- Error --}}
        @if($error)
            <div style="margin-top:0.75rem; background:#fef2f2; border:1px solid #fecaca; border-radius:0.5rem; padding:0.75rem 1rem; display:flex; align-items:center; gap:0.5rem; color:#dc2626; font-size:0.875rem;">
                <svg style="width:1rem;height:1rem;flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.5a.75.75 0 001.5 0v-4.5zm-.75 7a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/></svg>
                <span>{{ $error }}</span>
            </div>
        @endif

        {{-- Result --}}
        @if($result)
            <div style="margin-top:1rem; border-radius:0.75rem; border:1px solid #e5e7eb; overflow:hidden;">
                {{-- Branch warning --}}
                @if($result['is_other_branch'])
                    <div style="background:#fffbeb; padding:0.5rem 1rem; display:flex; align-items:center; gap:0.5rem; font-size:0.875rem; color:#92400e;">
                        <svg style="width:1rem;height:1rem;flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        This order belongs to <strong>{{ $result['branch'] }}</strong>. You can view it but cannot action it from your current branch.
                    </div>
                @endif

                <div style="padding:1rem; display:flex; flex-wrap:wrap; align-items:center; gap:1.5rem; background:white;">
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Order Code</p>
                        <p style="font-weight:700; font-size:1.1rem; color:#7c3aed; margin:0;">{{ $result['order_code'] }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Customer</p>
                        <p style="font-weight:600; margin:0;">{{ $result['customer'] ?: '—' }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Pick Up</p>
                        <p style="font-weight:600; margin:0;">{{ $result['branch'] }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Total</p>
                        <p style="font-weight:600; margin:0;">A${{ $result['total'] }}</p>
                    </div>
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Status</p>
                        <x-filament::badge
                            :color="match($result['status']) {
                                'Paid', 'Collected', 'Ready' => 'success',
                                'Pending Payment'            => 'warning',
                                'Payment Verification', 'Points Verification' => 'danger',
                                'Preparing'                  => 'info',
                                'Cancelled'                  => 'gray',
                                default                      => 'gray',
                            }"
                        >{{ $result['status'] }}</x-filament::badge>
                    </div>
                    <div>
                        <p style="font-size:0.75rem; color:#6b7280; margin:0 0 2px;">Payment</p>
                        <p style="font-weight:600; margin:0;">{{ $result['payment'] }}</p>
                    </div>
                    <div style="margin-left:auto;">
                        <x-filament::button tag="a" :href="$result['url']" icon="heroicon-m-eye">
                            View Order
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
