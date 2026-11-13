<?php

namespace Modules\Operations\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\FinanceCategory;
use Modules\Operations\Models\FinanceTransaction;
use Modules\Operations\Models\StaffMember;
use Modules\Operations\Support\OpsContext;
use Spatie\Permission\Models\Role;

class StaffController extends OpsController
{
    public function index(Request $request): View
    {
        $property = $this->property($request, 'manager');

        $staff = StaffMember::with('user')
            ->where('property_id', $property->id)
            ->when($request->input('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->input('status', 'active')))
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('department')->orderBy('name')
            ->get();

        return view('operations::staff.index', [
            'property' => $property,
            'staff' => $staff,
            'monthlyPayroll' => (int) $staff->where('status', 'active')->sum('salary_amount'),
        ]);
    }

    public function create(Request $request): View
    {
        $property = $this->property($request, 'manager');

        return view('operations::staff.form', [
            'property' => $property,
            'member' => new StaffMember(['access_level' => 'basic', 'employment_type' => 'full_time', 'status' => 'active']),
            'accessLevels' => $this->grantableLevels($request, $property),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $property = $this->property($request, 'manager');
        $data = $request->validate($this->rules($request, $property) + [
            'create_login' => ['nullable', 'boolean'],
            'login_email' => ['nullable', 'required_if:create_login,1', 'email', 'max:190'],
            'login_password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $member = DB::transaction(function () use ($request, $property, $data) {
            $member = new StaffMember($this->attributes($data) + [
                'property_id' => $property->id,
                'currency' => OpsContext::currency(),
            ]);

            if ($request->boolean('create_login')) {
                $member->user_id = $this->loginFor($property, $data, $member)->id;
            }

            $member->save();

            return $member;
        });

        return redirect()->route('ops.staff.show', $member)->with('status', "{$member->name} registered.");
    }

    public function show(Request $request, StaffMember $staff): View
    {
        $property = $this->propertyOf($request, $staff, 'manager');

        $recent = $staff->shifts()->where('starts_at', '>=', now()->subDays(30))->where('starts_at', '<=', now())->get();

        return view('operations::staff.show', [
            'property' => $property,
            'member' => $staff->load('user'),
            'upcoming' => $staff->shifts()->where('ends_at', '>=', now())->where('status', '!=', 'cancelled')->orderBy('starts_at')->limit(10)->get(),
            'workedMinutes' => $recent->sum(fn ($s) => $s->workedMinutes()),
            'shiftsWorked' => $recent->whereNotNull('clock_in_at')->count(),
            'shiftsMissed' => $recent->where('status', 'missed')->count(),
            'payments' => $staff->payments()->latest('occurred_on')->limit(12)->get(),
        ]);
    }

    public function edit(Request $request, StaffMember $staff): View
    {
        $property = $this->propertyOf($request, $staff, 'manager');
        $this->guardManagerRecord($request, $property, $staff);

        return view('operations::staff.form', [
            'property' => $property,
            'member' => $staff,
            'accessLevels' => $this->grantableLevels($request, $property),
        ]);
    }

    public function update(Request $request, StaffMember $staff): RedirectResponse
    {
        $property = $this->propertyOf($request, $staff, 'manager');
        $this->guardManagerRecord($request, $property, $staff);

        $data = $request->validate($this->rules($request, $property) + [
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $staff->update($this->attributes($data) + ['status' => $data['status']]);

        return redirect()->route('ops.staff.show', $staff)->with('status', 'Staff record updated.');
    }

    /** Record a salary/wage payment as an expense in the books. */
    public function pay(Request $request, StaffMember $staff): RedirectResponse
    {
        $property = $this->propertyOf($request, $staff, 'manager');
        $data = $request->validate([
            'amount' => ['required', self::MONEY],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(array_keys(FinanceTransaction::PAYMENT_METHODS))],
            'period' => ['nullable', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        FinanceTransaction::create([
            'property_id' => $property->id,
            'category_id' => FinanceCategory::bySlug('salaries')->id,
            'type' => 'expense',
            'amount' => $this->toMinor($data['amount']),
            'currency' => OpsContext::currency(),
            'occurred_on' => $data['occurred_on'],
            'payment_method' => $data['payment_method'],
            'counterparty' => $staff->name,
            'reference' => $data['reference'] ?? null,
            'description' => trim('Salary '.($data['period'] ?? '')),
            'staff_member_id' => $staff->id,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('ops.staff.show', $staff)->with('status', 'Salary payment recorded in the books.');
    }

    private function rules(Request $request, Property $property): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'position' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
            'access_level' => ['required', Rule::in(array_keys($this->grantableLevels($request, $property)))],
            'employment_type' => ['required', Rule::in(array_keys(StaffMember::EMPLOYMENT_TYPES))],
            'hired_on' => ['nullable', 'date'],
            'salary' => ['nullable', self::MONEY],
            'emergency_contact' => ['nullable', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'position' => $data['position'],
            'department' => $data['department'] ?? null,
            'access_level' => $data['access_level'],
            'employment_type' => $data['employment_type'],
            'hired_on' => $data['hired_on'] ?? null,
            'salary_amount' => $this->toMinor($data['salary'] ?? null),
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Only the owner (admin/host) can hand out manager access; managers can add basic and store-keeper staff. */
    private function grantableLevels(Request $request, Property $property): array
    {
        $levels = StaffMember::ACCESS_LEVELS;

        if ($this->level($request, $property) !== 'owner') {
            unset($levels['manager']);
        }

        return $levels;
    }

    private function guardManagerRecord(Request $request, Property $property, StaffMember $staff): void
    {
        abort_if($staff->access_level === 'manager' && $this->level($request, $property) !== 'owner', 403, 'Only the property owner can change a manager.');
    }

    /** Link an existing account by email, or create a new login with the Staff role. */
    private function loginFor(Property $property, array $data, StaffMember $member): User
    {
        Role::findOrCreate('Staff', 'web');
        $email = strtolower($data['login_email']);
        $user = User::where('email', $email)->first();

        if ($user) {
            if (StaffMember::where('property_id', $property->id)->where('user_id', $user->id)->exists()) {
                $this->fail('login_email', 'That account is already registered as staff at this property.');
            }
            if ($user->hasRole('Admin')) {
                $this->fail('login_email', 'An admin account cannot be linked as staff.');
            }
        } else {
            if (empty($data['login_password'])) {
                $this->fail('login_password', 'Set a password for the new login.');
            }
            $user = User::create([
                'name' => $member->name,
                'email' => $email,
                'phone' => $member->phone,
                'password' => $data['login_password'],
            ]);
        }

        if (! $user->hasRole('Staff')) {
            $user->assignRole('Staff');
        }

        return $user;
    }
}
