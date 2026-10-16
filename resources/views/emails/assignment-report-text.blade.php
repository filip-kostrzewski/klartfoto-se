Din fotorapport är klar

Hej {!! $customerName !!}! {!! $companyName !!} har sammanställt rapporten från besöket på {!! $address !!} den {!! $performedOn !!}.

@if (filled($customerMessage))
Meddelande från {!! $companyName !!}:
{!! $customerMessage !!}

@endif
Öppna fotorapporten: {!! $reportUrl !!}

Vänliga hälsningar,
{!! $companyName !!}

Du får det här mejlet eftersom {!! $companyName !!} har skickat en rapport från sitt arbete.
