Nytt bekräftat konto på Klartfoto

@if (filled($companyName))
Företag: {!! $companyName !!}
@endif
Namn: {!! $name !!}
E-post: {!! $email !!}
Registrerad: {!! $registeredAt !!}
@if (filled($plan))
Plan: {!! $plan !!}
@endif
