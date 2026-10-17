# Klartfoto — strona marketingowa

Klartfoto to fotodokumentacja wykonanej pracy dla firm usługowych w Szwecji. Projekt zawiera stronę marketingową w siedmiu językach, aplikację dla firm (konta, uppdrag, zdjęcia przed/po, raport udostępniany linkiem, PDF, e-mail, zespół do 10 osób) oraz fakturowanie ręczne przez Fortnox. Domena `klartfoto.se` została kupiona.

## Model biznesowy

- Rejestracja daje 14 dni za darmo z pełnym zakresem Team, bez karty. Długość ustawia `KLARTFOTO_TRIAL_DAYS`.
- Plany: Start 390 kr/mies. (1 użytkownik) i Team 790 kr/mies. (do 10 użytkowników, logo w raporcie), bez VAT. Rocznie: Start 3 900 kr, Team 7 900 kr (dwa miesiące gratis). Kwoty są w `config/klartfoto.php` pod `prices`.
- Abonament jest ciągły, bez okresu wiązania, fakturowany z góry za miesiąc lub rok przez Fortnox. Klient rezygnuje mailem przed końcem bieżącego okresu.
- Właściciel konta wysyła prośbę o fakturę ze strony abonamentu. Operator wystawia fakturę w Fortnox i po wpłacie aktywuje plan: `php artisan billing:activate {id} --invoice=F-123 --by=Imie` (plan, interwał i data końca wynikają z prośby lub bieżącego planu; `--until`, `--plan`, `--interval` nadpisują). `billing:requests` pokazuje oczekujące prośby, `billing:renewals` plany do odnowienia lub wygasłe, `billing:activate --renew` przedłuża bez nowej prośby.
- Harmonogram wysyła przypomnienia do właściciela 3 dni przed końcem triala i po jego wygaśnięciu (`billing:trial-reminders`).
- Integracja Stripe (Cashier) jest w kodzie, ale nieaktywna do czasu skonfigurowania kluczy.

## Aktualny stan

- Szwedzki jest językiem domyślnym; kompletne treści są dostępne po szwedzku, angielsku, polsku, niemiecku, norwesku bokmål, duńsku i fińsku.
- Strony obejmują stronę główną, firmy sprzątające, ekipy wykonawcze, zarządców najmu, przykład raportu i prywatność.
- Formularz ma walidację serwerową, CSRF, limit żądań, honeypot, ochronę przed duplikatami i codzienną procedurę usuwania zgłoszeń starszych niż 12 miesięcy. Publiczna trasa odczytu zapisanych zgłoszeń nie istnieje.
- Otwieranie formularza zależy od `EARLY_ACCESS_ENABLED` oraz skonfigurowania administratora danych, kontaktu, hostingu i procesu retencji. Nazwa zmiennej środowiskowej zachowuje istniejącą kompatybilność.
- Automatyczne potwierdzenia e-mail są domyślnie wyłączone. Ich dostarczania nie zweryfikowano.
- Domena `klartfoto.se` jest kupiona; strona produkcyjna nie została opublikowana.

## Uruchomienie lokalne w Herd

Po przeniesieniu katalogu do `~/Herd/klartfoto` Herd udostępnia projekt pod [http://klartfoto.test](http://klartfoto.test).

Projekt wymaga PHP 8.4 lub nowszego. Jeśli globalne `php` w Herd jest starsze, używaj binarki z Herd, np. `php85 artisan ...`.

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

Przed wdrożeniem skonfigurować zakupioną domenę `klartfoto.se`, `APP_URL=https://klartfoto.se`, `DATA_CONTROLLER_NAME`, `DATA_CONTROLLER_EMAIL` oraz rzeczywisty `DATA_HOSTING_PROVIDER`. Przed ustawieniem `DATA_RETENTION_PURGE_ENABLED=true` sprawdzić codzienny harmonogram Laravel. Ustawić `EARLY_ACCESS_ENABLED=true` tylko po sprawdzeniu informacji o prywatności, kanału odpowiedzi na zgłoszenia i procesu usuwania danych. Nie włączać wysyłki potwierdzeń bez skonfigurowania i zweryfikowania poczty.

Indeksowanie włącza się przy `APP_ENV=production`. Adresy kanoniczne, JSON-LD, robots i sitemap biorą bazę z `config('app.url')`. Pusty adres albo host lokalny jest zastępowany przez `https://klartfoto.se`. Poza produkcją robots blokuje indeksowanie, a sitemap zwraca 404. Na wdrożonej stronie sprawdzić wszystkie języki, formularz, robots, sitemapę i prawdziwe 404.

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
