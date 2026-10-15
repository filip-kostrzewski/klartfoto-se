<div class="cookie-banner" data-cookie-banner role="region" aria-label="{{ __('marketing.cookies.region') }}">
    <p>
        <span class="cookie-banner-long">{{ __('marketing.cookies.banner') }}</span>
        <span class="cookie-banner-short">{{ __('marketing.cookies.banner_short') }}</span>
        <a href="{{ \App\LocalizedUrl::route('privacy', $locale, false) }}#cookies">{{ __('marketing.cookies.more') }}</a>
    </p>
    <div class="cookie-banner-actions">
        <button type="button" class="cookie-choice" data-cookie-choice="denied">{{ __('marketing.cookies.reject') }}</button>
        <button type="button" class="cookie-choice" data-cookie-choice="granted">{{ __('marketing.cookies.accept') }}</button>
    </div>
</div>
<script>
    (function () {
        var name = @json(\App\GoogleAnalytics::CONSENT_COOKIE);
        var banner = document.querySelector('[data-cookie-banner]');
        var read = function () {
            var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=(granted|denied)(?:;|$)'));

            return match ? match[1] : null;
        };
        var write = function (value) {
            var secure = window.location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = name + '=' + value + '; Max-Age=15552000; Path=/; SameSite=Lax' + secure;
        };
        var apply = function (value) {
            if (typeof window.gtag !== 'function') {
                return;
            }

            window.gtag('consent', 'update', {
                analytics_storage: value === 'granted' ? 'granted' : 'denied',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied'
            });
        };
        var loadAnalytics = function () {
            if (typeof window.klartfotoLoadAnalytics === 'function') {
                window.klartfotoLoadAnalytics();
            }
        };
        var clearAnalytics = function () {
            document.cookie.split(';').forEach(function (part) {
                var cookieName = part.split('=')[0].trim();

                if (cookieName === '_ga' || cookieName === '_gid' || cookieName.indexOf('_ga_') === 0) {
                    document.cookie = cookieName + '=; Max-Age=0; Path=/';
                }
            });
        };
        var hide = function () {
            document.documentElement.classList.add('cookie-choice-saved');
        };
        var show = function () {
            document.documentElement.classList.remove('cookie-choice-saved');

            if (! banner) {
                return;
            }

            var first = banner.querySelector('button');

            if (first) {
                first.focus();
            }
        };

        document.querySelectorAll('[data-cookie-choice]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-cookie-choice') === 'granted' ? 'granted' : 'denied';
            write(value);
            apply(value);

            if (value === 'granted') {
                loadAnalytics();
            } else {
                clearAnalytics();
            }

            hide();
            });
        });

        if (read() === 'granted') {
            loadAnalytics();
        }

        document.querySelectorAll('[data-cookie-settings]').forEach(function (button) {
            button.addEventListener('click', function () {
                show();
            });
        });
    })();
</script>
