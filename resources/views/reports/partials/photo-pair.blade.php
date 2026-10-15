<section class="photo-pair">
    <figure>
        @if ($pair['before'])
            <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['before']], false) }}" alt="Före städningen">
        @else
            <p class="missing-photo">Ingen förebild</p>
        @endif
        <figcaption>Före</figcaption>
        @if (($canDeletePhotos ?? false) && $pair['before'])
            <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['before']], false) }}" onsubmit="return confirm('Vill du ta bort den här bilden?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Ta bort förebilden">Ta bort</button>
            </form>
        @endif
    </figure>
    <figure>
        @if ($pair['after'])
            <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['after']], false) }}" alt="Efter städningen">
        @else
            <p class="missing-photo">Ingen efterbild</p>
        @endif
        <figcaption>Efter</figcaption>
        @if (($canDeletePhotos ?? false) && $pair['after'])
            <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['after']], false) }}" onsubmit="return confirm('Vill du ta bort den här bilden?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Ta bort efterbilden">Ta bort</button>
            </form>
        @endif
    </figure>
</section>
