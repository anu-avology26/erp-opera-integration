<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    /**
     * Edit profile
     * @return View
     */
    public function edit(): View
    {
        $admin = Auth::guard('admin')->user();
        return view('admin.profile.edit', ['admin' => $admin]);
    }
    /**
     * Update profile
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
        ];

        if ($request->filled('password')) {
            $rules['current_password'] = ['required', 'string'];
            $rules['password'] = ['required', 'confirmed', Password::defaults()];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['password'] ?? null)) {
            if (! Hash::check($validated['current_password'], $admin->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.']);
            }
        }

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        if (! empty($validated['password'] ?? null)) {
            $admin->password = $validated['password'];
        }
        $admin->save();

        return back()->with('status', 'Profile updated.');
    }
}
