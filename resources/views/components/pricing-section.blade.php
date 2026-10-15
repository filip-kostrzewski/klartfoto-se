@props(['pricing'])

@if (config('klartfoto.pricing_visible'))
    <section class="pricing-section section-pad" id="priser">
        <div class="section-kicker"><span>05</span> {{ $pricing['kicker'] }}</div>
        <div class="pricing-heading">
            <h2>{{ $pricing['title'] }}<br><em>{{ $pricing['emphasis'] }}</em></h2>
        </div>
        <div class="pricing-grid">
            <article class="pricing-card">
                <h3>{{ $pricing['start_name'] }}</h3>
                <p class="pricing-price">{{ $pricing['start_price'] }} <span>{{ $pricing['start_vat'] }}</span></p>
                <ul>
                    @foreach ($pricing['start_features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            </article>
            <article class="pricing-card pricing-card-team">
                <h3>{{ $pricing['team_name'] }}</h3>
                <p class="pricing-price">{{ $pricing['team_price'] }} <span>{{ $pricing['team_vat'] }}</span></p>
                <ul>
                    @foreach ($pricing['team_features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            </article>
        </div>
        <p class="pricing-note">{{ $pricing['note'] }} {{ $pricing['invoice_note'] }}</p>
        <a class="button button-lime" href="/register">{{ $pricing['cta'] }} <span aria-hidden="true">↗</span></a>
    </section>
@endif
