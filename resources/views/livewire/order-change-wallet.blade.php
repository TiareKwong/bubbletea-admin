<div>
    {{-- Change Added to Wallet table --}}
    @if($transactions->isNotEmpty())
    <table style="width:100%; border-collapse: collapse; font-size: 0.9rem;">
        <thead>
            <tr style="border-bottom: 1px solid #e5e7eb; text-align: left;">
                <th style="padding: 8px 12px; color: #6b7280;">Cash Received</th>
                <th style="padding: 8px 12px; color: #6b7280;">Change Added</th>
                <th style="padding: 8px 12px; color: #6b7280;">Actioned By</th>
                <th style="padding: 8px 12px; color: #6b7280;">Date & Time</th>
                <th style="padding: 8px 12px; color: #6b7280;">Status</th>
                <th style="padding: 8px 12px; color: #6b7280;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $t)
            @php
                preg_match('/cash received: A\$([\d.]+)/i', $t->notes ?? '', $matches);
                $cashReceived = isset($matches[1]) ? 'A$' . number_format((float) $matches[1], 2) : '—';
                $removed = $t->isRemoved();
            @endphp
            <tr style="border-bottom: 1px solid #f3f4f6; {{ $removed ? 'opacity: 0.6;' : '' }}">
                <td style="padding: 8px 12px; {{ $removed ? 'text-decoration: line-through;' : '' }}">{{ $cashReceived }}</td>
                <td style="padding: 8px 12px; color: {{ $removed ? '#9ca3af' : '#16a34a' }}; font-weight: 600; {{ $removed ? 'text-decoration: line-through;' : '' }}">
                    +A${{ number_format($t->amount, 2) }}
                </td>
                <td style="padding: 8px 12px;">{{ $t->actioned_by ?? '—' }}</td>
                <td style="padding: 8px 12px;">{{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $t->created_at, 'UTC')->setTimezone('Pacific/Tarawa')->format('d M Y, h:i A') }}</td>
                <td style="padding: 8px 12px;">
                    @if($removed)
                        <span style="display:inline-block;padding:2px 8px;background:#fee2e2;color:#dc2626;border-radius:999px;font-size:0.78rem;font-weight:600;">
                            Removed
                        </span>
                        <div style="margin-top:4px;font-size:0.78rem;color:#6b7280;">
                            By {{ $t->removed_by }} · {{ \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $t->removed_at, 'UTC')->setTimezone('Pacific/Tarawa')->format('d M Y, h:i A') }}
                        </div>
                        <div style="font-size:0.78rem;color:#6b7280;font-style:italic;">Reason: {{ $t->removal_reason }}</div>
                    @elseif($hasRemoved)
                        <span style="display:inline-block;padding:2px 8px;background:#fef9c3;color:#92400e;border-radius:999px;font-size:0.78rem;font-weight:600;">
                            Locked
                        </span>
                    @else
                        <span style="display:inline-block;padding:2px 8px;background:#dcfce7;color:#16a34a;border-radius:999px;font-size:0.78rem;font-weight:600;">
                            Active
                        </span>
                    @endif
                </td>
                <td style="padding: 8px 12px;">
                    @if(! $removed && ! $hasRemoved)
                    <button type="button" wire:click="openEditModal"
                        style="padding:4px 10px;margin-right:6px;background:#f59e0b;color:#fff;border:none;border-radius:6px;font-size:0.8rem;cursor:pointer;">
                        Edit
                    </button>
                    <button type="button" wire:click="openRemoveModal"
                        style="padding:4px 10px;background:#ef4444;color:#fff;border:none;border-radius:6px;font-size:0.8rem;cursor:pointer;">
                        Remove
                    </button>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Edit Modal --}}
    @if($showEditModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h2 style="margin:0 0 4px;font-size:1.1rem;font-weight:700;">Edit Change to Wallet</h2>
            <p style="margin:0 0 16px;font-size:0.85rem;color:#6b7280;">Update the cash received, then click Calculate.</p>

            <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:4px;">Cash Received ($)</label>
            <p style="font-size:0.8rem;color:#6b7280;margin:0 0 6px;">Must be more than A${{ number_format($amountDue, 2) }} (amount due)</p>
            <input type="number" wire:model="cashReceived" step="0.01" min="{{ $amountDue + 0.01 }}"
                style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:0.9rem;box-sizing:border-box;margin-bottom:10px;"
                onkeydown="if(event.key==='Enter'){event.preventDefault();}"/>

            @if($errorMessage)
            <p style="color:#dc2626;font-size:0.82rem;margin:0 0 10px;">{{ $errorMessage }}</p>
            @endif

            <button type="button" wire:click="calculateChange"
                style="width:100%;padding:8px;background:#0ea5e9;color:#fff;border:none;border-radius:6px;font-size:0.88rem;font-weight:600;cursor:pointer;margin-bottom:10px;">
                Calculate Change
            </button>

            @if($changeToWallet !== '')
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px;margin-bottom:14px;font-size:0.88rem;color:#166534;">
                Change to add to wallet: <strong>A${{ $changeToWallet }}</strong>
            </div>
            @endif

            <div style="display:flex;gap:8px;">
                <button type="button" wire:click="saveEdit"
                    @if(blank($changeToWallet)) disabled style="flex:1;padding:8px;background:#e5e7eb;color:#9ca3af;border:none;border-radius:6px;font-size:0.88rem;font-weight:600;cursor:not-allowed;"
                    @else style="flex:1;padding:8px;background:#16a34a;color:#fff;border:none;border-radius:6px;font-size:0.88rem;font-weight:600;cursor:pointer;"
                    @endif>Save</button>
                <button type="button" wire:click="$set('showEditModal', false)"
                    style="flex:1;padding:8px;background:#f3f4f6;color:#374151;border:none;border-radius:6px;font-size:0.88rem;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Remove Modal (asks for reason) --}}
    @if($showRemoveModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h2 style="margin:0 0 4px;font-size:1.1rem;font-weight:700;">Remove Change to Wallet</h2>
            <p style="margin:0 0 16px;font-size:0.85rem;color:#374151;">
                This will reverse the wallet balance. The transaction record will be kept for audit. Please provide a reason.
            </p>

            <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:4px;">Reason for Removal <span style="color:#dc2626;">*</span></label>
            <textarea wire:model="removalReason" rows="3"
                placeholder="e.g. Entered incorrectly, customer returned cash..."
                style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:0.88rem;box-sizing:border-box;resize:vertical;margin-bottom:8px;"></textarea>

            @if($removalError)
            <p style="color:#dc2626;font-size:0.82rem;margin:0 0 10px;">{{ $removalError }}</p>
            @endif

            <div style="display:flex;gap:8px;margin-top:4px;">
                <button type="button" wire:click="confirmRemove"
                    style="flex:1;padding:8px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-size:0.88rem;font-weight:600;cursor:pointer;">
                    Confirm Remove
                </button>
                <button type="button" wire:click="$set('showRemoveModal', false)"
                    style="flex:1;padding:8px;background:#f3f4f6;color:#374151;border:none;border-radius:6px;font-size:0.88rem;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
