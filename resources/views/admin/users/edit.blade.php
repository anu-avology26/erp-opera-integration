@extends('admin.layout')

@section('title', 'Edit Admin User')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Edit Admin User</h1>
        <p class="page-subtitle">Update name, email, or reset password.</p>
    </div>

    @if ($errors->any())
        <div class="errors-pre" style="margin-bottom: 1rem;">{{ implode("\n", $errors->all()) }}</div>
    @endif

    <section class="card">
        <h2 class="card-title">Edit User</h2>
        <form method="post" action="{{ route('admin.users.update', $admin) }}">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $admin->name) }}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $admin->email) }}" required>
            </div>
            <div class="form-group">
                <label for="password">New Password (optional)</label>
                <input type="password" id="password" name="password" class="form-input">
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input">
            </div>
            <button type="submit" class="btn btn-success">Save Changes</button>
            <a href="{{ route('admin.users.index') }}" class="btn">Cancel</a>
        </form>
    </section>
@endsection
