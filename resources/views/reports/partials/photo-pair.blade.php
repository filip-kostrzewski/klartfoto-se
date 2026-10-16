@php
    $pairKey = (string) ($pair['pair']->id ?? 'new-'.($pairIndex ?? 0));
    $submittedKey = (string) old('pair_key', '');
    $roomValue = $submittedKey === $pairKey ? old('room_name') : ($pair['room_name'] ?? '');
    $noteValue = $submittedKey === $pairKey ? old('note') : ($pair['note'] ?? '');
@endphp
<section class="photo-pair-block">
    @if ($canEditPair ?? false)
        <form class="photo-pair-meta" method="post" action="{{ route('assignments.pairs.update', $assignment, false) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="pair_key" value="{{ $pairKey }}">
            @if ($pair['pair'])
                <input type="hidden" name="pair_id" value="{{ $pair['pair']->id }}">
            @endif
            @if ($pair['before'])
                <input type="hidden" name="before_photo_id" value="{{ $pair['before']->id }}">
            @endif
            @if ($pair['after'])
                <input type="hidden" name="after_photo_id" value="{{ $pair['after']->id }}">
            @endif
            <label>
                <span>Rum</span>
                <input name="room_name" type="text" maxlength="80" value="{{ $roomValue }}">
            </label>
            <label>
                <span>Anteckning</span>
                <textarea name="note" maxlength="500" rows="2">{{ $noteValue }}</textarea>
            </label>
            <button class="button button-outline-dark" type="submit">Spara</button>
        </form>
    @elseif (($pair['room_name'] ?? null) || ($pair['note'] ?? null))
        <header class="photo-pair-caption">
            @if ($pair['room_name'] ?? null)
                <h2>{{ $pair['room_name'] }}</h2>
            @endif
            @if ($pair['note'] ?? null)
                <p>{{ $pair['note'] }}</p>
            @endif
        </header>
    @endif
    <div class="photo-pair">
        <figure>
            <figcaption>Före</figcaption>
            @if ($pair['before'])
                <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['before']], false) }}" alt="{{ ($pair['room_name'] ?? null) ? 'Före, '.$pair['room_name'] : 'Före arbetet' }}">
            @else
                <p class="missing-photo">Ingen förebild</p>
            @endif
            @if (($canDeletePhotos ?? false) && $pair['before'])
                <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['before']], false) }}" onsubmit="return confirm('Vill du ta bort den här bilden?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="Ta bort förebilden">Ta bort</button>
                </form>
            @endif
        </figure>
        <figure>
            <figcaption>Efter</figcaption>
            @if ($pair['after'])
                <img src="{{ route('reports.photos.show', ['token' => $assignment->public_token, 'photo' => $pair['after']], false) }}" alt="{{ ($pair['room_name'] ?? null) ? 'Efter, '.$pair['room_name'] : 'Efter arbetet' }}">
            @else
                <p class="missing-photo">Ingen efterbild</p>
            @endif
            @if (($canDeletePhotos ?? false) && $pair['after'])
                <form class="photo-delete" method="post" action="{{ route('assignments.photos.destroy', [$assignment, $pair['after']], false) }}" onsubmit="return confirm('Vill du ta bort den här bilden?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="Ta bort efterbilden">Ta bort</button>
                </form>
            @endif
        </figure>
    </div>
</section>
