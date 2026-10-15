<aside class="auth-visual">
    <div class="auth-visual-photo">
        <img src="{{ asset('images/cleaning-detail.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" srcset="{{ asset('images/cleaning-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 768w, {{ asset('images/cleaning-detail.webp') }}?v={{ config('klartfoto.demo_asset_version') }} 1536w" sizes="(max-width: 760px) 100vw, 46vw" width="1536" height="1024" alt="" loading="lazy" decoding="async">
        <span class="auth-photo-badge"><span aria-hidden="true">✳</span> AI-demo · inte kundarbete</span>
        <div class="auth-visual-content">
            <p class="auth-visual-kicker">Klartfoto</p>
            <h2>{{ $authVisualTitle }}</h2>
            <p>{{ $authVisualText }}</p>
            <div class="auth-report-glance" aria-label="Exempel på rapportinnehåll">
                <div class="auth-report-glance-top">
                    <span class="report-mark" aria-hidden="true"><x-icon name="report" /></span>
                    <span><small>EXEMPEL</small><strong>Före · Efter</strong></span>
                    <span class="auth-report-link-icon" aria-hidden="true"><x-icon name="share" /></span>
                </div>
                <div class="auth-report-glance-photos">
                    <img src="{{ asset('images/demo/kitchen-before-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                    <img src="{{ asset('images/demo/kitchen-after-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                </div>
                <div class="auth-report-glance-meta"><span>Kund · Adress · Datum</span><x-icon name="check" /></div>
            </div>
        </div>
    </div>
</aside>
