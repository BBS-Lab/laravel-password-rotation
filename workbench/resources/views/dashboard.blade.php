@extends('demo::layout')

@section('content')
    <h1>Dashboard</h1>

    @if (session('status'))
        <p class="ok">{{ session('status') }}</p>
    @endif

    <p>Signed in as <strong>{{ $user->email }}</strong>.</p>

    @if ($user->passwordIsExpiring())
        <p class="warn">
            Heads up — your password expires {{ $user->passwordExpiresAt()?->diffForHumans() }}.
        </p>
    @else
        <p>Your password is valid until {{ $user->passwordExpiresAt()?->toFormattedDayDateString() ?? 'further notice' }}.</p>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
@endsection
