# SEO i mapa adresów

Strona jest przeznaczona dla firm szukających praktycznego sposobu na dokumentowanie wykonanej usługi i przekazywanie klientowi raportu. Głównym rynkiem jest Szwecja, a szwedzka wersja jest dostępna pod ścieżką główną. Obecnie serwis zawiera pełne teksty po szwedzku, angielsku, polsku, niemiecku, norwesku bokmål, duńsku i fińsku. Domena `klartfoto.se` została kupiona, ale strona nie została opublikowana; lokalny canonical pozostaje pod adresem `.test`. Formularz służy do kontaktu w sprawie Klartfoto; treści mają opisywać wartość raportu bez sugerowania, że istnieją konta lub działająca aplikacja. Nie zakładamy wolumenów wyszukiwań ani pozycji w Google.

## Szwedzkie intencje wyszukiwania

| Grupy fraz | Intencja | Główny adres |
|---|---|---|
| fotodokumentation utfört arbete; fotorapport till kund; rapport för serviceföretag | Ocena narzędzia do dokumentowania i przekazania wykonanej pracy | `/` |
| städrapport för städföretag; rapport efter städning till kund; före- och efterbilder vid städning | Znalezienie procesu raportowania dla firmy sprzątającej | `/for-stadforetag` |
| dokumentera renovering; fotodokumentation hantverkare; rapportera utfört arbete till kund | Rejestrowanie postępu i przekazywanie etapu pracy | `/for-hantverkare` |
| rapport inför nästa gäst; checklista mellan bokningar; förberedelse av lägenhet för uthyrning | Przygotowanie wynajmowanego lokalu między pobytami i przekazanie jego stanu | `/for-uthyrare` |
| mall före- och efterrapport; rapportmall med bilder; utskrivbar rapport efter arbete | Obejrzenie i wydrukowanie użytecznego wzoru raportu | `/rapportmall-fore-efter` |

Ogólna szwedzka fraza „städrapport” może dotyczyć także raportów robotów odkurzających. Tytuły, nagłówki i przykłady powinny jasno wskazywać na firmy: städföretag, utfört arbete, rapport till kunden. Nie kierować stron na konsumenckie zapytania o sprzątanie w konkretnym mieście.

## Architektura stron

Każdy język ma te same sześć typów pełnych stron: pięć przeznaczonych do indeksowania i jedną informację o prywatności z noindex.

- Strona główna wyjaśnia pomysł raportu zdjęciowego, pokazuje interaktywne porównanie przed i po oraz fragment przykładowego raportu.
- Strona dla firm sprzątających ma własną checklistę i przykład przekazania pracy klientowi.
- Strona dla ekip remontowych ma checklistę postępu i etapu robót.
- Strona dla zarządców najmu ma checklistę przygotowania mieszkania przed kolejnym gościem.
- Wzór raportu to drukowalny HTML z wygenerowanymi zdjęciami i przykładowymi danymi.
- Informacja o prywatności opisuje sposób przetwarzania danych i kontakt, jeśli został skonfigurowany; zawsze pozostaje noindex,nofollow.

Przykład raportu można wydrukować do PDF z poziomu przeglądarki, wybierając „Zapisz jako PDF”. Nie przedstawiać go jako osobnego pliku do pobrania. Nie tworzyć wielu krótkich, powtarzalnych artykułów. Nowe materiały powinny odpowiadać na konkretne pytanie z pracy i wnosić checklistę lub przykład.

## Zlokalizowane adresy

Szwedzki używa ścieżek głównych, pozostałe języki mają krótki prefiks. Przełącznik języka prowadzi do odpowiednika bieżącej strony.

| Język | Strona główna | Sprzątanie | Remonty | Najem i nieruchomości | Wzór raportu | Prywatność | Formularz kontaktowy (POST) |
|---|---|---|---|---|---|---|---|
| SV | `/` | `/for-stadforetag` | `/for-hantverkare` | `/for-uthyrare` | `/rapportmall-fore-efter` | `/integritet` | `/kontakt` |
| EN | `/en` | `/en/for-cleaning-companies` | `/en/for-contractors` | `/en/for-rental-managers` | `/en/before-after-report-template` | `/en/privacy` | `/en/contact` |
| PL | `/pl` | `/pl/dla-firm-sprzatajacych` | `/pl/dla-ekip-remontowych` | `/pl/dla-zarzadcow-apartamentow` | `/pl/wzor-raportu-przed-po` | `/pl/polityka-prywatnosci` | `/pl/kontakt` |
| DE | `/de` | `/de/fuer-reinigungsunternehmen` | `/de/fuer-handwerksbetriebe` | `/de/fuer-vermieter` | `/de/vorher-nachher-berichtsvorlage` | `/de/datenschutz` | `/de/kontakt` |
| NB | `/nb` | `/nb/for-renholdsbedrifter` | `/nb/for-handverkere` | `/nb/for-utleiere` | `/nb/for-og-etter-rapportmal` | `/nb/personvern` | `/nb/kontakt` |
| DA | `/da` | `/da/for-rengoringsfirmaer` | `/da/for-handvaerkere` | `/da/for-udlejere` | `/da/for-og-efter-rapportskabelon` | `/da/privatliv` | `/da/kontakt` |
| FI | `/fi` | `/fi/siivousyrityksille` | `/fi/remonttiryhmille` | `/fi/vuokra-asunnoille` | `/fi/ennen-jalkeen-raporttimalli` | `/fi/tietosuoja` | `/fi/yhteys` |

