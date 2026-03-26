<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $admins = Admin::query()->orderByDesc('created_at')->paginate(20);

        return view('admin.users.index', ['admins' => $admins]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        return redirect()->route('admin.users.index')->with('message', 'Admin user created successfully.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.users.edit', ['admin' => $admin]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
            'password' => ['nullable', 'confirmed', PasswordRule::defaults()],
        ]);

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        if (! empty($validated['password'])) {
            $admin->password = $validated['password'];
        }
        $admin->save();

        return redirect()->route('admin.users.index')->with('message', 'Admin user updated successfully.');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        $currentAdminId = Auth::guard('admin')->id();
        if ($currentAdminId && (int) $currentAdminId === (int) $admin->id) {
            return back()->with('message', 'You cannot delete the currently logged-in admin.');
        }

        $admin->delete();

        return redirect()->route('admin.users.index')->with('message', 'Admin user deleted successfully.');
    }

    public function sendReset(Admin $admin): RedirectResponse
    {
        $status = Password::broker('admins')->sendResetLink([
            'email' => $admin->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('message', 'Password reset link sent to ' . $admin->email . '.');
        }

        return back()->with('message', 'Unable to send reset link: ' . __($status));
    }
}
