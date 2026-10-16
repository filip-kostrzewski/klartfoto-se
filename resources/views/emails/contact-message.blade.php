<h1>New contact message for Klartfoto</h1>

<p><strong>Name:</strong> {{ $name }}</p>
<p><strong>Email:</strong> {{ $email }}</p>
@if (filled($company))
    <p><strong>Company:</strong> {{ $company }}</p>
@endif
<p><strong>Message:</strong></p>
<p>{!! nl2br(e($messageBody)) !!}</p>
