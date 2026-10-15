@php($measurementId = \App\GoogleAnalytics::measurementId())
<script>
    document.documentElement.classList.toggle(
        'cookie-choice-saved',
        new RegExp('(?:^|; )' + @json(\App\GoogleAnalytics::CONSENT_COOKIE) + '=(?:granted|denied)(?:;|$)').test(document.cookie),
    );
</script>
@if (is_string($measurementId))
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

            if (! match || match[1] !== 'granted') {
                return;
            }

            gtag('consent', 'update', {
                analytics_storage: 'granted',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied'
            });
        })();
        gtag('js', new Date());
        gtag('config', @json($measurementId));
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
@endif
