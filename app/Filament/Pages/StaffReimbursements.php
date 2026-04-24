<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\ReimbursementPayment;
use App\Services\BranchContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class StaffReimbursements extends Page
{
    protected string $view = 'filament.pages.staff-reimbursements';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Staff Reimbursements';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    public function getTitle(): string
    {
        $branch = app(BranchContext::class)->getBranch();
        return $branch ? 'Staff Reimbursements — ' . $branch->name : 'Staff Reimbursements';
    }

    // Per-staff reimbursement form state: keyed by staff_name
    public array $paymentMethod = [];
    public array $paymentDate   = [];
    public array $paymentNotes  = [];
    public array $showForm      = [];

    public function mount(): void
    {
        foreach ($this->getOutstandingByStaff() as $staff => $data) {
            $this->paymentMethod[$staff] = 'Cash';
            $this->paymentDate[$staff]   = now('Pacific/Tarawa')->toDateString();
            $this->paymentNotes[$staff]  = '';
            $this->showForm[$staff]      = false;
        }
    }

    private function branchId(): ?int
    {
        return app(BranchContext::class)->getId();
    }

    public function getOutstandingByStaff(): array
    {
        $query = Expense::where('paid_from', 'own_money')
            ->where('reimbursement_status', 'unpaid')
            ->orderBy('expense_date');

        $branchId = $this->branchId();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->get()
            ->groupBy(fn ($e) => $e->purchased_by ?: $e->created_by)
            ->map(fn ($expenses) => [
                'expenses' => $expenses->toArray(),
                'total'    => (float) $expenses->sum('amount'),
            ])
            ->toArray();
    }

    public function getReimbursementHistory(): array
    {
        $query = ReimbursementPayment::orderByDesc('payment_date')->orderByDesc('created_at');

        $branchId = $this->branchId();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->limit(20)->get()->toArray();
    }

    public function toggleForm(string $staff): void
    {
        $this->showForm[$staff] = ! ($this->showForm[$staff] ?? false);
    }

    public function reimburse(string $staff): void
    {
        $branchId  = $this->branchId();
        $method    = $this->paymentMethod[$staff] ?? 'Cash';
        $date      = $this->paymentDate[$staff]   ?? now('Pacific/Tarawa')->toDateString();
        $notes     = $this->paymentNotes[$staff]  ?? null;

        $expenseQuery = Expense::where('paid_from', 'own_money')
            ->where('reimbursement_status', 'unpaid')
            ->where(function ($q) use ($staff) {
                $q->where('purchased_by', $staff)->orWhere(function ($q2) use ($staff) {
                    $q2->whereNull('purchased_by')->orWhere('purchased_by', '')->where('created_by', $staff);
                });
            });

        if ($branchId) {
            $expenseQuery->where('branch_id', $branchId);
        }

        $expenses = $expenseQuery->get();

        if ($expenses->isEmpty()) {
            Notification::make()->title('No outstanding expenses for this staff member.')->warning()->send();
            return;
        }

        $total = (float) $expenses->sum('amount');

        $payment = ReimbursementPayment::create([
            'staff_name'     => $staff,
            'amount'         => $total,
            'payment_method' => $method,
            'payment_date'   => $date,
            'branch_id'      => $branchId,
            'notes'          => $notes ?: null,
            'created_by'     => auth()->user()->getFilamentName(),
        ]);

        $expenses->each(fn ($e) => $e->update([
            'reimbursement_status'     => 'reimbursed',
            'reimbursement_payment_id' => $payment->id,
        ]));

        $this->showForm[$staff]     = false;
        $this->paymentNotes[$staff] = '';

        Notification::make()
            ->title("A\${$total} reimbursed to {$staff} via {$method}")
            ->success()
            ->send();
    }
}
