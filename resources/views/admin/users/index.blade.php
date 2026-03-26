@extends('admin.layout')

@section('title', 'Admin Users')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Admin Users</h1>
        <p class="page-subtitle">Create admin panel users and review existing accounts.</p>
    </div>

    @if ($errors->any())
        <div class="errors-pre" style="margin-bottom: 1rem;">{{ implode("\n", $errors->all()) }}</div>
    @endif

    <section class="card">
        <h2 class="card-title">Create New Admin User</h2>
        <form method="post" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-success">Create User</button>
        </form>
    </section>

    <section class="card">
        <h2 class="card-title">Existing Admin Users</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($admins as $admin)
                    <tr>
                        <td>{{ $admin->name }}</td>
                        <td>{{ $admin->email }}</td>
                        <td>{{ optional($admin->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td>
                            <div style="display:flex; gap: 0.5rem; flex-wrap: wrap;">
                                <a class="btn btn-small" href="{{ route('admin.users.edit', $admin) }}">Edit</a>
                                <form method="post" action="{{ route('admin.users.reset', $admin) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-small">Send reset</button>
                                </form>
                                <form method="post" action="{{ route('admin.users.destroy', $admin) }}" onsubmit="return confirm('Delete this admin user?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">No admin users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($admins->count() > 0)
            @include('admin.partials.pagination_v2', ['paginator' => $admins])
        @endif
    </section>
@endsection
