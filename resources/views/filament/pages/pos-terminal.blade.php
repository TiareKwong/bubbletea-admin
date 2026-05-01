<x-filament-panels::page>

@php
    $allFlavors = \App\Models\Flavor::where('status', 'Available')
        ->when($selectedBranchId, function ($q) use ($selectedBranchId) {
            $q->where(function ($inner) use ($selectedBranchId) {
                $inner->doesntHave('branches')
                      ->orWhereHas('branches', fn ($bq) => $bq->where('branches.id', $selectedBranchId));
            });
        })
        ->orderBy('category')->orderBy('name')
        ->get(['id', 'name', 'type', 'category', 'image_url', 'small_price', 'regular_price', 'large_price'])
        ->toArray();

    $allToppings = \App\Models\Topping::where('status', 'Available')->orderBy('name')->get(['id', 'name', 'price'])->toArray();
    $allBranches = \App\Models\Branch::where('is_active', true)->orderBy('name')->get(['id', 'name'])->toArray();

    $filteredFlavors = $allFlavors;
    if ($search !== '') {
        $s = mb_strtolower(trim($search));
        $filteredFlavors = array_values(array_filter($filteredFlavors, fn($f) => str_contains(mb_strtolower($f['name']), $s)));
    }
    if ($categoryFilter !== '') {
        $filteredFlavors = array_values(array_filter($filteredFlavors, fn($f) => $f['category'] === $categoryFilter));
    }

    $categories = collect($allFlavors)->pluck('category')->unique()->filter()->sort()->values()->toArray();
    $cartTotal  = round(array_sum(array_column($cart, 'line_total')), 2);
    $isGuest    = $customerId === 'guest';

    $modalFlavor  = null;
    $modalPrice   = 0.0;
    $toppingCount = (int) array_sum(array_filter($modalToppingMap, fn($q) => $q > 0));

    if ($modalOpen && $modalFlavorId) {
        $modalFlavor = collect($allFlavors)->firstWhere('id', $modalFlavorId);
        if (!$modalFlavor) {
            $modalFlavor = \App\Models\Flavor::find($modalFlavorId, ['id', 'name', 'type', 'category', 'image_url', 'small_price', 'regular_price', 'large_price'])?->toArray();
        }
        if ($modalFlavor) {
            $base = match($modalSize) {
                'Small' => (float) $modalFlavor['small_price'],
                'Large' => (float) $modalFlavor['large_price'],
                default => (float) $modalFlavor['regular_price'],
            };
            $toppingTotal = 0.0;
            $filteredMap  = array_filter($modalToppingMap, fn($q) => $q > 0);
            if ($filteredMap) {
                $prices = \App\Models\Topping::whereIn('id', array_keys($filteredMap))->pluck('price', 'id');
                foreach ($filteredMap as $tid => $qty) {
                    $toppingTotal += (float) ($prices[$tid] ?? 0) * $qty;
                }
            }
            $modalPrice = round(($base + $toppingTotal) * max(1, $modalQty), 2);
        }
    }

    $selectedCustomer = null;
    if (!$isGuest && $customerId) {
        $selectedCustomer = \App\Models\User::find((int)$customerId, ['id', 'first_name', 'last_name', 'email', 'wallet_balance'])?->toArray();
    }
    $customers = [];
    if (strlen(trim($customerSearch)) >= 2) {
        $s = trim($customerSearch);
        $customers = \App\Models\User::where('is_staff', false)
            ->where(fn($q) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone_number', 'like', "%{$s}%"))
            ->limit(8)->get(['id', 'first_name', 'last_name', 'email', 'wallet_balance'])->toArray();
    }

    $paymentMethods = [
        'Cash'          => ['icon' => '💵', 'label' => 'Cash'],
        'EFTPOS'        => ['icon' => '💳', 'label' => 'EFTPOS'],
        'Bank Transfer' => ['icon' => '🏦', 'label' => 'Bank'],
        'Wallet'        => ['icon' => '👜', 'label' => 'Wallet'],
        'Points'        => ['icon' => '⭐', 'label' => 'Points'],
    ];
    $iceOptions   = ['None' => 'No Ice', 'Less' => 'Less Ice', 'Regular' => 'Regular', 'Extra' => 'Extra Ice'];
    $sugarOptions = ['0%' => '0%', '25%' => '25%', '50%' => '50%', '75%' => '75%', '100%' => '100%'];
@endphp

@if ($errorMessage)
<div style="margin-bottom:0.75rem;border-radius:0.5rem;background:#fef2f2;border:1px solid #fecaca;padding:0.75rem 1rem;font-size:0.875rem;color:#b91c1c;">
    {{ $errorMessage }}
</div>
@endif

<div style="display:flex;gap:1rem;align-items:flex-start;">

    {{-- LEFT: Flavor catalog --}}
    <div style="display:flex;flex-direction:column;flex:1 1 0%;min-width:0;">

        {{-- Search + Branch --}}
        <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
            <div style="position:relative;flex:1;">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search flavors…"
                    style="width:100%;padding:0.5rem 1rem 0.5rem 2.25rem;border-radius:0.5rem;border:1px solid #e5e7eb;background:white;font-size:0.875rem;color:#111827;outline:none;box-sizing:border-box;">
                <svg width="16" height="16" style="position:absolute;top:0.625rem;left:0.625rem;color:#9ca3af;pointer-events:none;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <select wire:model.live="selectedBranchId"
                style="border-radius:0.5rem;border:1px solid #e5e7eb;background:white;font-size:0.875rem;color:#111827;padding:0.5rem 0.75rem;outline:none;">
                <option value="">All Branches</option>
                @foreach ($allBranches as $b)
                    <option value="{{ $b['id'] }}" @selected($selectedBranchId == $b['id'])>{{ $b['name'] }}</option>
                @endforeach
            </select>
        </div>

        {{-- Category pills (horizontally scrollable) --}}
        <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;overflow-x:auto;flex-shrink:0;padding-bottom:0.25rem;scrollbar-width:none;">
            <style>div::-webkit-scrollbar{display:none;}</style>
            <button wire:click="setCategoryFilter('')"
                style="flex-shrink:0;padding:0.25rem 0.875rem;border-radius:9999px;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;white-space:nowrap;background:{{ $categoryFilter === '' ? '#7c3aed' : '#f3f4f6' }};color:{{ $categoryFilter === '' ? 'white' : '#4b5563' }};">
                All
            </button>
            @foreach ($categories as $cat)
                <button wire:click="setCategoryFilter('{{ e($cat) }}')"
                    style="flex-shrink:0;padding:0.25rem 0.875rem;border-radius:9999px;font-size:0.75rem;font-weight:500;border:none;cursor:pointer;white-space:nowrap;text-transform:capitalize;background:{{ $categoryFilter === $cat ? '#7c3aed' : '#f3f4f6' }};color:{{ $categoryFilter === $cat ? 'white' : '#4b5563' }};">
                    {{ ucfirst(str_replace('_', ' ', strtolower($cat))) }}
                </button>
            @endforeach
        </div>

        {{-- Scrollable wrapper — grid takes natural height, wrapper clips & scrolls --}}
        <div style="overflow-y:auto;height:calc(100vh - 17rem);min-height:300px;padding-right:0.25rem;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:1rem;padding:0.25rem 0.25rem 0.5rem;">
                @forelse ($filteredFlavors as $flavor)
                    <button wire:click="openFlavorModal({{ $flavor['id'] }})"
                        style="display:flex;flex-direction:column;border-radius:0.875rem;border:1px solid #e5e7eb;background:white;overflow:hidden;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.06);padding:0;">
                        {{-- Image: fixed 145px height --}}
                        @if ($flavor['image_url'])
                            <div style="width:100%;height:145px;overflow:hidden;background:#f3f4f6;">
                                <img src="{{ $flavor['image_url'] }}" alt="{{ $flavor['name'] }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                            </div>
                        @else
                            <div style="width:100%;height:145px;background:linear-gradient(135deg,#ede9fe,#ddd6fe);display:flex;align-items:center;justify-content:center;">
                                <span style="font-size:2.5rem;">🧋</span>
                            </div>
                        @endif
                        {{-- Name --}}
                        <div style="padding:0.5rem 0.625rem 0.625rem;background:white;">
                            <p style="font-size:0.8rem;font-weight:600;color:#1f2937;line-height:1.3;margin:0;text-align:left;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $flavor['name'] }}</p>
                        </div>
                    </button>
                @empty
                    <div style="grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4rem 0;color:#9ca3af;">
                        <p style="font-size:2.5rem;margin:0;">🧋</p>
                        <p style="font-size:0.875rem;margin:0.5rem 0 0;">No flavors found</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- RIGHT: Cart --}}
    <div style="display:flex;flex-direction:column;flex-shrink:0;width:300px;background:white;border-radius:0.75rem;border:1px solid #e5e7eb;overflow:hidden;position:sticky;top:1rem;height:calc(100vh - 8rem);">

        {{-- Customer --}}
        <div style="padding:1rem 1rem 0.75rem;border-bottom:1px solid #f3f4f6;">
            <p style="font-size:0.625rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">Customer</p>
            @if ($isGuest)
                <div style="position:relative;">
                    <input wire:model.live.debounce.400ms="customerSearch" type="text"
                        placeholder="Walk-in or search…"
                        style="width:100%;font-size:0.75rem;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid #e5e7eb;background:#f9fafb;color:#1f2937;outline:none;box-sizing:border-box;">
                    @if (count($customers) > 0)
                        <div style="position:absolute;z-index:10;width:100%;margin-top:0.25rem;background:white;border:1px solid #e5e7eb;border-radius:0.5rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-height:200px;overflow-y:auto;">
                            @foreach ($customers as $c)
                                <button wire:click="selectCustomer({{ $c['id'] }})" style="width:100%;text-align:left;padding:0.5rem 0.75rem;border:none;background:none;cursor:pointer;">
                                    <p style="font-size:0.75rem;font-weight:500;color:#1f2937;margin:0;">{{ $c['first_name'] }} {{ $c['last_name'] }}</p>
                                    <p style="font-size:0.75rem;color:#9ca3af;margin:0;">{{ $c['email'] }} · ${{ number_format((float)$c['wallet_balance'],2) }}</p>
                                </button>
                            @endforeach
                        </div>
                    @elseif (strlen(trim($customerSearch)) >= 2)
                        <div style="position:absolute;z-index:10;width:100%;margin-top:0.25rem;background:white;border:1px solid #e5e7eb;border-radius:0.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.1);padding:0.5rem 0.75rem;">
                            <p style="font-size:0.75rem;color:#9ca3af;margin:0;">No matching customers</p>
                        </div>
                    @endif
                </div>
            @else
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <p style="font-size:0.75rem;font-weight:600;color:#7c3aed;margin:0;">{{ $selectedCustomer['first_name'] ?? '' }} {{ $selectedCustomer['last_name'] ?? '' }}</p>
                        <p style="font-size:0.75rem;color:#9ca3af;margin:0;">💰 ${{ number_format((float)($selectedCustomer['wallet_balance'] ?? 0),2) }}</p>
                    </div>
                    <button wire:click="clearCustomer" style="font-size:0.875rem;color:#9ca3af;border:none;background:none;cursor:pointer;">✕</button>
                </div>
            @endif
        </div>

        {{-- Cart items --}}
        <div style="flex:1;overflow-y:auto;padding:0.75rem;">
            @forelse ($cart as $i => $item)
                <div style="background:#f9fafb;border-radius:0.5rem;padding:0.5rem 0.75rem;margin-bottom:0.5rem;">
                    <div style="display:flex;align-items:flex-start;gap:0.5rem;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:0.75rem;font-weight:600;color:#1f2937;line-height:1.25;margin:0;">{{ $item['flavor_name'] }}</p>
                            <p style="font-size:0.75rem;color:#6b7280;margin:0;">
                                {{ $item['size'] }}{{ ($item['ice'] && $item['ice'] !== 'Regular') ? ' · '.$item['ice'].' Ice' : '' }}{{ ($item['sugar'] && $item['sugar'] !== '100%') ? ' · '.$item['sugar'].' Sugar' : '' }}
                            </p>
                            @if ($item['topping_label'])
                                <p style="font-size:0.75rem;color:#9ca3af;margin:0.125rem 0 0;">{{ $item['topping_label'] }}</p>
                            @endif
                        </div>
                        <div style="flex-shrink:0;text-align:right;">
                            <p style="font-size:0.75rem;font-weight:700;color:#111827;margin:0;">${{ number_format($item['line_total'],2) }}</p>
                            <div style="display:flex;align-items:center;gap:0.25rem;margin-top:0.25rem;justify-content:flex-end;">
                                <button wire:click="adjustCartQty({{ $i }}, -1)" style="width:1.25rem;height:1.25rem;border-radius:0.25rem;background:#e5e7eb;color:#374151;font-size:0.75rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;">−</button>
                                <span style="font-size:0.75rem;width:1rem;text-align:center;">{{ $item['qty'] }}</span>
                                <button wire:click="adjustCartQty({{ $i }}, 1)" style="width:1.25rem;height:1.25rem;border-radius:0.25rem;background:#e5e7eb;color:#374151;font-size:0.75rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;">+</button>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:0.75rem;margin-top:0.375rem;">
                        <button wire:click="editCartItem({{ $i }})" style="font-size:0.75rem;color:#7c3aed;border:none;background:none;cursor:pointer;">Edit</button>
                        <button wire:click="removeCartItem({{ $i }})" style="font-size:0.75rem;color:#f87171;border:none;background:none;cursor:pointer;">Remove</button>
                    </div>
                </div>
            @empty
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2.5rem 0;color:#d1d5db;">
                    <p style="font-size:1.75rem;margin:0;">🛒</p>
                    <p style="font-size:0.75rem;margin:0.25rem 0 0;">Tap a flavor to add it</p>
                </div>
            @endforelse
        </div>

        {{-- Total + Payment + Charge --}}
        <div style="border-top:1px solid #f3f4f6;padding:0.75rem 1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                <span style="font-size:0.875rem;font-weight:600;color:#374151;">Total</span>
                <span style="font-size:1.25rem;font-weight:700;color:#111827;">${{ number_format($cartTotal,2) }}</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.375rem;margin-bottom:0.75rem;">
                @foreach ($paymentMethods as $method => $info)
                    @php $disabled = in_array($method, ['Wallet','Points']) && $isGuest; @endphp
                    <button
                        @if(!$disabled) wire:click="setPaymentMethod('{{ $method }}')" @endif
                        style="display:flex;flex-direction:column;align-items:center;padding:0.5rem 0.25rem;border-radius:0.5rem;font-size:0.75rem;font-weight:500;cursor:{{ $disabled ? 'not-allowed' : 'pointer' }};opacity:{{ $disabled ? '0.4' : '1' }};border:1px solid {{ $paymentMethod === $method ? '#7c3aed' : '#e5e7eb' }};background:{{ $paymentMethod === $method ? '#f5f3ff' : 'white' }};color:{{ $paymentMethod === $method ? '#6d28d9' : ($disabled ? '#d1d5db' : '#6b7280') }};"
                        @if($disabled) disabled @endif
                    >
                        <span style="font-size:1.1rem;line-height:1;">{{ $info['icon'] }}</span>
                        <span>{{ $info['label'] }}</span>
                    </button>
                @endforeach
            </div>

            @if ($paymentMethod === 'Bank Transfer')
                <input wire:model.live="paymentReference" type="text" placeholder="Transfer reference…"
                    style="width:100%;font-size:0.75rem;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid #e5e7eb;background:#f9fafb;color:#1f2937;outline:none;box-sizing:border-box;margin-bottom:0.75rem;">
            @endif

            <button wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder"
                style="width:100%;padding:0.75rem;border-radius:0.75rem;font-weight:700;font-size:0.875rem;border:none;cursor:{{ !empty($cart) ? 'pointer' : 'not-allowed' }};background:{{ !empty($cart) ? '#7c3aed' : '#e5e7eb' }};color:{{ !empty($cart) ? 'white' : '#9ca3af' }};box-shadow:0 1px 3px rgba(0,0,0,0.1);"
                @if(empty($cart)) disabled @endif>
                <span wire:loading.remove wire:target="placeOrder">💳 Charge ${{ number_format($cartTotal,2) }}</span>
                <span wire:loading wire:target="placeOrder">Processing…</span>
            </button>
        </div>
    </div>
