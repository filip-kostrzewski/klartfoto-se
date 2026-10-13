# Klartfoto — strona marketingowa

Klartfoto przedstawia format fotodokumentacji wykonanej pracy dla firm usługowych. Ten projekt zawiera stronę marketingową, trzy strony branżowe, przykład raportu do wydruku oraz formularz kontaktowy zapisujący zgłoszenia w lokalnej bazie SQLite. Nie zawiera aplikacji, kont użytkowników ani panelu. Domena `klartfoto.se` została kupiona, ale strona nie została opublikowana.

## Aktualny stan

- Szwedzki jest językiem domyślnym; kompletne treści są dostępne po szwedzku, angielsku, polsku, niemiecku, norwesku bokmål, duńsku i fińsku.
- Strony obejmują stronę główną, firmy sprzątające, ekipy wykonawcze, zarządców najmu, przykład raportu i prywatność.
- Formularz ma walidację serwerową, CSRF, limit żądań, honeypot, ochronę przed duplikatami i codzienną procedurę usuwania zgłoszeń starszych niż 12 miesięcy. Publiczna trasa odczytu zapisanych zgłoszeń nie istnieje.
- Otwieranie formularza zależy od `EARLY_ACCESS_ENABLED` oraz skonfigurowania administratora danych, kontaktu, hostingu i procesu retencji. Nazwa zmiennej środowiskowej zachowuje istniejącą kompatybilność.
- Automatyczne potwierdzenia e-mail są domyślnie wyłączone. Ich dostarczania nie zweryfikowano.
- Domena `klartfoto.se` jest kupiona; strona produkcyjna nie została opublikowana.

## Uruchomienie lokalne w Herd

Po przeniesieniu katalogu do `~/Herd/klartfoto` Herd udostępnia projekt pod [http://klartfoto.test](http://klartfoto.test).

Konfiguracja przy pierwszym uruchomieniu:

~~~sh
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
~~~

Aby odświeżać frontend w trakcie pracy, uruchom w drugim terminalu `npm run dev`. Lokalny plik `.env` może włączać formularz i wskazywać lokalną bazę. Zgłoszenie zawiera adres e-mail, wybrany obszar pracy, zgodę i znaczniki czasu.

Szwedzka strona główna jest pod `/`. Pozostałe wersje korzystają z prefiksów `/en`, `/pl`, `/de`, `/nb`, `/da` i `/fi`. Przełącznik języka otwiera odpowiadającą podstronę. Język wynika z adresu URL; serwis nie przekierowuje według IP ani preferencji przeglądarki. Dawne polskie adresy treści przekierowują do wersji `/pl`, a stare `/sv/…` do szwedzkich adresów głównych.

## Przygotowanie strony do wdrożenia

Przed wdrożeniem skonfigurować zakupioną domenę `klartfoto.se`, HTTPS dla `APP_URL` i `SEO_BASE_URL`, `DATA_CONTROLLER_NAME`, `DATA_CONTROLLER_EMAIL` oraz rzeczywisty `DATA_HOSTING_PROVIDER`. Przed ustawieniem `DATA_RETENTION_PURGE_ENABLED=true` sprawdzić codzienny harmonogram Laravel. Ustawić `EARLY_ACCESS_ENABLED=true` tylko po sprawdzeniu informacji o prywatności, kanału odpowiedzi na zgłoszenia i procesu usuwania danych. Nie włączać wysyłki potwierdzeń bez skonfigurowania i zweryfikowania poczty.

Indeksowanie wymaga `APP_ENV=production`, `SEO_INDEXABLE=true` i bazowego adresu HTTPS poza localhost oraz domenami lokalnymi. Lokalna trasa robots blokuje indeksowanie, a trasa sitemap zwraca 404. Na wdrożonej stronie sprawdzić wszystkie języki, formularz, robots, sitemapę i prawdziwe 404.

Globalny Nginx Herd może zachować status 404 dla dokładnej ścieżki `/robots.txt`, mimo treści zwracanej przez Laravel. Nie zmieniać globalnej konfiguracji Herd; sprawdzić trasę testami aplikacji lub przez `php artisan serve`, a status HTTP zweryfikować na serwerze witryny.

## Kontrole

~~~sh
php artisan test --compact
vendor/bin/pint --test
npm run build
php artisan schedule:list
~~~

Zobacz [plan działań](docs/PLAN.md), [mapę SEO](docs/SEO.md) i [pochodzenie zasobów](docs/ASSETS.md).

Laravel Boost podłączony w tym środowisku dotyczy innego projektu. Do weryfikacji Klartfoto używaj lokalnego Artisan, SQLite, Herd i przeglądarki.
