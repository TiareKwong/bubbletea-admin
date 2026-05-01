<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Flavor;
use App\Models\Order;
use App\Models\Reward;
use App\Models\Topping;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PushNotificationService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Orders';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    // --------------------------------------------------------------------------
    // Form (used by Create page only — edit is disabled)
    // --------------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            // ── Step 1: Customer ─────────────────────────────────────────────
            Section::make('Step 1 — Customer')
                ->description('Is this a registered customer or a walk-in? Search by name or email. Leave as Guest for walk-ins.')
                ->icon('heroicon-o-user')
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->default('guest')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            $results = User::where('is_staff', false)
                                ->where('email', '!=', 'guest@internal.local')
                                ->where(fn ($q) => $q
                                    ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                    ->orWhere('email', 'like', "%{$search}%")
                                )
                                ->orderBy('first_name')
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (User $u): array => [
                                    $u->id => $u->full_name . ' — ' . $u->email,
                                ])
                                ->toArray();

                            return ['guest' => '— Guest (Walk-in) —'] + $results;
                        })
                        ->getOptionLabelUsing(fn ($value): string => $value === 'guest'
                            ? '— Guest (Walk-in) —'
                            : (User::find($value)?->full_name . ' — ' . User::find($value)?->email ?? $value)
                        ),
                ]),

            // ── Step 2: Items ────────────────────────────────────────────────
            Section::make('Step 2 — Order Items')
                ->description('Add each drink the customer wants. Click "Add Item" for multiple drinks.')
                ->icon('heroicon-o-beaker')
                ->schema([
                    Repeater::make('items')
                        ->label('')
                        ->minItems(1)
                        ->addActionLabel('+ Add Another Drink')
                        ->columns(4)
                        ->schema([

                            // Row 1: Flavor (wide) + Size
                            Select::make('flavor_id')
                                ->label('🧋 Flavor')
                                ->options(
                                    Flavor::where('status', 'Available')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                ->columnSpan(3),

                            Select::make('size')
                                ->label('Size')
                                ->options(function (Get $get): array {
                                    $flavor = Flavor::find($get('flavor_id'));
                                    if (! $flavor) {
                                        return ['Regular' => 'Regular'];
                                    }
                                    $options = [];
                                    if ((float) $flavor->small_price   > 0) $options['Small']   = 'Small — $' . number_format((float) $flavor->small_price, 2);
                                    if ((float) $flavor->regular_price > 0) $options['Regular'] = 'Regular — $' . number_format((float) $flavor->regular_price, 2);
                                    if ((float) $flavor->large_price   > 0) $options['Large']   = 'Large — $' . number_format((float) $flavor->large_price, 2);
                                    return $options;
                                })
                                ->required()
                                ->default('Regular')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                ->columnSpan(1),

                            // Row 2: Ice + Sugar + Quantity
                            Select::make('ice')
                                ->label('🧊 Ice Level')
                                ->options([
                                    'None'    => 'No Ice',
                                    'Less'    => 'Less Ice',
                                    'Regular' => 'Regular Ice',
                                    'Extra'   => 'Extra Ice',
                                ])
                                ->default('Regular')
                                ->columnSpan(1),

                            Select::make('sugar')
                                ->label('🍬 Sugar Level')
                                ->options([
                                    '0%'   => '0% (No Sugar)',
                                    '25%'  => '25%',
                                    '50%'  => '50%',
                                    '75%'  => '75%',
                                    '100%' => '100% (Full Sugar)',
                                ])
                                ->default('100%')
                                ->columnSpan(1),

                            TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                ->columnSpan(1),

                            TextInput::make('price')
                                ->label('Price (AUD)')
                                ->prefix('$')
                                ->readOnly()
                                ->dehydrated(true)
                                ->default(0)
                                ->columnSpan(1),

                            // Row 3: Toppings (full width)
                            Repeater::make('toppings')
                                ->label('🫙 Toppings (optional, max 4 total qty)')
                                ->addActionLabel('+ Add Topping')
                                ->addable(function (Get $get): bool {
                                    $rows  = $get('toppings') ?? [];
                                    $total = array_sum(array_column($rows, 'qty'));
                                    return $total < 4;
                                })
                                ->columns(2)
                                ->columnSpan(4)
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                ->schema([
                                    Select::make('topping_id')
                                        ->label('Topping')
                                        ->options(
                                            Topping::where('status', 'Available')
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                        )
                                        ->nullable()
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                        ->columnSpan(1),

                                    TextInput::make('qty')
                                        ->label('Qty')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1)
                                        ->maxValue(function (Get $get): int {
                                            $rows    = $get('../../toppings') ?? [];
                                            $current = (int) ($get('qty') ?? 1);
                                            $total   = array_sum(array_column($rows, 'qty'));
                                            return max(1, 4 - ($total - $current));
                                        })
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculateItemPrice($get, $set))
                                        ->columnSpan(1),
                                ]),
                        ]),
                ]),

            // ── Step 3: Payment ──────────────────────────────────────────────
            Section::make('Step 2b — Branch')
                ->description('Which branch is this order for?')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Select::make('branch_id')
                        ->label('Branch')
                        ->relationship('branch', 'name', fn ($query) => $query->where('is_active', true))
                        ->default(fn () => app(\App\Services\BranchContext::class)->getId())
                        ->searchable()
                        ->preload(),
                ]),

            Section::make('Step 3 — Payment')
                ->description('Select how the customer is paying. Only enter a reference for Bank Transfer.')
                ->icon('heroicon-o-banknotes')
                ->columns(2)
                ->schema([
                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->options([
                            'Cash'          => '💵 Cash',
                            'EFTPOS'        => '💳 EFTPOS',
                            'Bank Transfer' => '🏦 Bank Transfer',
                        ])
                        ->required()
                        ->default('Cash'),

                    TextInput::make('payment_reference')
                        ->label('Bank Transfer Reference')
                        ->maxLength(100)
                        ->placeholder('e.g. customer phone number')
                        ->helperText('Only required for Bank Transfer orders.'),
                ]),
        ]);
    }

    /**
     * Recalculates the unit price for one item row based on flavor, size, and toppings.
     * Called reactively whenever any of those fields change.
     */
    public static function recalculateItemPrice(Get $get, Set $set): void
    {
        $flavorId   = $get('flavor_id');
        $size       = $get('size') ?? 'Regular';
        $toppingIds = $get('toppings') ?? [];

        if (! $flavorId) {
            return;
        }

        $flavor = Flavor::find($flavorId);
        if (! $flavor) {
            return;
        }

        $basePrice = match ($size) {
            'Large' => (float) $flavor->large_price,
            'Small' => (float) $flavor->small_price,
            default => (float) $flavor->regular_price,
        };

        // Toppings is now a repeater: [{topping_id, qty}, ...]
        $toppingRows = $get('toppings') ?? [];
        $toppingTotal = 0.0;

        if (! empty($toppingRows)) {
            $ids = array_filter(array_column($toppingRows, 'topping_id'));
            if (! empty($ids)) {
                $prices = Topping::whereIn('id', $ids)->pluck('price', 'id');
                foreach ($toppingRows as $row) {
                    $id  = $row['topping_id'] ?? null;
                    $qty = max(1, (int) ($row['qty'] ?? 1));
                    if ($id && isset($prices[$id])) {
                        $toppingTotal += (float) $prices[$id] * $qty;
                    }
                }
            }
        }

        $quantity = max(1, (int) ($get('quantity') ?? 1));
        $set('price', round(($basePrice + $toppingTotal) * $quantity, 2));
    }

    // --------------------------------------------------------------------------
    // Infolist (used by View page)
    // --------------------------------------------------------------------------

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Details')
                ->columns(2)
                ->schema([
                    TextEntry::make('order_code')
                        ->label('Order Code')
                        ->badge()
                        ->color('primary'),

                    TextEntry::make('order_status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'Pending Payment'      => 'warning',
                            'Payment Verification' => 'info',
                            'Points Verification'  => 'info',
                            'Paid'                 => 'success',
                            'Preparing'            => 'primary',
                            'Ready'                => 'success',
                            'Cancelled'            => 'danger',
                            default                => 'gray',
                        }),

                    TextEntry::make('payment_method')
                        ->label('Payment Method')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'Cash'          => 'success',
                            'Bank Transfer' => 'info',
                            'Points'        => 'warning',
                            'EFTPOS'        => 'primary',
                            default         => 'gray',
                        }),

                    TextEntry::make('payment_reference')
                        ->label('Payment Reference')
                        ->placeholder('None'),

                    TextEntry::make('total_price')
                        ->label('Total')
                        ->money('AUD'),

                    TextEntry::make('points_used')
                        ->label('Points Used'),

                    TextEntry::make('points_earned')
                        ->label('Points Earned'),

                    TextEntry::make('wallet_amount_used')
                        ->label('Wallet Used')
                        ->money('AUD')
                        ->placeholder('—')
                        ->visible(fn ($record): bool => (float) $record->wallet_amount_used > 0),

                    TextEntry::make('amount_to_pay')
                        ->label('Amount to Collect from Customer')
                        ->badge()
                        ->getStateUsing(function ($record): string {
                            if ($record->payment_method === 'Points') {
                                return 'A$0.00 (Paid by Points)';
                            }
                            $amountDue = max(0, (float) $record->total_price - (float) $record->wallet_amount_used);
                            $walletUsed = (float) $record->wallet_amount_used;
                            if ($walletUsed > 0 && $amountDue > 0) {
                                return 'A$' . number_format($amountDue, 2) . ' (' . $record->payment_method . ')';
                            }
                            if ($walletUsed > 0 && $amountDue == 0) {
                                return 'A$0.00 (Fully paid by Wallet)';
                            }
                            return 'A$' . number_format($amountDue, 2);
                        })
                        ->color(function ($record): string {
                            if ($record->payment_method === 'Points') return 'gray';
                            $amountDue  = max(0, (float) $record->total_price - (float) $record->wallet_amount_used);
                            if ($amountDue == 0) return 'success';
                            $unpaid = ! in_array($record->order_status, ['Paid', 'Preparing', 'Ready', 'Collected', 'Cancelled']);
                            return $unpaid ? 'danger' : 'gray';
                        })
                        ->columnSpanFull(),

                    TextEntry::make('collected')
                        ->label('Collected')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                        ->badge()
                        ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                    TextEntry::make('created_at')
                        ->label('Placed At')
                        ->formatStateUsing(fn ($state) => $state
                            ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $state->format('Y-m-d H:i:s'), 'UTC')
                                ->setTimezone('Pacific/Tarawa')
                                ->format('d M Y, h:i A')
                            : '—'),

                    TextEntry::make('updated_by')
                        ->label('Last Updated By')
                        ->placeholder('Not updated'),
                ]),

            Section::make('Promotion Applied')
                ->icon('heroicon-o-tag')
                ->columns(2)
                ->schema([
                    TextEntry::make('promo_title')
                        ->label('Promotion')
                        ->badge()
                        ->color('success')
                        ->placeholder('None'),

                    TextEntry::make('discount_applied')
                        ->label('Discount Saved')
                        ->formatStateUsing(function ($state): string {
                            $val = (float) $state;
                            return $val > 0 ? '- A$' . number_format($val, 2) : '—';
                        })
                        ->badge()
                        ->color(fn ($state): string => (float) $state > 0 ? 'success' : 'gray'),

                    TextEntry::make('free_items')
                        ->label('Free Items')
                        ->columnSpanFull()
                        ->html()
                        ->state(function ($record): string {
                            $items = $record->free_items;
                            if (empty($items)) return '<span style="color:#9ca3af;">—</span>';

                            $rows = collect($items)
                                ->map(fn (string $name): string =>
                                    '<span style="display:inline-block;background:#d1fae5;border:1px solid #6ee7b7;border-radius:9999px;padding:0.15rem 0.75rem;font-size:0.8rem;color:#065f46;margin:0.15rem 0.15rem 0 0;">🎁 ' . e($name) . '</span>'
                                )
                                ->implode('');

                            return '<div>' . $rows . '</div>';
                        }),
                ]),

            Section::make('Customer')
                ->columns(2)
                ->schema([
                    TextEntry::make('user.first_name')
                        ->label('First Name')
                        ->default('Deleted Account'),

                    TextEntry::make('user.last_name')
                        ->label('Last Name')
                        ->placeholder('—'),

                    TextEntry::make('user.email')
                        ->label('Email')
                        ->placeholder('—'),

                    TextEntry::make('user.phone_number')
                        ->label('Phone')
                        ->placeholder('—'),
                ]),

            Section::make('Items')
                ->schema([
                    TextEntry::make('orderItems')
                        ->label('')
                        ->html()
                        ->columnSpanFull()
                        ->state(function ($record): string {
                            $items = $record->orderItems()->with('flavor')->get();

                            if ($items->isEmpty()) {
                                return '<p style="color:#9ca3af;">No items.</p>';
                            }

                            $sizeColors = [
                                'Large'   => '#7c3aed',
                                'Regular' => '#2563eb',
                            ];

                            $html = '';
                            foreach ($items as $item) {
                                $flavorName = e($item->flavor?->name ?? '—');
                                $size       = $item->size;
                                $qty        = (int) $item->quantity;
                                $ice        = $item->ice;
                                $sugar      = $item->sugar;
                                $unitPrice  = 'A$' . number_format($qty > 0 ? (float) $item->price / $qty : (float) $item->price, 2);
                                $sizeColor  = $sizeColors[$size ?? ''] ?? '#374151';

                                // Parse toppings
                                $toppings = $item->toppings;
                                if (is_string($toppings)) {
                                    $toppings = json_decode($toppings, true) ?? [];
                                }
                                $toppingNames = collect((array) $toppings)
                                    ->map(fn ($t) => is_array($t) ? ($t['name'] ?? null) : $t)
                                    ->filter()
                                    ->values();

                                $sizeBadge = $size
                                    ? '<span style="background:' . $sizeColor . ';color:#fff;padding:0.2rem 0.65rem;border-radius:9999px;font-size:0.75rem;font-weight:600;">' . e($size) . '</span>'
                                    : '';

                                $iceSugarHtml = '';
                                if ($ice || $sugar) {
                                    $parts = array_filter([
                                        $ice   ? '🧊 ' . e($ice)   : null,
                                        $sugar ? '🍬 ' . e($sugar) : null,
                                    ]);
                                    $iceSugarHtml = '<div style="display:flex;gap:1.5rem;align-items:center;font-size:0.875rem;color:#4b5563;margin-bottom:0.55rem;flex-wrap:wrap;">'
                                        . implode('', array_map(fn($p) => "<span>{$p}</span>", $parts))
                                        . '<span style="margin-left:auto;font-weight:700;font-size:0.95rem;color:#059669;">' . $unitPrice . ' <span style="font-weight:400;color:#6b7280;font-size:0.8rem;">/ each</span></span>'
                                        . '</div>';
                                } else {
                                    $iceSugarHtml = '<div style="text-align:right;font-weight:700;font-size:0.95rem;color:#059669;margin-bottom:0.55rem;">' . $unitPrice . ' <span style="font-weight:400;color:#6b7280;font-size:0.8rem;">/ each</span></div>';
                                }

                                $toppingHtml = $toppingNames->isEmpty()
                                    ? ''
                                    : '<div style="font-size:0.8rem;color:#6b7280;margin-top:0.3rem;"><span style="font-weight:600;color:#374151;">Toppings: </span>'
                                      . $toppingNames->map(fn ($t) =>
                                          '<span style="display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:9999px;padding:0.1rem 0.6rem;font-size:0.75rem;color:#374151;margin:0.15rem 0.15rem 0 0;">' . e($t) . '</span>'
                                        )->implode('')
                                      . '</div>';

                                $html .= <<<HTML
                                <div style="border:1px solid #e5e7eb;border-radius:0.75rem;padding:1rem 1.25rem;margin-bottom:0.75rem;background:#fafafa;">

                                    <!-- Flavor + size + qty -->
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
                                        <span style="font-size:1.1rem;font-weight:700;color:#111827;">{$flavorName}</span>
                                        <div style="display:flex;gap:0.4rem;align-items:center;">
                                            {$sizeBadge}
                                            <span style="background:#111827;color:#fff;padding:0.2rem 0.65rem;border-radius:9999px;font-size:0.85rem;font-weight:700;">× {$qty}</span>
                                        </div>
                                    </div>

                                    {$iceSugarHtml}
                                    {$toppingHtml}
                                </div>
                                HTML;
                            }

                            return $html;
                        }),
                ]),
        ]);
    }

    // --------------------------------------------------------------------------
    // Table
    // --------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->searchOnBlur()
            ->poll('30s')
            ->columns([
                TextColumn::make('order_code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('user.full_name')
                    ->label('Customer')
                    ->default('Deleted Account')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'user',
                        fn (Builder $q) => $q->where('first_name', 'like', "%{$search}%")
                                             ->orWhere('last_name', 'like', "%{$search}%")
                    )),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('AUD')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cash'          => 'success',
                        'Bank Transfer' => 'info',
                        'Points'        => 'warning',
                        'EFTPOS'        => 'primary',
                        default         => 'gray',
                    }),

                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending Payment'      => 'warning',
                        'Payment Verification' => 'info',
                        'Points Verification'  => 'info',
                        'Paid'                 => 'success',
                        'Preparing'            => 'primary',
                        'Ready'                => 'success',
                        'Cancelled'            => 'danger',
                        default                => 'gray',
                    }),

                IconColumn::make('collected')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Placed')
                    ->formatStateUsing(fn ($state) => $state
                        ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $state->format('Y-m-d H:i:s'), 'UTC')
                            ->setTimezone('Pacific/Tarawa')
                            ->format('d M Y, h:i A')
                        : '—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Order Status')
                    ->placeholder('All Statuses')
                    ->native(false)
                    ->options([
                        'Pending Payment' => 'Pending Payment',
                        'Paid'            => 'Paid',
                        'Preparing'       => 'Preparing',
                        'Ready'           => 'Ready',
                        'Cancelled'       => 'Cancelled',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->placeholder('All Methods')
                    ->native(false)
                    ->options([
                        'Cash'          => 'Cash',
                        'Bank Transfer' => 'Bank Transfer',
                        'Points'        => 'Points',
                        'EFTPOS'        => 'EFTPOS',
                        'Wallet'        => 'Wallet',
                    ]),

                SelectFilter::make('collected')
                    ->label('Collection')
                    ->placeholder('All')
                    ->native(false)
                    ->options([
                        '1' => 'Collected',
                        '0' => 'Not Yet Collected',
                    ]),

            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->actions([
                ViewAction::make(),

                ActionGroup::make([
                // Bank Transfer: verify = paid in one step
                Action::make('markVerified')
                    ->label('Verify & Mark Paid')
                    ->icon('heroicon-o-check-badge')
                    ->color('info')
                    ->visible(fn (Order $record): bool =>
                        $record->payment_method === 'Bank Transfer' &&
                        $record->order_status   === 'Payment Verification'
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Verify & Mark Paid')
                    ->modalDescription('Confirm the bank transfer has been received and mark this order as paid?')
                    ->action(function (Order $record): void {
                        $staffName    = auth()->user()->getFilamentName();
                        $pointsEarned = (int) round((float) $record->total_price * 10);

                        $reward = Reward::firstOrCreate(
                            ['user_id' => $record->user_id],
                            ['points'  => 0]
                        );
                        $reward->points += $pointsEarned;
                        $reward->save();

                        // Deduct wallet amount if used (guard against double-deduction)
                        $walletUsed = (float) $record->wallet_amount_used;
                        $alreadyDeducted = $walletUsed > 0 && WalletTransaction::where('reference', $record->order_code)
                            ->where('user_id', $record->user_id)
                            ->where('type', 'payment')
                            ->exists();
                        if ($walletUsed > 0 && $record->user_id && ! $alreadyDeducted) {
                            $record->user->decrement('wallet_balance', $walletUsed);
                            WalletTransaction::create([
                                'user_id'     => $record->user_id,
                                'type'        => 'payment',
                                'amount'      => $walletUsed,
                                'reference'   => $record->order_code,
                                'notes'       => 'Wallet payment for order #' . $record->order_code,
                                'actioned_by' => $staffName,
                            ]);
                        }

                        $record->order_status  = 'Paid';
                        $record->points_earned = $pointsEarned;
                        $record->updated_by    = $staffName;
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'payment_verified', $record->order_code);
                    }),

                // Mark Paid — Cash / EFTPOS / Points only
                Action::make('markPaid')
                    ->label(fn (Order $record): string =>
                        $record->payment_method === 'EFTPOS' ? 'Confirm EFTPOS Payment' : 'Mark Paid'
                    )
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Order $record): bool =>
                        $record->payment_method !== 'Bank Transfer' &&
                        in_array($record->order_status, ['Pending Payment', 'Payment Verification', 'Points Verification'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Payment')
                    ->modalDescription('Mark this order as paid? This cannot be undone.')
                    ->action(function (Order $record): void {
                        $staffName = auth()->user()->getFilamentName();
                        $reward    = Reward::firstOrCreate(
                            ['user_id' => $record->user_id],
                            ['points'  => 0]
                        );

                        $pointsEarned = 0;

                        if ($record->payment_method === 'Points') {
                            // Deduct the points used to pay for this order
                            $reward->points = max(0, $reward->points - (int) $record->points_used);
                        } else {
                            // Earn points for cash / EFTPOS / wallet payments
                            $pointsEarned   = (int) round((float) $record->total_price * 10);
                            $reward->points += $pointsEarned;
                        }

                        $reward->save();

                        // Deduct wallet amount if used (guard against double-deduction)
                        $walletUsed = (float) $record->wallet_amount_used;
                        $alreadyDeducted = $walletUsed > 0 && WalletTransaction::where('reference', $record->order_code)
                            ->where('user_id', $record->user_id)
                            ->where('type', 'payment')
                            ->exists();
                        if ($walletUsed > 0 && $record->user_id && ! $alreadyDeducted) {
                            $record->user->decrement('wallet_balance', $walletUsed);
                            WalletTransaction::create([
                                'user_id'     => $record->user_id,
                                'type'        => 'payment',
                                'amount'      => $walletUsed,
                                'reference'   => $record->order_code,
                                'notes'       => 'Wallet payment for order #' . $record->order_code,
                                'actioned_by' => $staffName,
                            ]);
                        }

                        $record->order_status  = 'Paid';
                        $record->points_earned = $pointsEarned;
                        $record->updated_by    = $staffName;
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'payment_confirmed', $record->order_code);
                    }),

                Action::make('markPreparing')
                    ->label('Mark Preparing')
                    ->icon('heroicon-o-fire')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => $record->order_status === 'Paid' && ! $record->collected)
                    ->requiresConfirmation()
                    ->modalHeading('Start Preparing')
                    ->modalDescription('Mark this order as being prepared?')
                    ->action(function (Order $record): void {
                        $record->order_status = 'Preparing';
                        $record->updated_by   = auth()->user()->getFilamentName();
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'order_preparing', $record->order_code);
                    }),

                Action::make('markReady')
                    ->label('Mark Ready')
                    ->icon('heroicon-o-bell')
                    ->color('success')
                    ->visible(fn (Order $record): bool =>
                        $record->order_status === 'Preparing' &&
                        ! $record->collected &&
                        $record->payment_method !== 'Points'
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Order Ready')
                    ->modalDescription('Mark this order as ready for collection?')
                    ->action(function (Order $record): void {
                        $record->order_status = 'Ready';
                        $record->updated_by   = auth()->user()->getFilamentName();
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'order_ready', $record->order_code);
                    }),

                Action::make('markCollected')
                    ->label('Mark Collected')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('primary')
                    ->visible(fn (Order $record): bool =>
                        in_array($record->order_status, ['Paid', 'Preparing', 'Ready']) &&
                        ! $record->collected
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Collection')
                    ->modalDescription('Mark this order as collected by the customer?')
                    ->action(function (Order $record): void {
                        $record->collected    = true;
                        $record->order_status = 'Collected';
                        $record->updated_by   = auth()->user()->getFilamentName();
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'order_collected', $record->order_code);
                    }),

                Action::make('cancel')
                    ->label('Cancel Order')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => ! in_array($record->order_status, ['Cancelled']) && ! $record->collected)
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Order')
                    ->modalDescription('Are you sure you want to cancel this order? Any points changes from this order will be reversed.')
                    ->action(function (Order $record): void {
                        $staffName   = auth()->user()->getFilamentName();
                        // Reverse points only if the order was already paid
                        $alreadyPaid = in_array($record->order_status, ['Paid', 'Preparing', 'Ready']);

                        if ($alreadyPaid) {
                            $reward = Reward::firstOrCreate(
                                ['user_id' => $record->user_id],
                                ['points'  => 0]
                            );

                            if ($record->payment_method === 'Points') {
                                // Refund the points that were spent
                                $reward->points += (int) $record->points_used;
                            } else {
                                // Reverse the points that were earned
                                $reward->points = max(0, $reward->points - (int) $record->points_earned);
                            }

                            $reward->save();

                            // Refund wallet amount if it was used
                            $walletUsed = (float) $record->wallet_amount_used;
                            if ($walletUsed > 0 && $record->user_id) {
                                $record->user->increment('wallet_balance', $walletUsed);
                                WalletTransaction::create([
                                    'user_id'     => $record->user_id,
                                    'type'        => 'refund',
                                    'amount'      => $walletUsed,
                                    'reference'   => $record->order_code,
                                    'notes'       => 'Refund for cancelled order #' . $record->order_code,
                                    'actioned_by' => $staffName,
                                ]);
                            }
                        }

                        $record->order_status  = 'Cancelled';
                        $record->points_earned = 0;
                        $record->updated_by    = $staffName;
                        $record->save();

                        PushNotificationService::sendLocalized($record->user_id, 'order_cancelled', $record->order_code);
                    }),
                ])->icon('heroicon-m-ellipsis-vertical')->tooltip('Actions'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'branch']);
        return app(\App\Services\BranchContext::class)->applyTo($query);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
