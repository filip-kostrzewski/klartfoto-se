<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#111715">
    <title>Fotorapport från {{ $snapshot['company_name'] }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/site.css', 'resources/js/site.js'])
</head>
<body class="report-body">
    @php
        $performedTime = $snapshot['performed_time_label'] ?? null;
        if (! filled($performedTime)) {
            $performedStartTime = is_string($snapshot['performed_time'] ?? null) ? substr($snapshot['performed_time'], 0, 5) : null;
            $performedEndTime = is_string($snapshot['performed_end_time'] ?? null) ? substr($snapshot['performed_end_time'], 0, 5) : null;
            $performedTime = $performedStartTime !== null && $performedEndTime !== null
                ? $performedStartTime.'–'.$performedEndTime
                : ($performedStartTime ?? $performedEndTime);
        }
    @endphp
    <main class="customer-report version-report">
        <header class="customer-report-header">
            @if ($logoUrl)
                <img class="customer-report-logo" src="{{ $logoUrl }}" alt="{{ $snapshot['company_name'] }}">
            @endif
            <div class="customer-report-heading-row">
                <div class="customer-report-heading-copy">
                    <p class="report-overline">Fotorapport</p>
                    <h1>{{ $snapshot['company_name'] }}</h1>
                    <p class="customer-report-date">
                        {{ \Illuminate\Support\Carbon::parse($snapshot['performed_on'])->locale('sv')->translatedFormat('j F Y') }}
                        @if (filled($performedTime))
                            · kl. {{ $performedTime }}
                        @endif
                    </p>
                </div>
                @if ($pdfUrl ?? null)
                    <a class="button button-outline-dark report-pdf-download" href="{{ $pdfUrl }}" aria-label="Ladda ner den här rapportversionen som PDF">
                        <x-icon name="download" />
                        <span>Ladda ner PDF</span>
                    </a>
                @endif
            </div>
        </header>

        <dl class="customer-report-meta">
            <div>
                <dt>Kund</dt>
                <dd>{{ $snapshot['customer_name'] }}</dd>
            </div>
            <div>
                <dt>Adress</dt>
                <dd>{{ $snapshot['address'] }}</dd>
            </div>
        </dl>

        @if (session('report_response_status'))
            <div class="report-response-flash" role="status">{{ session('report_response_status') }}</div>
        @endif
        @if ($errors->any())
            <div class="report-response-flash report-response-flash-error" role="alert" tabindex="-1">
                <strong>Kontrollera ditt svar</strong>
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $flatPairIndex = 0;
        @endphp
        @forelse (($snapshot['places'] ?? []) as $placeIndex => $place)
            <h2 class="version-report-place">{{ $place['name'] ?? 'Övrigt' }}</h2>
            @foreach (($place['pairs'] ?? []) as $pairIndex => $snapshotPair)
                @php
                    $pair = $photoPairs[$flatPairIndex] ?? null;
                    $flatPairIndex++;
                    $commentFormIsActive = (string) old('place_index') === (string) $placeIndex
                        && (string) old('pair_index') === (string) $pairIndex;
                @endphp
                @if ($pair)
                    <div class="version-report-pair" id="report-pair-{{ $placeIndex }}-{{ $pairIndex }}">
                        @include('reports.partials.version-photo-pair', ['pair' => $pair])
                        <details class="report-response-comment" @if ($commentFormIsActive) open @endif>
                        <summary>Kommentera det här bildparet</summary>
                        <form method="post" action="{{ route('reports.responses.store', ['token' => request()->route('token')], false) }}" data-submit-feedback>
                            @csrf
                            <input type="hidden" name="response_type" value="comment">
                            <input type="hidden" name="place_index" value="{{ $placeIndex }}">
                            <input type="hidden" name="pair_index" value="{{ $pairIndex }}">
                            <label for="report-comment-{{ $placeIndex }}-{{ $pairIndex }}">Kommentar till företaget</label>
                            <textarea id="report-comment-{{ $placeIndex }}-{{ $pairIndex }}" name="message" rows="3" maxlength="1000" required aria-invalid="{{ $commentFormIsActive && $errors->has('message') ? 'true' : 'false' }}" @if ($commentFormIsActive && $errors->has('message')) aria-describedby="report-comment-error-{{ $placeIndex }}-{{ $pairIndex }}" @endif>{{ $commentFormIsActive ? old('message') : '' }}</textarea>
                            @if ($commentFormIsActive)
                                @error('message') <span class="field-error" id="report-comment-error-{{ $placeIndex }}-{{ $pairIndex }}">{{ $message }}</span> @enderror
                            @endif
                            <button class="button button-outline-dark" type="submit" data-loading-text="Skickar…">Skicka kommentar</button>
                        </form>
                        </details>
                    </div>
                @endif
            @endforeach
        @empty
            <p class="empty-note">Inga bilder är tillagda i den här rapporten.</p>
        @endforelse

        <section class="report-response-panel" aria-labelledby="report-response-heading">
            <div>
                <p class="report-overline">Ditt svar</p>
                <h2 id="report-response-heading">Har du tagit emot rapporten?</h2>
                <p>Bekräfta mottagandet till företaget. Ditt svar registreras på den här rapportversionen.</p>
            </div>
            <form method="post" action="{{ route('reports.responses.store', ['token' => request()->route('token')], false) }}" data-submit-feedback>
                @csrf
                <input type="hidden" name="response_type" value="received">
                <button class="button button-lime" type="submit" data-loading-text="Sparar svar…">Jag har tagit emot rapporten</button>
            </form>
        </section>

        <footer class="customer-report-footer">
            <p>
                <span>{{ $snapshot['company_name'] }}</span>
                @if (filled($snapshot['company_phone'] ?? null))
                    <span>{{ $snapshot['company_phone'] }}</span>
                @endif
                @if (filled($snapshot['company_organization_number'] ?? null))
                    <span>org.nr {{ $snapshot['company_organization_number'] }}</span>
                @endif
            </p>
            @if (filled($snapshot['report_created_at'] ?? null))
                <p>Rapporten skapades {{ \Illuminate\Support\Carbon::parse($snapshot['report_created_at'])->timezone('Europe/Stockholm')->locale('sv')->translatedFormat('j F Y \\k\\l. H:i') }}</p>
            @endif
            <p>Rapport via Klartfoto</p>
        </footer>
    </main>
</body>
</html>
