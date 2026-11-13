<?php

namespace Modules\Operations\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\Shift;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Support\OpsAccess;

class ShiftController extends OpsController
{
    public const AREAS = ['Front desk', 'Housekeeping', 'Kitchen', 'Laundry', 'Store', 'Security', 'Maintenance', 'Night duty'];

    public function index(Request $request): View
    {
        $property = $this->property($request);
        self::markMissed($property);

        $weekStart = ($request->filled('week') ? CarbonImmutable::parse($request->input('week')) : CarbonImmutable::today())->startOfWeek();
        $weekEnd = $weekStart->endOfWeek();

        $shifts = Shift::with('staffMember')
            ->where('property_id', $property->id)
            ->where('starts_at', '<=', $weekEnd)
            ->where('ends_at', '>=', $weekStart)
            ->orderBy('starts_at')
            ->get();

        $staff = StaffMember::where('property_id', $property->id)
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $shifts->pluck('staff_member_id')))
            ->orderBy('department')->orderBy('name')
            ->get();

        return view('operations::shifts.index', [
            'property' => $property,
            'weekStart' => $weekStart,
            'days' => collect(range(0, 6))->map(fn ($i) => $weekStart->addDays($i)),
            'staff' => $staff,
            'grid' => $shifts->groupBy(fn (Shift $s) => $s->staff_member_id.'|'.$s->starts_at->toDateString()),
            'me' => OpsAccess::staffMember($request->user(), $property),
            'canManage' => $this->can($request, $property, 'manager'),
        ]);
    }

    public function create(Request $request): View
    {
        $property = $this->property($request, 'manager');

        return view('operations::shifts.form', [
            'property' => $property,
            'shift' => new Shift([
                'staff_member_id' => $request->integer('staff') ?: null,
                'starts_at' => CarbonImmutable::parse($request->input('date', 'today'))->setTime(8, 0),
                'ends_at' => CarbonImmutable::parse($request->input('date', 'today'))->setTime(16, 0),
            ]),
            'staff' => $this->activeStaff($property),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $data = $request->validate($this->rules($property) + [
            'repeat_days' => ['nullable', 'integer', 'min:0', 'max:27'],
            'skip_weekends' => ['nullable', 'boolean'],
        ]);

        [$start, $end] = $this->window($data);
        $created = 0;

        for ($i = 0; $i <= (int) ($data['repeat_days'] ?? 0); $i++) {
            $s = $start->addDays($i);
            if ($i > 0 && $request->boolean('skip_weekends') && $s->isWeekend()) {
                continue;
            }

            Shift::create([
                'property_id' => $property->id,
                'staff_member_id' => $data['staff_member_id'],
                'starts_at' => $s,
                'ends_at' => $end->addDays($i),
                'area' => $data['area'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'created_by' => $request->user()->id,
            ]);
            $created++;
        }

        return redirect()->route('ops.shifts.index', ['week' => $start->toDateString()])
            ->with('status', $created === 1 ? 'Shift scheduled.' : "{$created} shifts scheduled.");
    }

    public function edit(Request $request, Shift $shift): View
    {
        $property = $this->propertyOf($request, $shift, 'manager');

        return view('operations::shifts.form', [
            'property' => $property,
            'shift' => $shift,
            'staff' => $this->activeStaff($property, $shift->staff_member_id),
        ]);
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $property = $this->propertyOf($request, $shift, 'manager');
        $data = $request->validate($this->rules($property) + [
            'status' => ['required', Rule::in(Shift::STATUSES)],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at'],
            'handover_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$start, $end] = $this->window($data);

        $shift->update([
            'staff_member_id' => $data['staff_member_id'],
            'starts_at' => $start,
            'ends_at' => $end,
            'area' => $data['area'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
            'clock_in_at' => $data['clock_in_at'] ?? null,
            'clock_out_at' => $data['clock_out_at'] ?? null,
            'handover_notes' => $data['handover_notes'] ?? null,
        ]);

        return redirect()->route('ops.shifts.index', ['week' => $start->toDateString()])->with('status', 'Shift updated.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        $this->propertyOf($request, $shift, 'manager');
        $week = $shift->starts_at->toDateString();
        $shift->delete();

        return redirect()->route('ops.shifts.index', ['week' => $week])->with('status', 'Shift removed.');
    }

    public function clockIn(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizeClock($request, $shift);
        abort_unless($shift->canClockIn(), 422, 'This shift cannot be clocked into right now.');

        $shift->update(['clock_in_at' => now(), 'status' => 'in_progress']);

        return back()->with('status', 'Clocked in at '.now()->format('g:i A').'.');
    }

    public function clockOut(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorizeClock($request, $shift);
        abort_unless($shift->isOnDuty(), 422, 'You are not clocked in to this shift.');

        $data = $request->validate(['handover_notes' => ['nullable', 'string', 'max:2000']]);
        $shift->update(['clock_out_at' => now(), 'status' => 'completed', 'handover_notes' => $data['handover_notes'] ?? null]);

        return back()->with('status', 'Clocked out. Worked '.Shift::formatMinutes($shift->workedMinutes()).'.');
    }

    public function attendance(Request $request): View
    {
        $property = $this->property($request, 'manager');
        self::markMissed($property);

        $from = $request->filled('from') ? CarbonImmutable::parse($request->input('from'))->startOfDay() : CarbonImmutable::today()->startOfMonth();
        $to = $request->filled('to') ? CarbonImmutable::parse($request->input('to'))->endOfDay() : CarbonImmutable::today()->endOfDay();

        $shifts = Shift::with('staffMember')
            ->where('property_id', $property->id)
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        $rows = $shifts->groupBy('staff_member_id')->map(fn ($group) => [
            'member' => $group->first()->staffMember,
            'scheduled' => $group->count(),
            'worked' => $group->whereNotNull('clock_in_at')->count(),
            'missed' => $group->where('status', 'missed')->count(),
            'late' => $group->filter(fn (Shift $s) => $s->lateMinutes() > 10)->count(),
            'scheduled_minutes' => $group->sum(fn (Shift $s) => $s->scheduledMinutes()),
            'worked_minutes' => $group->sum(fn (Shift $s) => $s->workedMinutes()),
        ])->sortBy(fn ($r) => $r['member']->name)->values();

        return view('operations::shifts.attendance', [
            'property' => $property,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'handovers' => $shifts->whereNotNull('handover_notes')->sortByDesc('clock_out_at')->take(15),
        ]);
    }

    /** Scheduled shifts that ended without anyone clocking in. */
    public static function markMissed(Property $property): void
    {
        Shift::where('property_id', $property->id)
            ->where('status', 'scheduled')
            ->whereNull('clock_in_at')
            ->where('ends_at', '<', now())
            ->update(['status' => 'missed']);
    }

    private function authorizeClock(Request $request, Shift $shift): void
    {
        $property = $this->propertyOf($request, $shift);
        $isOwnShift = $shift->staffMember?->user_id === $request->user()->id;

        abort_unless($isOwnShift || $this->can($request, $property, 'manager'), 403);
    }

    private function rules(Property $property): array
    {
        return [
            'staff_member_id' => ['required', Rule::exists('ops_staff_members', 'id')->where('property_id', $property->id)],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'area' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** An end time at or before the start time means the shift runs overnight. */
    private function window(array $data): array
    {
        $start = CarbonImmutable::parse($data['date'].' '.$data['start_time']);
        $end = CarbonImmutable::parse($data['date'].' '.$data['end_time']);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    private function activeStaff(Property $property, ?int $include = null)
    {
        return StaffMember::where('property_id', $property->id)
            ->where(fn ($q) => $q->where('status', 'active')->when($include, fn ($q) => $q->orWhere('id', $include)))
            ->orderBy('name')
            ->get();
    }
}
