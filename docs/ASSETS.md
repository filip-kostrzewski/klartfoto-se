# Zasoby graficzne

Jedna spójna generacja przedstawia tę samą neutralną kuchnię przed i po uporządkowaniu. Obraz służy wyłącznie jako poglądowa ilustracja, nie dokumentuje pracy klienta. Nie zawiera osób, danych, tekstu ani marek.

- Narzędzie: wbudowana generacja obrazu Codex, prompt tekstowy.
- Brief promptu: „Stwórz realistyczny, szeroki dyptyk złożony z dwóch pionowych kadrów tej samej współczesnej kuchni. Utrzymaj identyczną geometrię, ustawienie kamery, perspektywę, obiektyw, światło dzienne i pozycje mebli po obu stronach. Pokaż okno z rośliną, jasny blat, biały backsplash, jasne drewniane szafki, zlew z chromowanym kranem, płytę i piekarnik. Lewy kadr przedstawia zwykły bałagan po pracy; prawy tę samą kuchnię po sprzątaniu. Naturalna fotografia wnętrza, realistyczne materiały i światło, bez ludzi, napisów, logo i znaków wodnych.”
- Źródło: `resources/images/source/kitchen-diptych.png` — PNG 1536 × 1024, 2 300 167 B.
- Podział: równe połowy po 768 × 1024 px; lewa połowa to przed, prawa to po. Oba kadry mają wspólne proporcje i używają tego samego object-fit oraz object-position.
- Pliki responsywne: `public/images/demo/kitchen-before-480.webp` (480 × 640, 32 232 B), `kitchen-before-768.webp` (768 × 1024, 69 860 B), `kitchen-after-480.webp` (480 × 640, 29 036 B), `kitchen-after-768.webp` (768 × 1024, 63 588 B). Razem około 191 KiB.
- Hero ładuje większy wariant z priorytetem i srcset; niższe przykłady używają lazy loading. Uchwyt zmienia wyłącznie maskę/przesunięcie podziału. Nie twórz zdjęć pomieszczeń przez CSS ani SVG.
- Prompt i zasób wolno zmienić po wyborze marki lub kierunku wizualnego; nie przypisuj obrazów prawdziwej firmie ani klientowi.
