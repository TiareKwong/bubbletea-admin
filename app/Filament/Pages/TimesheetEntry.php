<?php

namespace App\Filament\Pages;

use App\Models\StaffDeduction;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class TimesheetEntry extends Page
{
    protected string $view = 'filament.pages.timesheet-entry';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Timesheets';

    protected static string|\UnitEnum|null $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->is_admin || $user?->is_super_staff);
    }

    /**
     * The pay week runs Friday through the following Thursday, and staff
     * are paid on that same Thursday. $weekStart is always snapped to a
     * Friday so navigation always lands on a real pay period.
     */
    public ?int $staffId = null;
    public string $weekStart = '';

    public array $rows = [];
    public array $pendingDeleteIds = [];

    public string $newDeductionDate   = '';
    public string $newDeductionAmount = '';
    public string $newDeductionReason = '';

    public function mount(): void
    {
        $this->weekStart = $this->snapToFriday(now('Pacific/Tarawa')->toDateString());
    }

    public function updatedStaffId(): void
    {
        $this->newDeductionAmount = '';
        $this->newDeductionReason = '';
        $this->loadRows();
    }

    public function updatedWeekStart(): void
    {
        $this->weekStart = $this->snapToFriday($this->weekStart);
        $this->loadRows();
    }

    public function prevWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subDays(7)->toDateString();
        $this->loadRows();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addDays(7)->toDateString();
        $this->loadRows();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = $this->snapToFriday(now('Pacific/Tarawa')->toDateString());
        $this->loadRows();
    }

    protected function snapToFriday(string $date): string
    {
        $d    = Carbon::parse($date)->startOfDay();
        $diff = ($d->dayOfWeek - Carbon::FRIDAY + 7) % 7;

        return $d->subDays($diff)->toDateString();
    }

    public function getWeekEndProperty(): string
    {
        return Carbon::parse($this->weekStart)->addDays(6)->toDateString();
    }

    public function getIsCurrentWeekProperty(): bool
    {
        return $this->weekStart === $this->snapToFriday(now('Pacific/Tarawa')->toDateString());
    }

    protected function loadRows(): void
    {
        $this->pendingDeleteIds = [];
        $this->newDeductionDate = $this->weekStart;

        if (! $this->staffId) {
            $this->rows = [];
            return;
        }

        $rows = Timesheet::where('user_id', $this->staffId)
            ->whereBetween('work_date', [$this->weekStart, $this->weekEnd])
            ->orderBy('work_date')
            ->orderBy('time_in')
            ->get()
            ->map(fn (Timesheet $t) => [
                'id'        => $t->id,
                'work_date' => $t->work_date->toDateString(),
                'time_in'   => $t->time_in ? Carbon::parse($t->time_in)->format('H:i') : '',
                'time_out'  => $t->time_out ? Carbon::parse($t->time_out)->format('H:i') : '',
                'notes'     => $t->notes ?? '',
            ])
            ->values()
            ->toArray();

        // Always show all 7 days of the pay week, even if nothing is
        // logged yet, so it's obvious what still needs filling in and
        // where the week ends.
        $present = collect($rows)->pluck('work_date')->unique();
        $cursor  = Carbon::parse($this->weekStart);

        for ($d = 0; $d < 7; $d++) {
            $date = $cursor->copy()->addDays($d)->toDateString();
            if (! $present->contains($date)) {
                $rows[] = ['id' => null, 'work_date' => $date, 'time_in' => '', 'time_out' => '', 'notes' => ''];
            }
        }

        usort($rows, fn ($a, $b) => [$a['work_date'], $a['id'] ?? 0] <=> [$b['work_date'], $b['id'] ?? 0]);

        $this->rows = array_values($rows);
    }

    public function addRow(): void
    {
        $last = end($this->rows);

        // Extra rows are almost always a second shift on a day that
        // already has one, so default to the same date rather than +1.
        $date = ($last && ! empty($last['work_date'])) ? $last['work_date'] : $this->weekStart;

        $this->rows[] = [
            'id'        => null,
            'work_date' => $date,
            'time_in'   => '',
            'time_out'  => '',
            'notes'     => '',
        ];
    }

    public function removeRow(int $index): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        if (! empty($this->rows[$index]['id'])) {
            $this->pendingDeleteIds[] = $this->rows[$index]['id'];
        }

        array_splice($this->rows, $index, 1);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        if (! $this->staffId) {
            Notification::make()->title('Please select a staff member.')->warning()->send();
            return;
        }

        DB::transaction(function () {
            foreach ($this->rows as $row) {
                if (empty($row['work_date']) || empty($row['time_in']) || empty($row['time_out'])) {
                    continue;
                }

                $data = [
                    'user_id'      => $this->staffId,
                    'work_date'    => $row['work_date'],
                    'time_in'      => $row['time_in'],
                    'time_out'     => $row['time_out'],
                    'hours_worked' => Timesheet::calculateHours($row['time_in'], $row['time_out']),
                    'notes'        => $row['notes'] ?: null,
                ];

                if (! empty($row['id'])) {
                    Timesheet::where('id', $row['id'])->update($data);
                } else {
                    $data['created_by'] = auth()->user()->getFilamentName();
                    Timesheet::create($data);
                }
            }

            if (! empty($this->pendingDeleteIds)) {
                Timesheet::whereIn('id', $this->pendingDeleteIds)->delete();
            }
        });

        $this->loadRows();

        Notification::make()->title('Timesheet saved.')->success()->send();
    }

    public function getDeductionsProperty(): \Illuminate\Support\Collection
    {
        if (! $this->staffId) {
            return collect();
        }

        return StaffDeduction::where('user_id', $this->staffId)
            ->whereBetween('date', [$this->weekStart, $this->weekEnd])
            ->orderBy('date')
            ->get();
    }

    public function addDeduction(): void
    {
        if (! $this->staffId) {
            Notification::make()->title('Please select a staff member.')->warning()->send();
            return;
        }

        $amount = (float) str_replace(',', '', $this->newDeductionAmount ?: '0');

        if ($amount <= 0) {
            Notification::make()->title('Enter a deduction amount greater than zero.')->warning()->send();
            return;
        }

        StaffDeduction::create([
            'user_id'    => $this->staffId,
            'date'       => $this->newDeductionDate ?: $this->weekStart,
            'amount'     => $amount,
            'reason'     => $this->newDeductionReason ?: null,
            'source'     => 'manual',
            'created_by' => auth()->user()->getFilamentName(),
        ]);

        $this->newDeductionDate   = '';
        $this->newDeductionAmount = '';
        $this->newDeductionReason = '';

        Notification::make()->title('Deduction added.')->success()->send();
    }

    public function removeDeduction(int $id): void
    {
        StaffDeduction::where('id', $id)->where('user_id', $this->staffId)->delete();
    }

    public function getStaffOptionsProperty(): array
    {
        return User::where('is_staff', true)
            ->where('is_admin', false)
            ->where('is_super_staff', false)
            ->where('email', '!=', 'guest@internal.local')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name])
            ->toArray();
    }

    public function getSelectedStaffProperty(): ?User
    {
        return $this->staffId ? User::find($this->staffId) : null;
    }

    /**
     * Rows missing a date (e.g. a freshly cleared field) are kept in a
     * leading "undated" bucket so they stay visible and editable instead
     * of silently disappearing from the grid. Everything else belongs to
     * the single pay week currently in view.
     */
    public function getUndatedRowsProperty(): array
    {
        return collect($this->rows)
            ->map(fn ($row, $index) => $row + ['_index' => $index])
            ->filter(fn ($row) => empty($row['work_date']))
            ->values()
            ->toArray();
    }

    public function getDatedRowsProperty(): array
    {
        return collect($this->rows)
            ->map(fn ($row, $index) => $row + ['_index' => $index])
            ->filter(fn ($row) => ! empty($row['work_date']))
            ->sortBy(fn ($row) => $row['work_date'] . '-' . $row['_index'])
            ->values()
            ->toArray();
    }
}
