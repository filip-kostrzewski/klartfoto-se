<x-mail::message>
# Provperioden har löpt ut

Hej!

Provperioden för {{ $company->name }} i Klartfoto är slut. Dina rapporter finns kvar och kan öppnas, men nya uppdrag är pausade tills en plan är aktiv.

Begär en faktura från abonnemangssidan, så aktiverar vi planen så snart betalningen är registrerad. Ingen bindningstid, månadsvis eller årsvis fakturering.

<x-mail::button :url="$billingUrl">
Aktivera plan
</x-mail::button>

Svara gärna på det här mejlet om något i Klartfoto saknas för dig. Vi läser varje svar.

Hälsningar<br>
Klartfoto
</x-mail::message>
