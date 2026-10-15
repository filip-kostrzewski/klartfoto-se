@extends('layouts.app')

@section('title', 'Inställningar · Klartfoto')

@section('content')
    <section class="app-card">
        <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $company->name }}</p>
        <h1>Inställningar</h1>

        @if (session('status'))
            <div class="form-status form-status-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="settings-block" id="abonnemang">
            <h2>Abonnemang</h2>
            @include('settings.partials.subscription')
        </section>

        <section class="settings-block" id="logotyp">
            <h2>Logotyp</h2>
            <p class="auth-lead">Logotypen visas överst i kundens rapport och i exempelrapporten. PNG, JPEG eller SVG. Vi förminskar bilden och tar bort sådant som inte ska synas.</p>
            @if ($company->allowsTeamFeatures() && $company->logo_path)
                <img class="settings-logo" src="{{ route('settings.logo', absolute: false) }}" alt="{{ $company->name }}">
            @endif
            @if ($isOwner && $company->allowsTeamFeatures())
                <form class="app-form" method="post" action="{{ route('settings.logo.store', absolute: false) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="logo">Välj fil</label>
                    <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,.svg,image/svg+xml" required>
                    <button class="button button-lime" type="submit">Ladda upp logotyp</button>
                </form>
                @if ($company->logo_path)
                    <form method="post" action="{{ route('settings.logo.destroy', absolute: false) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button button-outline-dark" type="submit">Ta bort logotyp</button>
                    </form>
                @endif
            @elseif (! $company->allowsTeamFeatures())
                <p class="form-status form-status-error" role="status">Provperioden är slut. Egen logotyp ingår i Team.</p>
            @endif
        </section>

        <section class="settings-block" id="medlemmar">
            <h2>Medlemmar</h2>
            <p class="auth-lead">{{ $userCount }} av {{ $seatLimit }} användare{{ $pendingInvites === 1 ? ', 1 inbjudan väntar' : ($pendingInvites > 1 ? ', '.$pendingInvites.' inbjudningar väntar' : '') }}. @if ($company->onTrial())Under provperioden kan ni vara upp till 10.@else Start inkluderar 1 användare. Team inkluderar upp till 10.@endif</p>
            <ul class="member-list">
                @foreach ($company->users as $member)
                    <li>
                        <strong>{{ $member->name }}</strong>
                        <span>{{ $member->email }}</span>
                        <span>{{ $member->isOwner() ? 'Ägare' : 'Medlem' }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($company->invitations->isNotEmpty())
                <h3>Väntande inbjudningar</h3>
                <ul class="member-list">
                    @foreach ($company->invitations as $invitation)
                        <li>
                            <strong>{{ $invitation->email }}</strong>
                            <span>Gäller till {{ $invitation->expires_at->locale('sv')->translatedFormat('j F Y') }}</span>
                            @if ($isOwner)
                                <form method="post" action="{{ route('invitations.destroy', $invitation, false) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Ta bort</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($isOwner && $company->allowsTeamFeatures())
                <form class="app-form" method="post" action="{{ route('invitations.store', absolute: false) }}">
                    @csrf
                    <label for="email">Bjud in med e-post</label>
                    <input id="email" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" placeholder="namn@foretag.se">
                    <button class="button button-lime" type="submit">Skicka inbjudan</button>
                </form>
            @elseif ($isOwner)
                <p class="form-status form-status-error" role="status">Provperioden är slut. Fler användare ingår i Team.</p>
            @else
                <p class="field-hint">Bara ägaren kan bjuda in nya användare.</p>
            @endif
        </section>
    </section>
@endsection
