@php
    $measurementId = \App\GoogleAnalytics::measurementId();
    $analyticsAllowed = is_string($measurementId) && request()->cookie(\App\GoogleAnalytics::CONSENT_COOKIE) === 'granted';
@endphp
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    window.klartfotoAnalyticsId = @json($measurementId);
    gtag('consent', 'default', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        wait_for_update: 500
    });
    (function () {
        var match = document.cookie.match(new RegExp('(?:^|; )' + @json(\App\GoogleAnalytics::CONSENT_COOKIE) + '=(granted|denied)(?:;|$)'));
        document.documentElement.classList.toggle('cookie-choice-saved', Boolean(match));

        if (! match) {
            return;
        }

        gtag('consent', 'update', {
            analytics_storage: match[1] === 'granted' ? 'granted' : 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied'
        });
    })();
</script>
@if (is_string($measurementId))
    <script>
        window.klartfotoLoadAnalytics = function () {
            if (document.querySelector('script[data-klartfoto-analytics]')) {
                return;
            }

            gtag('js', new Date());
            gtag('config', @json($measurementId));

            var script = document.createElement('script');
            script.async = true;
            script.setAttribute('data-klartfoto-analytics', @json($measurementId));
            script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(@json($measurementId));
            document.head.appendChild(script);
        };
    </script>
@endif
@if ($analyticsAllowed)
    <script>
        gtag('js', new Date());
        gtag('config', @json($measurementId));
    </script>
    <script async data-klartfoto-analytics src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
@endif
