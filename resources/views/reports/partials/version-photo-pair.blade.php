<section class="photo-pair version-photo-pair" aria-label="{{ $pair['label'] ?: 'Fotopar' }}">
    <figure>
        <figcaption>Före @if (filled($pair['label']))<span>· {{ $pair['label'] }}</span>@endif</figcaption>
        @if ($pair['beforeUrl'])
            <img src="{{ $pair['beforeUrl'] }}" alt="Före arbetet — {{ $pair['placeName'] }}" loading="lazy" decoding="async">
        @else
            <p class="missing-photo">Ingen förebild</p>
        @endif
    </figure>
    <figure>
        <figcaption>Efter @if (filled($pair['label']))<span>· {{ $pair['label'] }}</span>@endif</figcaption>
        @if ($pair['afterUrl'])
            <img src="{{ $pair['afterUrl'] }}" alt="Efter arbetet — {{ $pair['placeName'] }}" loading="lazy" decoding="async">
        @else
            <p class="missing-photo">Ingen efterbild</p>
        @endif
    </figure>
</section>
@if (filled($pair['customerComment'] ?? null))
    <aside class="version-photo-comment" aria-label="Kommentar till kunden">
        <strong>Kommentar till kunden</strong>
        <p>{{ $pair['customerComment'] }}</p>
    </aside>
@endif
