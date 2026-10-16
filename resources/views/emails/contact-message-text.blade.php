New contact message for Klartfoto

Name: {!! $name !!}
Email: {!! $email !!}
@if (filled($company))
Company: {!! $company !!}
@endif

Message:
{!! $messageBody !!}
