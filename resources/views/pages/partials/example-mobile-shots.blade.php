@php
    $exampleFlag = trans('marketing.report.example_flag');
    $shotVersion = config('klartfoto.demo_asset_version');
    $loadPhoneShotEagerly = $loadPhoneShotEagerly ?? false;
@endphp
<div class="example-phone">
    <a class="example-phone-link" href="{{ \App\LocalizedUrl::route('report', $locale, false) }}">
        <span class="example-phone-frame">
            <span class="example-phone-speaker" aria-hidden="true"></span>
            <img src="{{ asset('images/demo/example-report-phone.webp') }}?v={{ $shotVersion }}" width="708" height="1372" alt="{{ $exampleFlag }}. Rapportens övre del: företag, objekt, datum och första bildparet." @if ($loadPhoneShotEagerly) fetchpriority="high" decoding="sync" @else loading="lazy" decoding="async" @endif>
        </span>
    </a>
</div>
