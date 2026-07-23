@extends('demo::layout')

@section('content')
    <h1>Your password has expired</h1>

    <p>Choose a new password to continue. You are held on this screen until you do
       — but you can still sign out.</p>

    @if ($errors->any())
        <ul class="error">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('password.rotate') }}">
        @csrf
        <label>Current password
            <input type="password" name="current_password" autofocus>
        </label>
        <label>New password
            <input type="password" name="password">
        </label>
        <label>Confirm new password
            <input type="password" name="password_confirmation">
        </label>
        <button type="submit">Update password</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="link">Sign out instead</button>
    </form>
@endsection
