@php
    $exampleFlag = trans('marketing.report.example_flag');
    $shotVersion = config('klartfoto.demo_asset_version');
@endphp
<div class="example-mobile-shots">
    <a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">
        <img src="{{ asset('images/demo/example-report-phone.webp') }}?v={{ $shotVersion }}" width="390" height="844" alt="{{ $exampleFlag }}. {{ $copy['home']['photo_note'] }}" loading="lazy" decoding="async">
    </a>
    <a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">
        <img src="{{ asset('images/demo/example-report-phone-photos.webp') }}?v={{ $shotVersion }}" width="390" height="844" alt="{{ $exampleFlag }}. {{ $copy['home']['photo_note'] }}" loading="lazy" decoding="async">
    </a>
    <a href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">
        <img src="{{ asset('images/demo/example-report-phone-footer.webp') }}?v={{ $shotVersion }}" width="390" height="520" alt="{{ $exampleFlag }}. {{ $copy['home']['photo_note'] }}" loading="lazy" decoding="async">
    </a>
</div>
