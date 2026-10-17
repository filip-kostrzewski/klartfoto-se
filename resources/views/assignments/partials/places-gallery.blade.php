        <section class="assignment-photos-section" aria-labelledby="assignment-photos-heading">
            <div class="assignment-section-heading">
                <div><p class="section-kicker">Foton</p><h2 id="assignment-photos-heading">Platser och bildpar</h2></div>
                <span class="photo-pair-count">{{ $assignment->photos->count() }} {{ $assignment->photos->count() === 1 ? 'bild' : 'bilder' }}</span>
            </div>

            @forelse ($assignment->places as $place)
                @php
                    $placeIds = $assignment->places->pluck('id')->map(fn ($id) => (int) $id)->all();
                @endphp
                <article class="assignment-place-card" aria-labelledby="place-heading-{{ $place->id }}">
                    <header class="assignment-place-heading">
                        <div>
                            <p class="section-kicker">Plats {{ $loop->iteration }}</p>
                            <h3 id="place-heading-{{ $place->id }}">{{ $place->name }}</h3>
                            <p>{{ $place->photoPairs->count() }} {{ $place->photoPairs->count() === 1 ? 'bildpar' : 'bildpar' }}</p>
                        </div>
                        @if ($canManageJobs && $assignment->places->count() > 1)
                            <div class="reorder-controls" role="group" aria-label="Ändra ordning för {{ $place->name }}">
                                @if (! $loop->first)
                                    @php
                                        $upPlaceIds = $placeIds;
                                        [$upPlaceIds[$loop->index - 1], $upPlaceIds[$loop->index]] = [$upPlaceIds[$loop->index], $upPlaceIds[$loop->index - 1]];
                                    @endphp
                                    <form method="post" action="{{ route('assignments.places.reorder', $assignment, false) }}">
                                        @csrf
                                        @method('PUT')
                                        @foreach ($upPlaceIds as $placeId)
                                            <input type="hidden" name="place_ids[]" value="{{ $placeId }}">
                                        @endforeach
                                        <button class="button button-order" type="submit" aria-label="Flytta {{ $place->name }} upp">↑ <span>Upp</span></button>
                                    </form>
                                @endif
                                @if (! $loop->last)
                                    @php
                                        $downPlaceIds = $placeIds;
                                        [$downPlaceIds[$loop->index], $downPlaceIds[$loop->index + 1]] = [$downPlaceIds[$loop->index + 1], $downPlaceIds[$loop->index]];
                                    @endphp
                                    <form method="post" action="{{ route('assignments.places.reorder', $assignment, false) }}">
                                        @csrf
                                        @method('PUT')
                                        @foreach ($downPlaceIds as $placeId)
                                            <input type="hidden" name="place_ids[]" value="{{ $placeId }}">
                                        @endforeach
                                        <button class="button button-order" type="submit" aria-label="Flytta {{ $place->name }} ned">↓ <span>Ned</span></button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </header>

                    @forelse ($place->photoPairs as $photoPair)
                        @php
                            $pairIds = $place->photoPairs->pluck('id')->map(fn ($id) => (int) $id)->all();
                            $phasePhotos = $photoPair->photos->keyBy(fn ($photo) => $photo->phase->value);
                            $needsBefore = $phasePhotos->get('before') === null;
                            $needsAfter = $phasePhotos->get('after') === null;
                            $pairFormWasSubmitted = (string) old('_photo_pair_id') === (string) $photoPair->id;
                            $pairLabelValue = $pairFormWasSubmitted
                                ? old('label', $photoPair->label)
                                : $photoPair->label;
                            $customerCommentValue = $pairFormWasSubmitted
                                ? old('customer_comment', $photoPair->customer_comment)
                                : $photoPair->customer_comment;
                            $internalNoteValue = $pairFormWasSubmitted
                                ? old('internal_note', $photoPair->internal_note)
                                : $photoPair->internal_note;
                            $pair = [
                                'before' => $phasePhotos->get('before')?->id,
                                'after' => $phasePhotos->get('after')?->id,
                                'beforeUploader' => $phasePhotos->get('before')?->uploadedBy?->name,
                                'afterUploader' => $phasePhotos->get('after')?->uploadedBy?->name,
                                'label' => $photoPair->label,
                            ];
                        @endphp
                        <div class="assignment-pair-card">
                            <div class="assignment-pair-heading">
                                <strong>{{ $photoPair->label ?: 'Bildpar '.$loop->iteration }}</strong>
                                @if ($canManageJobs && $place->photoPairs->count() > 1)
                                    <div class="reorder-controls" role="group" aria-label="Ändra ordning för bildpar {{ $loop->iteration }} i {{ $place->name }}">
                                        @if (! $loop->first)
                                            @php
                                                $upPairIds = $pairIds;
                                                [$upPairIds[$loop->index - 1], $upPairIds[$loop->index]] = [$upPairIds[$loop->index], $upPairIds[$loop->index - 1]];
                                            @endphp
                                            <form method="post" action="{{ route('assignments.places.pairs.reorder', [$assignment, $place], false) }}">
                                                @csrf
                                                @method('PUT')
                                                @foreach ($upPairIds as $pairId)
                                                    <input type="hidden" name="pair_ids[]" value="{{ $pairId }}">
                                                @endforeach
                                                <button class="button button-order" type="submit" aria-label="Flytta bildpar {{ $loop->iteration }} upp">↑ <span>Upp</span></button>
                                            </form>
                                        @endif
                                        @if (! $loop->last)
                                            @php
                                                $downPairIds = $pairIds;
                                                [$downPairIds[$loop->index], $downPairIds[$loop->index + 1]] = [$downPairIds[$loop->index + 1], $downPairIds[$loop->index]];
                                            @endphp
                                            <form method="post" action="{{ route('assignments.places.pairs.reorder', [$assignment, $place], false) }}">
                                                @csrf
                                                @method('PUT')
                                                @foreach ($downPairIds as $pairId)
                                                    <input type="hidden" name="pair_ids[]" value="{{ $pairId }}">
                                                @endforeach
                                                <button class="button button-order" type="submit" aria-label="Flytta bildpar {{ $loop->iteration }} ned">↓ <span>Ned</span></button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @include('reports.partials.photo-pair', ['pair' => $pair, 'assignment' => $assignment, 'canDeletePhotos' => true])
                            @if ($canManageJobs && $needsBefore && $needsAfter)
                                <form method="post" action="{{ route('assignments.places.pairs.destroy', [$assignment, $place, $photoPair], false) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-danger" type="submit">Ta bort tomt bildpar</button>
                                </form>
                            @endif
                            @if ($canManageJobs && ($needsBefore || $needsAfter))
                                <details class="assignment-pair-fill" @if ((string) old('assignment_photo_pair_id') === (string) $photoPair->id || $errors->has('before_photos') || $errors->has('after_photos') || $errors->has('before_upload_ids') || $errors->has('after_upload_ids')) open @endif>
                    <summary>Fyll i saknad bildruta <span>i det här bildparet</span></summary>
                                    <p>Välj bara den saknade bilden. Den läggs till i {{ $place->name }}, {{ $photoPair->label ?: 'bildpar '.$loop->iteration }} — ett nytt par skapas inte.</p>
                                    <form class="assignment-pair-fill-form" method="post" action="{{ route('assignments.photos.store', $assignment, false) }}" enctype="multipart/form-data" data-photo-upload-mode="existing-pair">
                                        @csrf
                                        <input type="hidden" name="assignment_photo_pair_id" value="{{ $photoPair->id }}">
                                        <input type="hidden" name="place_name" value="{{ $place->name }}">
                                        @if ($needsBefore)
                                            @include('assignments.partials.photo-input', [
                                                'inputId' => 'pair_before_'.$photoPair->id,
                                                'inputName' => 'before_photos[]',
                                                'uploadIdName' => 'before_upload_ids[]',
                                                'label' => 'Lägg till förebild',
                                                'multiple' => false,
                                                'required' => true,
                                            ])
                                        @endif
                                        @if ($needsAfter)
                                            @include('assignments.partials.photo-input', [
                                                'inputId' => 'pair_after_'.$photoPair->id,
                                                'inputName' => 'after_photos[]',
                                                'uploadIdName' => 'after_upload_ids[]',
                                                'label' => 'Lägg till efterbild',
                                                'multiple' => false,
                                                'required' => true,
                                            ])
                                        @endif
                                        <div class="photo-upload-progress" data-upload-progress-list role="status" aria-live="polite" hidden></div>
                                        <p class="photo-upload-feedback" data-upload-feedback role="alert" tabindex="-1" hidden></p>
                                        <button class="button button-outline-dark" type="submit" data-upload-submit data-loading-text="Laddar upp…">Lägg till i bildparet</button>
                                    </form>
                                </details>
                            @endif
                            @if ($canManageJobs)
                                <details class="assignment-pair-notes" @if ($pairFormWasSubmitted) open @endif>
                                    <summary>Namn och kommentarer <span>Kund och team</span></summary>
                                    <form class="assignment-pair-notes-form" method="post" action="{{ route('assignments.places.pairs.notes.update', [$assignment, $place, $photoPair], false) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_photo_pair_id" value="{{ $photoPair->id }}">
                                        <div class="assignment-pair-note-field assignment-pair-label-field">
                                            <label for="pair-label-{{ $photoPair->id }}">Namn på bildparet <span class="field-optional">valfritt</span></label>
                                            <p id="pair-label-help-{{ $photoPair->id }}">Skilj bilderna åt inom {{ $place->name }}, till exempel bänkskiva eller handfat.</p>
                                            <input id="pair-label-{{ $photoPair->id }}" name="label" type="text" maxlength="120" value="{{ $pairLabelValue }}" aria-invalid="{{ $pairFormWasSubmitted && $errors->has('label') ? 'true' : 'false' }}" @if ($pairFormWasSubmitted && $errors->has('label')) aria-describedby="pair-label-help-{{ $photoPair->id }} pair-label-error-{{ $photoPair->id }}" @else aria-describedby="pair-label-help-{{ $photoPair->id }}" @endif>
                                            @if ($pairFormWasSubmitted)
                                                @error('label')<span class="field-error" id="pair-label-error-{{ $photoPair->id }}">{{ $message }}</span>@enderror
                                            @endif
                                        </div>
                                        <div class="assignment-pair-note-field">
                                            <label for="customer-comment-{{ $photoPair->id }}">Kommentar till kunden</label>
                                            <p id="customer-comment-help-{{ $photoPair->id }}">Visas vid bildparet när du publicerar nästa rapportversion.</p>
                                            <textarea id="customer-comment-{{ $photoPair->id }}" name="customer_comment" rows="3" maxlength="1000" aria-invalid="{{ $pairFormWasSubmitted && $errors->has('customer_comment') ? 'true' : 'false' }}" aria-describedby="customer-comment-help-{{ $photoPair->id }}@if ($pairFormWasSubmitted && $errors->has('customer_comment')) customer-comment-error-{{ $photoPair->id }}@endif">{{ $customerCommentValue }}</textarea>
                                            @if ($pairFormWasSubmitted)
                                                @error('customer_comment')<span class="field-error" id="customer-comment-error-{{ $photoPair->id }}">{{ $message }}</span>@enderror
                                            @endif
                                        </div>
                                        <div class="assignment-pair-note-field">
                                            <label for="internal-note-{{ $photoPair->id }}">Intern anteckning</label>
                                            <p id="internal-note-help-{{ $photoPair->id }}">Bara synlig i Klartfoto — följer inte med i kundrapporten.</p>
                                            <textarea id="internal-note-{{ $photoPair->id }}" name="internal_note" rows="3" maxlength="2000" aria-invalid="{{ $pairFormWasSubmitted && $errors->has('internal_note') ? 'true' : 'false' }}" aria-describedby="internal-note-help-{{ $photoPair->id }}@if ($pairFormWasSubmitted && $errors->has('internal_note')) internal-note-error-{{ $photoPair->id }}@endif">{{ $internalNoteValue }}</textarea>
                                            @if ($pairFormWasSubmitted)
                                                @error('internal_note')<span class="field-error" id="internal-note-error-{{ $photoPair->id }}">{{ $message }}</span>@enderror
                                            @endif
                                        </div>
                                        <div class="assignment-pair-notes-footer">
                                            <p>Ändringarna sparas i utkastet. Skapa en ny rapportversion när kommentaren ska visas för kunden.</p>
                                            <button class="button button-outline-dark" type="submit">Spara kommentarer</button>
                                        </div>
                                    </form>
                                </details>
                            @else
                                @if (filled($photoPair->customer_comment) || filled($photoPair->internal_note))
                                    <div class="assignment-pair-notes-readonly">
                                        @if (filled($photoPair->customer_comment))
                                            <div><strong>Kommentar till kunden</strong><p>{{ $photoPair->customer_comment }}</p></div>
                                        @endif
                                        @if (filled($photoPair->internal_note))
                                            <div><strong>Intern anteckning</strong><p>{{ $photoPair->internal_note }}</p></div>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="place-empty-note">Inga bilder i den här platsen ännu.</p>
                    @endforelse
                </article>
            @empty
                @foreach ($assignment->photoPairs() as $pair)
                    @include('reports.partials.photo-pair', ['pair' => ['before' => $pair['before']?->id, 'after' => $pair['after']?->id, 'beforeUploader' => $pair['before']?->uploadedBy?->name, 'afterUploader' => $pair['after']?->uploadedBy?->name], 'assignment' => $assignment, 'canDeletePhotos' => true])
                @endforeach
                @if ($assignment->photos->isEmpty())
                    <div class="app-panel photo-empty-state">
                        <span class="empty-state-icon"><x-icon name="camera" /></span>
                        <div><h3>Inga bilder ännu</h3><p>Lägg till bilder före och efter i en namngiven plats.</p></div>
                    </div>
                @endif
            @endforelse
        </section>
