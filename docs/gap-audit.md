# Audyt luk Klartfoto

Stan kodu: 17 października 2026. Ceny konkurencji tylko z podanych stron z tego dnia. Nie mieszam sprzecznych kwot w jedną liczbę. Nie dopisuję cen, których strona nie podaje.

## Co Klartfoto już robi

Publicznie: zdjęcia przed/po, klient, adres i data, link do raportu bez konta klienta, przeglądarka mobilna, 14 dni próby bez karty z pełnym Team, Start 390 kr/mies. i Team 790 kr/mies. exkl. moms (rocznie 3 900 / 7 900 kr), 1 vs 10 użytkowników, logo w Team, nieograniczone raporty, abonament ciągły fakturowany z góry przez Fortnox, archiwum, usuwanie zlecenia, wersje, granskning, PDF i e-mail. Pilotaż na zaproszenie został usunięty.

## Konkurenci (tylko zweryfikowane)

- **CompanyCam** ([pricing](https://companycam.com/pricing)): Core dla 1–2 użytkowników, 79 USD/mies. month-to-month albo 63 USD/mies. przy płatności rocznej. Na stronie: zdjęcia i wideo ze znacznikiem czasu, historia projektu, raporty, synchronizacja offline, płatności na miejscu (USA), aktualizacje zlecenia na żywo. Dodatkowych cen za użytkownika nie uśredniam.
- **QPoint** ([qpoint.se](https://qpoint.se/)): 250 kr/mies. (5 użytkowników, 10 kontroli na obiekt i rok, nielimitowane wydruki, faktura kwartalna) oraz Pro+ 500 kr/mies. (99 użytkowników, nielimitowane kontrole, importy). INSTA 800 nie jest tu przypisywane, bo cytowana strona tego nie stwierdza.
- **Fieldwire** ([pricing](https://www.fieldwire.com/pricing/)): Basic za darmo (5 użytkowników, 3 projekty, 100 arkuszy). Pro 39 USD/użytkownik/mies. rocznie albo 54 USD/użytkownik/mies. miesięcznie (468 USD/użytkownik/rok). Business na stronie cennika: 64 USD/użytkownik/mies. rocznie. Artykuł pomocy podaje inne kwoty Business; nie scalam ich w jedną.

Ceny Protokoll24, DokuAI, ProofLog, ProofRoll, SiteSnapr, JobReceipt, Photo Reports, DocketSnap, Normly, GeoTapp i SmartDok nie były ponownie sprawdzane. Nie podaję ich.

## Luki

| Luka | Obietnica albo konkurent | Kod teraz | Priorytet | Wysiłek |
| --- | --- | --- | --- | --- |
| GPS przy zdjęciu | CompanyCam: lokalizacja i czas zdjęcia | EXIF w `StoreAssignmentPhotos` czyta tylko orientację, nie współrzędne | ważny | średni: odczyt GPS, zgoda, pokazanie w raporcie |
| Offline | CompanyCam: sync offline | Zdjęcia idą od razu z przeglądarki; brak kolejki offline | ważny | duży |
| Adnotacje na zdjęciu | Typowe w CompanyCam i Fieldwire | Brak warstwy rysowania | ważny | duży |
| Podpis klienta | Odbiór pracy u konkurencji | Jest tylko odpowiedź „otrzymano” i komentarz, bez podpisu | ważny | średni |
| Checklista w raporcie | QPoint opiera się na kontrolach | `/exempel-rapport` nie pokazuje checklisty; w raporcie klienta jej nie ma | ważny | średni, jeśli ma wejść do wersji raportu |
| Moms w kasie | Ceny na stronie są „exkl. moms” | `config/cashier.php` `tax_enabled` domyślnie false; brak stawki 25% w konfiguracji | drobny, dopóki copy zostaje „exkl. moms” | mały, gdy księgowość potwierdzi |
| Limit storage | Konkurenci często limitują przestrzeń | Brak kwoty w `config/klartfoto.php`; zdjęcie max krawędź 1600 px | drobny | mały, po decyzji produktowej |
| Retencja zdjęć | Nie jest obiecana konkretną liczbą dni | `config/marketing.php` `retention_purge_enabled` domyślnie false | drobny | mały, po decyzji prawnej |
| Płatność na miejscu | CompanyCam (USA) | Faktura, nie płatność kartą na obiekcie | drobny | duży i poza obecnym modelem |

## Pięć rzeczy do zbudowania

1. GPS ze zgody użytkownika, zapisany przy zdjęciu i widoczny w raporcie.
2. Checklista w wersji raportu, nie tylko na stronie marketingowej.
3. Podpis albo jednoznaczne potwierdzenie odbioru na linku klienta.
4. Adnotacje na zdjęciu przed publikacją wersji.
5. Kolejka offline dla zdjęć z telefonu.

Moms, storage i retencja zostają poza tą piątką, dopóki strona nie obiecuje innej liczby niż „exkl. moms” i dopóki nie ma decyzji o limicie albo okresie przechowywania.
