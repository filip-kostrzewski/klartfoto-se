@props(['pricing'])

@if (config('klartfoto.pricing_visible'))
    <section class="pricing-section section-pad" id="priser" aria-labelledby="pricing-title">
        <div class="section-kicker"><span>04</span> {{ $pricing['kicker'] }}</div>
        <div class="pricing-heading">
            <h2 id="pricing-title">{{ $pricing['title'] }}<br><em>{{ $pricing['emphasis'] }}</em></h2>
            <p class="pricing-intro">{{ $pricing['intro'] }}</p>
        </div>
        <div class="pricing-grid">
            <article class="pricing-card">
                <div class="pricing-card-heading"><h3>{{ $pricing['start_name'] }}</h3></div>
                <p class="pricing-description">{{ $pricing['start_description'] }}</p>
                <p class="pricing-price">{{ $pricing['start_price'] }} <span>{{ $pricing['start_vat'] }}</span></p>
                <ul>
                    @foreach ($pricing['start_features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="button button-dark" href="{{ route('register', absolute: false) }}">{{ $pricing['cta'] }}</a>
            </article>
            <article class="pricing-card pricing-card-team">
                <div class="pricing-card-heading">
                    <h3>{{ $pricing['team_name'] }}</h3>
                    <span class="pricing-badge">{{ $pricing['team_badge'] }}</span>
                </div>
                <p class="pricing-description">{{ $pricing['team_description'] }}</p>
                <p class="pricing-price">{{ $pricing['team_price'] }} <span>{{ $pricing['team_vat'] }}</span></p>
                <ul>
                    @foreach ($pricing['team_features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
                <a class="button button-lime" href="{{ route('register', absolute: false) }}">{{ $pricing['cta'] }}</a>
            </article>
        </div>
        <p class="pricing-note">{{ $pricing['note'] }} {{ $pricing['invoice_note'] }}</p>
        @if (filled($pricing['pilot_note'] ?? null))
            <p class="pricing-pilot">{{ $pricing['pilot_note'] }}</p>
        @endif
        @if (filled($pricing['market_note'] ?? null))
            <p class="pricing-market">{{ $pricing['market_note'] }}</p>
        @endif
    </section>
@endif
