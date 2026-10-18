<x-mail::message>
# Provperioden slutar {{ $endsOn }}

Hej!

Du har använt Klartfoto gratis med allt som ingår i Team. Provperioden för {{ $company->name }} slutar {{ $endsOn }}.

Välj Start eller Team innan dess, så fortsätter du utan avbrott. Du betalar mot faktura från Fortnox, månadsvis eller årsvis, utan bindningstid.

- Start: {{ \App\PlanPricing::formatted(\App\CompanyPlan::Start, \App\BillingInterval::Month) }} per månad exkl. moms, 1 användare.
- Team: {{ \App\PlanPricing::formatted(\App\CompanyPlan::Team, \App\BillingInterval::Month) }} per månad exkl. moms, upp till {{ config('klartfoto.team_seat_limit', 10) }} användare och egen logotyp i rapporten.
- Betala per år och få {{ \App\PlanPricing::freeMonthsPerYear(\App\CompanyPlan::Team) }} månader gratis.

<x-mail::button :url="$billingUrl">
Välj plan
</x-mail::button>

Om du inte väljer en plan pausas nya uppdrag när provperioden är slut. Befintliga rapporter kan fortfarande öppnas.

Hälsningar<br>
Klartfoto
</x-mail::message>
