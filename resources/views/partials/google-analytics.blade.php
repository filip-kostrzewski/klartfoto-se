@php($measurementId = \App\GoogleAnalytics::measurementId())
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
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
        gtag('js', new Date());
        gtag('config', @json($measurementId));
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
@endif
