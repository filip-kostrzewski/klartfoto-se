<h1>Nytt bekräftat konto på Klartfoto</h1>

@if (filled($companyName))
    <p><strong>Företag:</strong> {{ $companyName }}</p>
@endif
<p><strong>Namn:</strong> {{ $name }}</p>
<p><strong>E-post:</strong> {{ $email }}</p>
<p><strong>Registrerad:</strong> {{ $registeredAt }}</p>
@if (filled($plan))
    <p><strong>Plan:</strong> {{ $plan }}</p>
@endif