Endpointy POST nie są publicznymi stronami i nie trafiają do mapy witryny. Poprzednie polskie ścieżki przekierowują na odpowiadające adresy z prefiksem `/pl`. `/sv` oraz znane ścieżki `/sv/…` przekierowują na szwedzkie adresy główne. Nieznany prefiks języka lub slug zwraca 404, a nie stronę główną. Root pozostaje po szwedzku, bo Szwecja jest głównym rynkiem.

## Canonical, hreflang i indeksowanie

Każda wersja językowa ma osobny adres, canonical wskazujący na samą siebie i wzajemne alternatywy dla `sv`, `en`, `pl`, `de`, `nb`, `da` i `fi`, w tym dla bieżącej strony. `x-default` wskazuje odpowiednik szwedzki. Canonicale i hreflangi korzystają z skonfigurowanego `SEO_BASE_URL`, a nie z hosta przesłanego w żądaniu. Dokument zawiera poprawny język HTML, Open Graph i schemat `WebSite`. Nie ma przekierowania według IP ani języka przeglądarki; odwiedzający wybiera wersję w przełączniku.

Indeksowanie wymaga jednocześnie `APP_ENV=production`, `SEO_INDEXABLE=true` oraz bazowego adresu HTTPS, który nie wskazuje na localhost, `.test` ani `.local`. Lokalna ścieżka robots blokuje indeksowanie, a mapa witryny zwraca 404. Prywatność ma `noindex,nofollow` i nie trafia do sitemap.xml.

Produkcyjna mapa witryny ma 35 adresów: stronę główną, sprzątanie, remonty, najem i wzór raportu w siedmiu językach. Pomija prywatność i endpointy POST. Nie dodaje `lastmod`, jeśli nie ma wiarygodnego źródła zmian treści. Robots dopuszcza indeksowanie tylko po spełnieniu warunków produkcyjnych i podaje skonfigurowany adres sitemap.xml. Nie dodawać statycznego `public/robots.txt`, który zasłoniłby trasę Laravel.

Globalny Nginx Herd może zachować nietypowy status 404 dla `/robots.txt`, mimo że Laravel zwróci treść trasy. Nie zmieniać globalnej konfiguracji Herd. Działanie aplikacji sprawdzać testami albo przez `php artisan serve`, a status HTTP zweryfikować osobno na serwerze danej witryny, stagingu i produkcji.

## Metadane i dane strukturalne

Każda strona ma własny zlokalizowany title, description i H1, canonical, metadane Open Graph z lokalnym językiem oraz breadcrumb tam, gdzie pasuje. Dane strukturalne ograniczają się do `Organization`, `WebSite` i właściwego `BreadcrumbList`. Nie dodawać fikcyjnych opinii, ocen, cen, `aggregateRating` ani schematu oferty dla nieustalonej ceny produktu.

## Zasoby i dystrybucja

1. Utrzymywać po jednej stronie dla odrębnej pracy nabywcy z tabeli powyżej.
2. Ulepszać przykład raportu na podstawie checklist z rozmów ze szwedzkimi firmami.
3. Dodawać najwyżej jeden nowy materiał w cyklu, jeśli daje oryginalną wartość: checklistę przekazania sprzątania, sekwencję zdjęć remontu albo kontrolę mieszkania przed kolejnym gościem.
4. Udostępniać materiały w odpowiednich kanałach zawodowych, z konkretną wskazówką; nie produkować masowo stron dla zbliżonych fraz.
5. Po skonfigurowaniu pomiaru obserwować wizyty, użycie wzoru raportu, interakcje z formularzem i dane Search Console bez obietnic pozycji lub ruchu.

## Lista przed publikacją

- [ ] Potwierdzić pisownię i prawa do marki, kontakt ds. prywatności i hosting; przygotować kupioną domenę `klartfoto.se` do publikacji.
- [ ] Ustawić HTTPS dla `APP_URL` i `SEO_BASE_URL`, wyłącznie z kanonicznym hostem i bez ścieżki aplikacji.
- [ ] Ustawić `APP_ENV=production` i `SEO_INDEXABLE=true`; sprawdzić indeksowanie stron oraz noindex dla prywatności.
- [ ] Zweryfikować unikalny title, description i H1, self-canonical oraz status 200 na każdej stronie w siedmiu językach.
- [ ] Sprawdzić wzajemne hreflangi z linkiem do samej siebie, zgodne `x-default`, cele przełącznika języka i brak automatycznych przekierowań.
- [ ] Potwierdzić prawdziwe 404 zamiast miękkiego 404 dla nieznanych adresów.
- [ ] Sprawdzić robots 200 i reguły, a także poprawny XML mapy z dokładnie 35 kanonicznymi adresami bez prywatności, POST i `lastmod`.
- [ ] Upewnić się, że żaden adres stagingowy ani `.test` nie trafia do indeksu lub mapy, a tekst formularza odpowiada faktycznemu sposobowi obsługi zgłoszeń.
- [ ] Dopasować informację o prywatności, retencję, pocztę i proces usuwania danych do faktycznego wdrożenia.
- [ ] Sprawdzić porównanie zdjęć, klawiaturę, fokus, widoki 320/390/1440 px i ograniczenie ruchu.
- [ ] Obejrzeć druk raportu w podglądzie na komputerze i telefonie.
- [ ] Po starcie sprawdzić odpowiedzi HTTP i stan Search Console; lokalny test nie potwierdza indeksowania ani pozycji.

## Oficjalne materiały Google

- [Tworzenie pomocnych i wiarygodnych treści](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Zarządzanie stronami wieloregionalnymi i wielojęzycznymi](https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites)
- [Zlokalizowane wersje stron](https://developers.google.com/search/docs/specialty/international/localized-versions)

