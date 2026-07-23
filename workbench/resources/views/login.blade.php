@extends('demo::layout')

@section('content')
    <h1>Sign in</h1>

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" autofocus>
        </label>
        <label>Password
            <input type="password" name="password">
        </label>
        <button type="submit">Sign in</button>
    </form>

    <p class="hint">
        Seeded logins (password <code>password</code>):<br>
        <code>expired@example.com</code> — past the rotation window<br>
        <code>fresh@example.com</code> — still valid
    </p>
@endsection
