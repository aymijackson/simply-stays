<?php

namespace Modules\Admin\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('role'), fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $request->input('role'))))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->pluck('name');

        return view('admin::users.index', compact('users', 'roles'));
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'exists:roles,name'],
        ]);

        $previousRole = $user->primaryRole();

        $user->syncRoles([$validated['role']]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->withProperties(['from' => $previousRole, 'to' => $validated['role']])
            ->log('role changed');

        return back()->with('status', "{$user->name}'s role updated to {$validated['role']}.");
    }

    public function updateStatus(User $user): RedirectResponse
    {
        abort_if($user->id === Auth::id(), 422, 'You cannot suspend your own account.');

        $user->update([
            'status' => $user->status === 'suspended' ? 'active' : 'suspended',
        ]);

        return back()->with('status', $user->status === 'suspended' ? "{$user->name} has been suspended." : "{$user->name} has been reactivated.");
    }
}
