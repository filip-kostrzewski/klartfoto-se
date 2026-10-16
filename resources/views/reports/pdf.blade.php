<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <title>Fotorapport från {{ $companyName }}</title>
    <style>
        @page {
            size: A4;
            margin: 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #17211b;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
        }

        .report-header {
            border-bottom: 1px solid #d5ddd5;
            margin-bottom: 8mm;
            padding-bottom: 6mm;
        }

        .report-logo {
            display: block;
            margin: 0 0 5mm;
            max-height: 24mm;
            max-width: 55mm;
            object-fit: contain;
        }

        .report-kicker {
            color: #53635a;
            font-size: 8pt;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            font-size: 22pt;
            line-height: 1.15;
            margin: 2mm 0;
            overflow-wrap: anywhere;
        }

        .report-date {
            color: #53635a;
            margin: 2mm 0 0;
        }

        .report-meta {
            border-collapse: collapse;
            margin-bottom: 8mm;
            table-layout: fixed;
            width: 100%;
        }

        .report-meta td {
            padding: 0 5mm 0 0;
            vertical-align: top;
            width: 50%;
        }

        .report-meta-label,
        .photo-caption {
            color: #53635a;
            display: block;
            font-size: 8pt;
            margin-bottom: 1mm;
        }

        .report-meta-value {
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .report-place {
            border-bottom: 1px solid #d5ddd5;
            font-size: 15pt;
            margin: 7mm 0 3mm;
            padding-bottom: 2mm;
            page-break-after: avoid;
        }

        .report-pair {
            border: 1px solid #d5ddd5;
            border-radius: 2mm;
            margin: 0 0 6mm;
            padding: 4mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .pair-label {
            font-size: 11pt;
            margin: 0 0 3mm;
            overflow-wrap: anywhere;
        }

        .pair-photos {
            border-collapse: separate;
            border-spacing: 3mm 0;
            table-layout: fixed;
            width: 100%;
        }

        .pair-photos td {
            page-break-inside: avoid;
            vertical-align: top;
            width: 50%;
        }

        .pair-photos figure {
            margin: 0;
            page-break-inside: avoid;
        }

        .pair-photos img {
            display: block;
            height: auto;
            margin: 0 auto;
            max-height: 95mm;
            max-width: 100%;
            object-fit: contain;
        }

        .customer-comment {
            border-left: 2px solid #b6ce70;
            margin: 4mm 0 0;
            overflow-wrap: anywhere;
            padding-left: 3mm;
            white-space: pre-wrap;
        }

        .empty-phase {
            color: #87938b;
            font-size: 8pt;
            padding-top: 5mm;
        }

    </style>
</head>
<body>
    <header class="report-header">
        @if ($logoDataUri)
            <img class="report-logo" src="{{ $logoDataUri }}" alt="{{ $companyName }}">
        @endif
        <span class="report-kicker">Fotorapport · Klartfoto · Version {{ $versionNumber }}</span>
        <h1>{{ $companyName }}</h1>
        @if ($performedOn)
            <p class="report-date">
                {{ \Illuminate\Support\Carbon::parse($performedOn)->locale('sv')->translatedFormat('j F Y') }}
                @if (filled($performedTime ?? null))
                    · kl. {{ $performedTime }}
                @endif
            </p>
        @endif
    </header>

    <table class="report-meta">
        <tbody>
            <tr>
                <td>
                    <span class="report-meta-label">Kund</span>
                    <div class="report-meta-value">{{ $customerName }}</div>
                </td>
                <td>
                    <span class="report-meta-label">Adress</span>
                    <div class="report-meta-value">{{ $address }}</div>
                </td>
            </tr>
            @if (filled($companyPhone ?? null) || filled($companyOrganizationNumber ?? null))
                <tr>
                    <td colspan="2">
                        <span class="report-meta-label">Företagsuppgifter</span>
                        <div class="report-meta-value">
                            @if (filled($companyPhone ?? null))
                                {{ $companyPhone }}
                            @endif
                            @if (filled($companyPhone ?? null) && filled($companyOrganizationNumber ?? null))
                                ·
                            @endif
                            @if (filled($companyOrganizationNumber ?? null))
                                org.nr {{ $companyOrganizationNumber }}
                            @endif
                        </div>
                    </td>
                </tr>
            @endif
            @if (filled($reportCreatedAt ?? null))
                <tr>
                    <td colspan="2">
                        <span class="report-meta-label">Rapporten skapades</span>
                        <div class="report-meta-value">
                            {{ \Illuminate\Support\Carbon::parse($reportCreatedAt)->timezone('Europe/Stockholm')->locale('sv')->translatedFormat('j F Y \\k\\l. H:i') }}
                        </div>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    @foreach ($places as $place)
        <h2 class="report-place">{{ $place['name'] }}</h2>
        @foreach ($place['pairs'] as $pair)
            <section class="report-pair">
                @if ($pair['label'])
                    <h3 class="pair-label">{{ $pair['label'] }}</h3>
                @endif
                <table class="pair-photos">
                    <tbody>
                        <tr>
                            <td>
                                @if ($pair['beforeDataUri'])
                                    <figure>
                                        <figcaption class="photo-caption">Före</figcaption>
                                        <img src="{{ $pair['beforeDataUri'] }}" alt="Före arbetet">
                                    </figure>
                                @else
                                    <span class="empty-phase">Före saknas</span>
                                @endif
                            </td>
                            <td>
                                @if ($pair['afterDataUri'])
                                    <figure>
                                        <figcaption class="photo-caption">Efter</figcaption>
                                        <img src="{{ $pair['afterDataUri'] }}" alt="Efter arbetet">
                                    </figure>
                                @else
                                    <span class="empty-phase">Efter saknas</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
                @if ($pair['customerComment'])
                    <p class="customer-comment">{{ $pair['customerComment'] }}</p>
                @endif
            </section>
        @endforeach
    @endforeach

</body>
</html>
