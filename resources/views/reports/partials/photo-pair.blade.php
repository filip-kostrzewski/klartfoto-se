<section class="photo-pair">
    <figure>
        @if ($pair['before'])
            <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['before']], false) }}" alt="Före städningen">
        @else
            <p class="missing-photo">Ingen förebild</p>
        @endif
        <figcaption>Före</figcaption>
    </figure>
    <figure>
        @if ($pair['after'])
            <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['after']], false) }}" alt="Efter städningen">
        @else
            <p class="missing-photo">Ingen efterbild</p>
        @endif
        <figcaption>Efter</figcaption>
    </figure>
</section>