</div>

{{-- Flavor Config Modal --}}
@if ($modalOpen && $modalFlavor)
    @php
        $f        = $modalFlavor;
        $hasSmall = (float)$f['small_price'] > 0;
        $hasReg   = (float)$f['regular_price'] > 0;
        $hasLarge = (float)$f['large_price'] > 0;
        $isDrink  = in_array($f['type'], ['drink','ice_cream','smoothie']);
    @endphp
    <div style="position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;padding:1rem;">
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.6);" wire:click="closeModal"></div>
        <div style="position:relative;background:white;border-radius:1rem;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);width:100%;max-width:420px;overflow:hidden;z-index:1;">

            <div style="position:relative;height:7rem;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);">
                @if ($f['image_url'])
                    <img src="{{ $f['image_url'] }}" alt="{{ $f['name'] }}" style="width:100%;height:100%;object-fit:cover;">
                    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,0.45),transparent);"></div>
                @endif
                <div style="position:absolute;bottom:0.75rem;left:1rem;">
                    <h2 style="font-weight:700;color:white;font-size:1.1rem;text-shadow:0 1px 3px rgba(0,0,0,0.5);margin:0;">{{ $f['name'] }}</h2>
                    <p style="color:rgba(255,255,255,0.8);font-size:0.7rem;text-transform:capitalize;margin:0;">{{ str_replace('_',' ',$f['type']) }}</p>
                </div>
                <button wire:click="closeModal" style="position:absolute;top:0.75rem;right:0.75rem;color:white;font-size:0.875rem;display:flex;align-items:center;justify-content:center;width:1.75rem;height:1.75rem;border-radius:50%;background:rgba(255,255,255,0.2);border:none;cursor:pointer;">✕</button>
            </div>

            <div style="padding:1.25rem;max-height:68vh;overflow-y:auto;">

                {{-- Size --}}
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.625rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">Size</p>
                    <div style="display:flex;gap:0.5rem;">
                        @if ($hasSmall)
                            <button wire:click="setModalSize('Small')" style="flex:1;padding:0.5rem;border-radius:0.75rem;border:1px solid {{ $modalSize==='Small' ? '#8b5cf6' : '#e5e7eb' }};background:{{ $modalSize==='Small' ? '#f5f3ff' : 'white' }};color:{{ $modalSize==='Small' ? '#6d28d9' : '#4b5563' }};font-size:0.875rem;font-weight:500;text-align:center;cursor:pointer;">
                                Small<br><span style="font-size:0.75rem;font-weight:400;">${{ number_format((float)$f['small_price'],2) }}</span>
                            </button>
                        @endif
                        @if ($hasReg)
                            <button wire:click="setModalSize('Regular')" style="flex:1;padding:0.5rem;border-radius:0.75rem;border:1px solid {{ $modalSize==='Regular' ? '#3b82f6' : '#e5e7eb' }};background:{{ $modalSize==='Regular' ? '#eff6ff' : 'white' }};color:{{ $modalSize==='Regular' ? '#1d4ed8' : '#4b5563' }};font-size:0.875rem;font-weight:500;text-align:center;cursor:pointer;">
                                Regular<br><span style="font-size:0.75rem;font-weight:400;">${{ number_format((float)$f['regular_price'],2) }}</span>
                            </button>
                        @endif
                        @if ($hasLarge)
                            <button wire:click="setModalSize('Large')" style="flex:1;padding:0.5rem;border-radius:0.75rem;border:1px solid {{ $modalSize==='Large' ? '#10b981' : '#e5e7eb' }};background:{{ $modalSize==='Large' ? '#ecfdf5' : 'white' }};color:{{ $modalSize==='Large' ? '#065f46' : '#4b5563' }};font-size:0.875rem;font-weight:500;text-align:center;cursor:pointer;">
                                Large<br><span style="font-size:0.75rem;font-weight:400;">${{ number_format((float)$f['large_price'],2) }}</span>
                            </button>
                        @endif
                    </div>
                </div>

                @if ($isDrink)
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.625rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">🧊 Ice</p>
                    <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
                        @foreach ($iceOptions as $val => $label)
                            <button wire:click="setModalIce('{{ $val }}')" style="padding:0.375rem 0.75rem;border-radius:0.5rem;border:1px solid {{ $modalIce===$val ? '#0ea5e9' : '#e5e7eb' }};background:{{ $modalIce===$val ? '#f0f9ff' : 'white' }};color:{{ $modalIce===$val ? '#0369a1' : '#4b5563' }};font-size:0.75rem;font-weight:500;cursor:pointer;">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div style="margin-bottom:1rem;">
                    <p style="font-size:0.625rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 0.5rem;">🍬 Sugar</p>
                    <div style="display:flex;gap:0.375rem;flex-wrap:wrap;">
                        @foreach ($sugarOptions as $val => $label)
                            <button wire:click="setModalSugar('{{ $val }}')" style="padding:0.375rem 0.75rem;border-radius:0.5rem;border:1px solid {{ $modalSugar===$val ? '#f59e0b' : '#e5e7eb' }};background:{{ $modalSugar===$val ? '#fffbeb' : 'white' }};color:{{ $modalSugar===$val ? '#92400e' : '#4b5563' }};font-size:0.75rem;font-weight:500;cursor:pointer;">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @endif

                @if (count($allToppings) > 0)
                <div style="margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                        <p style="font-size:0.625rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin:0;">🫙 Toppings</p>
                        <p style="font-size:0.75rem;color:#9ca3af;margin:0;">{{ $toppingCount }}/4</p>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;">
                        @foreach ($allToppings as $topping)
                            @php
                                $tQty   = $modalToppingMap[$topping['id']] ?? 0;
                                $canAdd = $toppingCount < 4;
                                $isSel  = $tQty > 0;
                            @endphp
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid {{ $isSel ? '#7c3aed' : '#e5e7eb' }};background:{{ $isSel ? '#f5f3ff' : '#f9fafb' }};">
                                <div style="min-width:0;margin-right:0.5rem;">
                                    <p style="font-size:0.75rem;font-weight:500;color:#1f2937;line-height:1.25;margin:0;">{{ $topping['name'] }}</p>
                                    <p style="font-size:0.75rem;color:#9ca3af;margin:0;">+${{ number_format((float)$topping['price'],2) }}</p>
                                </div>
                                <div style="display:flex;align-items:center;gap:0.25rem;flex-shrink:0;">
                                    @if ($isSel)
                                        <button wire:click="adjustModalTopping({{ $topping['id'] }}, -1)" style="width:1.5rem;height:1.5rem;border-radius:50%;background:#e5e7eb;font-size:0.75rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;">−</button>
                                        <span style="font-size:0.75rem;font-weight:700;width:1rem;text-align:center;color:#7c3aed;">{{ $tQty }}</span>
                                    @endif
                                    <button wire:click="adjustModalTopping({{ $topping['id'] }}, 1)"
                                        style="width:1.5rem;height:1.5rem;border-radius:50%;font-size:0.75rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;background:{{ ($canAdd || $isSel) ? '#7c3aed' : '#f3f4f6' }};color:{{ ($canAdd || $isSel) ? 'white' : '#d1d5db' }};"
                                        @if(!$canAdd && !$isSel) disabled @endif>+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div style="border-top:1px solid #f3f4f6;padding-top:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <div style="display:flex;align-items:center;gap:0.75rem;">
                            <button wire:click="adjustModalQty(-1)" style="width:2.25rem;height:2.25rem;display:flex;align-items:center;justify-content:center;border-radius:50%;border:2px solid #d1d5db;color:#374151;font-weight:700;font-size:1.125rem;background:white;cursor:pointer;">−</button>
                            <span style="font-size:1.5rem;font-weight:700;width:2rem;text-align:center;color:#111827;">{{ $modalQty }}</span>
                            <button wire:click="adjustModalQty(1)" style="width:2.25rem;height:2.25rem;display:flex;align-items:center;justify-content:center;border-radius:50%;border:2px solid #d1d5db;color:#374151;font-weight:700;font-size:1.125rem;background:white;cursor:pointer;">+</button>
                        </div>
                        <div style="text-align:right;">
                            <p style="font-size:0.75rem;color:#9ca3af;margin:0;">Item total</p>
                            <p style="font-size:1.5rem;font-weight:700;color:#111827;margin:0;">${{ number_format($modalPrice,2) }}</p>
                        </div>
                    </div>
                    <button wire:click="addToCart" style="width:100%;padding:0.75rem;border-radius:0.75rem;background:#7c3aed;color:white;font-weight:700;font-size:0.875rem;border:none;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        {{ $modalEditIndex !== null ? '✏️ Update Item' : '+ Add to Order' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

</x-filament-panels::page>
