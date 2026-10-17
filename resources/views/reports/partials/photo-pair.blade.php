@if (! ($canDeletePhotos ?? false) && (filled($pair['label'] ?? null) || filled($pair['customerComment'] ?? null)))
    <header class="photo-pair-caption">
        @if (filled($pair['label'] ?? null))
            <h2>{{ $pair['label'] }}</h2>
        @endif
        @if (filled($pair['customerComment'] ?? null))
            <p>{{ $pair['customerComment'] }}</p>
        @endif
    </header>
@endif
<section class="photo-pair">
    <figure>
        <figcaption>Före</figcaption>
        @if ($pair['before'])
            <img src="{{ ($canDeletePhotos ?? false) ? route('assignments.photos.preview', [$assignment, $pair['before']], false) : route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['before']], false) }}" alt="Före arbetet">
        @else
            <p class="missing-photo">Ingen förebild</p>
        @endif
        @if (($canDeletePhotos ?? false) && ! empty($pair['beforeUploader']))
            <span class="photo-uploader">Uppladdad av {{ $pair['beforeUploader'] }}</span>
        @endif
        @if (($canDeletePhotos ?? false) && $pair['before'])
            <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['before']], false) }}" onsubmit="return confirm('Vill ni ta bort den här bilden?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Ta bort förebilden">Ta bort</button>
            </form>
        @endif
    </figure>
    <figure>
        <figcaption>Efter</figcaption>
        @if ($pair['after'])
            <img src="{{ ($canDeletePhotos ?? false) ? route('assignments.photos.preview', [$assignment, $pair['after']], false) : route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['after']], false) }}" alt="Efter arbetet">
        @else
            <p class="missing-photo">Ingen efterbild</p>
        @endif
        @if (($canDeletePhotos ?? false) && ! empty($pair['afterUploader']))
            <span class="photo-uploader">Uppladdad av {{ $pair['afterUploader'] }}</span>
        @endif
        @if (($canDeletePhotos ?? false) && $pair['after'])
            <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['after']], false) }}" onsubmit="return confirm('Vill ni ta bort den här bilden?')">
                @csrf
                @method('DELETE')
                <button type="submit" aria-label="Ta bort efterbilden">Ta bort</button>
            </form>
        @endif
    </figure>
</section>
