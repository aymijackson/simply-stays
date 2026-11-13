<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceOrder;
use Spatie\Permission\Models\Role;

/**
 * Vendors are users with the ServiceProvider role. Admins register them here
 * and hand over the login; vendors then manage their own services.
 */
class AdminVendorController extends Controller
{
    private const ROLE = 'ServiceProvider';

    public function index(Request $request): View
    {
        Role::findOrCreate(self::ROLE, 'web');

        $vendors = User::role(self::ROLE)
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $ids = $vendors->pluck('id');
        $services = Service::whereIn('provider_id', $ids)
            ->groupBy('provider_id')
            ->selectRaw("provider_id, COUNT(*) as total, SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as live")
            ->get()->keyBy('provider_id');
        $orders = ServiceOrder::join('services', 'services.id', '=', 'service_orders.service_id')
            ->whereIn('services.provider_id', $ids)
            ->groupBy('services.provider_id')
            ->selectRaw("services.provider_id as provider_id, COUNT(*) as total, SUM(CASE WHEN service_orders.status IN ('pending', 'confirmed', 'in_progress') THEN 1 ELSE 0 END) as open")
            ->get()->keyBy('provider_id');

        return view('admin::vendors.index', compact('vendors', 'services', 'orders'));
    }

    public function create(): View
    {
        return view('admin::vendors.form', ['vendor' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        Role::findOrCreate(self::ROLE, 'web');

        $vendor = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => 'active',
        ]);
        $vendor->assignRole(self::ROLE);

        activity()->causedBy(Auth::user())->performedOn($vendor)->log('vendor registered');

        return redirect()->route('admin.vendors.index')
            ->with('status', "{$vendor->name} is registered. Share their login ({$vendor->email}) and the password you set, then add their first service.")
            ->with('new_vendor_id', $vendor->id);
    }

    public function edit(User $vendor): View
    {
        $this->ensureVendor($vendor);

        return view('admin::vendors.form', compact('vendor'));
    }

    public function update(Request $request, User $vendor): RedirectResponse
    {
        $this->ensureVendor($vendor);

        $data = $request->validate($this->rules($vendor) + [
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        $vendor->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $vendor->password = $data['password'];
        }
        $vendor->save();

        return redirect()->route('admin.vendors.index')->with('status', empty($data['password'])
            ? "{$vendor->name} updated."
            : "{$vendor->name} updated, with a new password. Share it with them.");
    }

    private function rules(?User $vendor = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($vendor?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    private function ensureVendor(User $user): void
    {
        abort_unless($user->hasRole(self::ROLE), 404);
    }
}
