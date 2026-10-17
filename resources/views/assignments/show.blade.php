@extends('layouts.app')

@section('title', $assignment->customer_name.' · Klartfoto')
@section('page-heading', 'Uppdrag')

@section('content')
    @php
        $newVisitParameters = array_filter([
            'customer_id' => $assignment->customer_id,
            'customer_property_id' => $assignment->customer_property_id,
        ]);
    @endphp

    <section class="assignment-detail-page">
        @php
            $reportSnapshot = $latestReportVersion?->snapshot ?? null;
            $shareProjectName = $reportSnapshot['customer_name'] ?? $assignment->customer_name;
            $shareProjectAddress = $reportSnapshot['address'] ?? $assignment->address;
            $shareProjectDate = $reportSnapshot['performed_on'] ?? $assignment->performed_on->toDateString();
            $shareProjectTime = $reportSnapshot === null
                ? $assignment->performedTimeLabel()
                : ($reportSnapshot['performed_time_label'] ?? null);
            if ($reportSnapshot !== null && ! filled($shareProjectTime)) {
                $snapshotStartTime = is_string($reportSnapshot['performed_time'] ?? null)
                    ? substr($reportSnapshot['performed_time'], 0, 5)
                    : null;
                $snapshotEndTime = is_string($reportSnapshot['performed_end_time'] ?? null)
                    ? substr($reportSnapshot['performed_end_time'], 0, 5)
                    : null;
                $shareProjectTime = $snapshotStartTime !== null && $snapshotEndTime !== null
                    ? $snapshotStartTime.'–'.$snapshotEndTime
                    : ($snapshotStartTime ?? $snapshotEndTime);
            }
            $sharePhotoCount = $reportSnapshot === null
                ? $assignment->photos->count()
                : collect($reportSnapshot['assets'] ?? [])->where('type', 'photo')->count();
        @endphp
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> Uppdrag</p>
                <h1>{{ $assignment->customer_name }}</h1>
                <p class="auth-lead">{{ $assignment->address }} <span aria-hidden="true">·</span> {{ $assignment->performedLabel() }}</p>
            </div>
            <div class="assignment-heading-actions">
                @if ($canArchiveAssignment && ! $assignment->isArchived())
                    <button class="button button-outline-dark" type="button" data-open-dialog="archive-assignment-dialog">Arkivera</button>
                @endif
                @if ($canArchiveAssignment && $assignment->isArchived())
                    <form method="post" action="{{ route('assignments.restore', $assignment, false) }}">
                        @csrf
                        <button class="button button-outline-dark" type="submit">Återställ</button>
                    </form>
                @endif
                @if ($canManageJobs)
                    <a class="button button-outline-dark" href="{{ route('assignments.edit', $assignment, false) }}">Redigera</a>
                    <a class="button button-lime" href="{{ route('assignments.create', $newVisitParameters, false) }}">Nytt besök</a>
                @endif
                <a class="button button-outline-dark" href="#share-box">Skicka rapporten</a>
                <a class="assignment-back-link" href="{{ route('assignments.index', $assignment->isArchived() ? ['arkiv' => 'arkiverade'] : [], false) }}">{{ $assignment->isArchived() ? 'Arkiverade uppdrag' : 'Alla uppdrag' }}</a>
            </div>
        </div>

        @if ($assignment->isArchived())
            <p class="assignment-archive-note" role="status">{{ \App\Models\Assignment::ARCHIVE_SHARE_LINK_NOTICE }}</p>
        @endif

        @if (isset($canManageTeamAssignment, $canReviewAssignment, $canHandoffAssignment, $isReviewed, $teamMembers) && ($canManageTeamAssignment || $canReviewAssignment || $canHandoffAssignment || $isReviewed || (int) $assignment->assigned_to_user_id !== (int) $assignment->user_id || $assignment->reviewer_user_id))
            <details class="assignment-team-disclosure" @if ($errors->has('assigned_to_user_id') || $errors->has('reviewer_user_id')) open @endif>
                <summary class="assignment-team-summary" id="assignment-team-heading">
                    <span>Team <span aria-hidden="true">·</span> Ansvar och granskning</span>
                    @if ($isReviewed)
                        <span class="assignment-review-badge">Granskad</span>
                    @else
                        <span class="assignment-review-status">
                            <span class="assignment-review-badge assignment-review-badge-pending">Inte granskad</span>
                            <span class="assignment-review-hint">Inte granskad betyder att ingen har markerat rapporten som kontrollerad.</span>
                        </span>
                    @endif
                </summary>
                <section class="assignment-team-panel" aria-labelledby="assignment-team-heading">
                <p class="assignment-team-help">Ansvarig är den som utför besöket. En granskare kan kontrollera rapporten innan den skickas. Inte granskad betyder att ingen har markerat rapporten som kontrollerad.</p>

                <dl class="assignment-team-people">
                    <div>
                        <dt>Skapad av</dt>
                        <dd>{{ $assignment->user?->name ?? 'Okänd' }}</dd>
                    </div>
                    <div>
                        <dt>Ansvarig</dt>
                        <dd>{{ $assignment->assignedTo?->name ?? 'Inte tilldelad' }}</dd>
                    </div>
                    <div>
                        <dt>Granskare</dt>
                        <dd>{{ $assignment->reviewer?->name ?? 'Ingen vald' }}</dd>
                    </div>
                    @if ($isReviewed && $assignment->reviewed_at)
                        <div>
                            <dt>Granskad av</dt>
                            <dd>{{ $assignment->reviewedBy?->name ?? 'Teammedlem' }} <span>· {{ \Illuminate\Support\Carbon::parse($assignment->reviewed_at)->locale('sv')->translatedFormat('j F Y, H:i') }}</span></dd>
                        </div>
                    @endif
                </dl>

                @if ($canManageTeamAssignment)
                    <form class="assignment-team-form app-form" method="post" action="{{ route('assignments.team.update', $assignment, false) }}">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="assigned_to_user_id">Ansvarig medarbetare</label>
                            <select id="assigned_to_user_id" name="assigned_to_user_id" required aria-invalid="{{ $errors->has('assigned_to_user_id') ? 'true' : 'false' }}" @if ($errors->has('assigned_to_user_id')) aria-describedby="assigned-to-error" @endif>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('assigned_to_user_id', $assignment->assigned_to_user_id) === (string) $member->id)>{{ $member->name }} · {{ $member->email }}</option>
                                @endforeach
                            </select>
                            @error('assigned_to_user_id') <span class="field-error" id="assigned-to-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="reviewer_user_id">Granskare <span>valfritt</span></label>
                            <select id="reviewer_user_id" name="reviewer_user_id" aria-invalid="{{ $errors->has('reviewer_user_id') ? 'true' : 'false' }}" @if ($errors->has('reviewer_user_id')) aria-describedby="reviewer-error" @endif>
                                <option value="">Ingen särskild granskare</option>
                                @foreach ($teamMembers as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('reviewer_user_id', $assignment->reviewer_user_id) === (string) $member->id)>{{ $member->name }} · {{ $member->email }}</option>
                                @endforeach
                            </select>
                            @error('reviewer_user_id') <span class="field-error" id="reviewer-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="assignment-team-form-footer">
                            <p>Tilldelning skickar inte mejl och markerar inte rapporten som granskad.</p>
                            <button class="button button-outline-dark" type="submit">Spara ansvar</button>
                        </div>
                    </form>
                @endif

                <div class="assignment-team-actions">
                    @if ($canReviewAssignment)
                        <form method="post" action="{{ route('assignments.review', [$assignment, $latestReportVersion], false) }}" data-submit-feedback>
                            @csrf
                            <p>Granska den publicerade versionen {{ $latestReportVersion?->version_number ? 'V'.$latestReportVersion->version_number : '' }} innan den lämnas vidare.</p>
                            <button class="button button-lime" type="submit" data-loading-text="Sparar granskning…">Markera som granskad</button>
                        </form>
                    @elseif ($isReviewed)
                        <p class="assignment-team-confirmation">Den aktuella rapportversionen är granskad.</p>
                    @elseif ($hasUnpublishedDraftChanges && $latestReportVersion && $assignment->reviewer_user_id)
                        <p class="assignment-team-hint">Skapa en ny rapportversion innan den kan granskas.</p>
                    @endif

                    @if ($canHandoffAssignment)
                        <form method="post" action="{{ route('assignments.handoff', $assignment, false) }}" data-submit-feedback>
                            @csrf
                            <p>Överlämna uppdraget till företagets ägare. Det markeras inte som granskat.</p>
                            <button class="button button-outline-dark" type="submit" data-loading-text="Överlämnar…">Överlämna till ägaren</button>
                        </form>
                    @endif
                </div>
                </section>
            </details>
        @endif

        @if ($errors->any())
            <div class="form-status form-status-error" role="alert" tabindex="-1" data-focus-status @if ($errors->has('recipient_email') || $errors->has('message') || $errors->has('assigned_to_user_id') || $errors->has('reviewer_user_id') || $errors->has('label') || $errors->has('customer_comment') || $errors->has('internal_note')) data-focus-on-load="true" @endif>
                <strong>Kontrollera uppgifterna</strong>
                <ul>
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('assignments.partials.places-gallery')

        <section class="report-version-panel" aria-labelledby="report-version-heading">
            <h2 id="report-version-heading" class="sr-only">Aktuell rapportversion</h2>

            <div class="report-version-current">
                <div>
                    <strong>{{ $latestReportVersion ? 'Version '.$latestReportVersion->version_number : 'Ingen rapportversion ännu' }}</strong>
                    <span>
                        @if ($latestReportVersion?->first_sent_at)
                            Delad {{ $latestReportVersion->first_sent_at->locale('sv')->translatedFormat('j F Y') }}
                        @elseif ($latestReportVersion)
                            Skapad {{ $latestReportVersion->created_at->locale('sv')->translatedFormat('j F Y') }}
                        @else
                            Skapas när du publicerar bilderna.
                        @endif
                    </span>
                </div>
                @if ($latestReportVersion)
                    <span class="report-version-badge report-version-badge-{{ $assignmentIsMissingAfterPhoto ? 'missing' : $latestReportVersion->status }}">
                        @if ($assignmentIsMissingAfterPhoto)
                            {{ __('assignments.missing_after') }}
                        @elseif ($latestReportVersion->status === 'sent')
                            Skickad vidare
                        @elseif ($currentVersionIsReady)
                            Klar att dela
                        @else
                            Inte aktuell
                        @endif
                    </span>
                    <div class="report-version-actions" role="group" aria-label="Åtgärder för version {{ $latestReportVersion->version_number }}">
                        <a class="button button-outline-dark" href="{{ $reportUrl }}" target="_blank" rel="noopener">Öppna rapport <span aria-hidden="true">↗</span></a>
                        @if ($latestReportPdfUrl ?? null)
                            <a class="button button-outline-dark report-pdf-download" href="{{ $latestReportPdfUrl }}" aria-label="Ladda ner version {{ $latestReportVersion->version_number }} som PDF">
                                <x-icon name="download" />
                                <span>Ladda ner PDF</span>
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            @if ($hasUnpublishedDraftChanges)
                <div class="report-draft-notice" role="status">
                    <span class="report-draft-mark" aria-hidden="true">●</span>
                    <p>{{ $latestReportVersion ? 'Utkastet skiljer sig från den publicerade rapporten.' : 'Bilderna är sparade i utkastet.' }}</p>
                    @if ($canCreateReportVersion)
                        <form id="create-report-version" class="report-version-create" method="post" action="{{ route('assignments.versions.store', $assignment, false) }}" data-submit-feedback>
                            @csrf
                            <button class="button button-lime" type="submit" data-loading-text="Skapar version…">
                                {{ $latestReportVersion ? 'Skapa ny version' : 'Publicera rapport' }} <span aria-hidden="true">↗</span>
                            </button>
                        </form>
                    @endif
                </div>
            @elseif ($canCreateReportVersion && ! $latestReportVersion)
                <div class="report-draft-notice" role="status">
                    <span class="report-draft-mark" aria-hidden="true">●</span>
                    <p>Publicera när utkastet är klart. Äldre rapportversioner behålls.</p>
                    <form id="create-report-version" class="report-version-create" method="post" action="{{ route('assignments.versions.store', $assignment, false) }}" data-submit-feedback>
                        @csrf
                        <button class="button button-lime" type="submit" data-loading-text="Skapar version…">Publicera rapport <span aria-hidden="true">↗</span></button>
                    </form>
                </div>
            @endif
        </section>

        <section class="share-box" id="share-box" aria-labelledby="share-heading">
            <div class="share-box-top">
                <div class="share-box-copy">
                    <span class="workflow-icon"><x-icon name="share" /></span>
                    <div><p class="share-box-kicker">DELA RAPPORT</p><h2 id="share-heading">Skicka rapporten till kunden</h2><p>Skicka en länk till uppdragets aktuella innehåll.</p></div>
                </div>
                <a class="button button-outline-dark" href="{{ $reportUrl }}" target="_blank" rel="noopener"><x-icon name="eye" /> Öppna rapport</a>
            </div>

            @if (session('status'))
                <div class="form-status form-status-success share-feedback" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="form-status form-status-error share-feedback" role="alert">{{ session('error') }}</div>
            @endif

            @if ($hasUnpublishedDraftChanges && $latestReportVersion)
                <p class="share-version-note">Förhandsvisningen visar version {{ $latestReportVersion->version_number }}. Skicka rapporten skapar och skickar en version av uppdragets aktuella innehåll.</p>
            @endif

            <div class="share-project-preview" role="group" aria-label="Publicerad rapportversion som delas">
                <span class="share-project-icon" aria-hidden="true"><x-icon name="report" /></span>
                <div>
                    <strong>{{ $shareProjectName }}</strong>
                    <span>{{ $shareProjectAddress }} · {{ \Illuminate\Support\Carbon::parse($shareProjectDate)->locale('sv')->translatedFormat('j F Y') }}@if ($shareProjectTime) · kl. {{ $shareProjectTime }}@endif</span>
                    @if ($latestReportVersion)
                        <span>Version {{ $latestReportVersion->version_number }} ·
                            @if ($assignmentIsMissingAfterPhoto)
                                {{ __('assignments.missing_after_inline') }}
                            @elseif ($latestReportVersion->status === 'sent')
                                skickad tidigare
                            @elseif ($currentVersionIsReady)
                                klar att dela
                            @else
                                inte aktuell
                            @endif
                        </span>
                    @else
                        <span>Första rapportversionen skapas vid utskick.</span>
                    @endif
                </div>
                <span class="share-project-photo-count">{{ $sharePhotoCount }} {{ $sharePhotoCount === 1 ? 'bild' : 'bilder' }}</span>
            </div>

            @if ($canSendAssignmentEmail)
                <details class="assignment-email-disclosure" @if ($errors->has('recipient_email') || $errors->has('message') || $errors->has('confirm_incomplete_pairs') || filled(old('recipient_email')) || filled(old('message'))) open @endif>
                    <summary class="assignment-email-summary">Skicka rapporten <span>till kunden via e-post</span></summary>
                    <form class="assignment-email-form app-form" method="post" action="{{ route('assignments.email', $assignment, false) }}" data-submit-feedback>
                    @csrf
                    <div class="assignment-email-recipient">
                        <label for="recipient_email">Kundens e-postadress</label>
                        <input id="recipient_email" name="recipient_email" type="email" inputmode="email" autocomplete="email" required maxlength="254" value="{{ old('recipient_email', $customerEmail) }}" aria-invalid="{{ $errors->has('recipient_email') ? 'true' : 'false' }}" @if ($errors->has('recipient_email')) aria-describedby="recipient_email-error" @endif>
                        @error('recipient_email') <span class="field-error" id="recipient_email-error">{{ $message }}</span> @enderror
                    </div>

                    <details class="assignment-email-message" @if ($errors->has('message') || filled(old('message'))) open @endif>
                        <summary>Lägg till ett meddelande <span>valfritt</span></summary>
                        <label for="assignment_email_message">Meddelande till kunden</label>
                        <textarea id="assignment_email_message" name="message" rows="4" maxlength="1000" aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" @if ($errors->has('message')) aria-describedby="assignment_email_message-error" @endif>{{ old('message') }}</textarea>
                        @error('message') <span class="field-error" id="assignment_email_message-error">{{ $message }}</span> @enderror
                    </details>

                    <div class="assignment-email-footer">
                        <p>Rapporten innehåller kundens namn, adress, datum och bilderna som finns i uppdraget just nu.</p>
                        @if ($assignmentIsMissingAfterPhoto)
                            <div class="incomplete-report-warning" role="alert">
                                <p>{{ __('assignments.incomplete_warning') }}</p>
                                <label class="consent-label" for="confirm_incomplete_pairs">
                                    <input id="confirm_incomplete_pairs" type="checkbox" name="confirm_incomplete_pairs" value="1" @checked(old('confirm_incomplete_pairs')) aria-invalid="{{ $errors->has('confirm_incomplete_pairs') ? 'true' : 'false' }}" @if ($errors->has('confirm_incomplete_pairs')) aria-describedby="confirm_incomplete_pairs-error" @endif>
                                    <span>{{ __('assignments.incomplete_confirm') }}</span>
                                </label>
                                @error('confirm_incomplete_pairs') <span class="field-error" id="confirm_incomplete_pairs-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        <button class="button button-lime" type="submit" data-loading-text="Skickar rapporten…">Skicka rapporten <span aria-hidden="true">↗</span></button>
                    </div>
                    </form>
                </details>
            @else
                <div class="assignment-email-blocked" role="status">
                    @if (! auth()->user()->hasVerifiedEmail())
                        <strong>Bekräfta din e-postadress innan du skickar</strong>
                        <p>Vi behöver veta att avsändarens adress fungerar. Bekräfta den i rutan högst upp på sidan.</p>
                        <a href="#email-verification">Gå till e-postbekräftelse <span aria-hidden="true">↑</span></a>
                    @elseif (! $canManageJobs)
                        <strong>Utskick är pausade för kontot</strong>
                        <p>Befintliga rapporter går att öppna och dela med en kopierad länk. Återaktivera planen för att skicka rapporter via e-post.</p>
                        <a href="{{ route('settings.billing', absolute: false) }}">Visa plan och faktura <span aria-hidden="true">↗</span></a>
                    @elseif ($assignment->photos->isEmpty())
                        <strong>Lägg till minst en bild före utskick</strong>
                        <p>Kunden får rapporten med bilderna som finns i uppdraget.</p>
                        <a href="#assignment-upload-heading">Lägg till bilder <span aria-hidden="true">↓</span></a>
                    @elseif (($latestEmailAttempt['status'] ?? null) === 'sent')
                        <strong>Rapporten har skickats vidare.</strong>
                        <p>För att undvika dubbla mejl kan du skicka igen efter 60 sekunder. Uppdatera sidan då.</p>
                    @elseif (($latestEmailAttempt['status'] ?? null) === 'unknown')
                        <strong>Kontrollera det senaste utskicket</strong>
                        <p>Vi kan inte bekräfta om mejlet skickades. Kontrollera med mottagaren innan du gör ett nytt försök.</p>
                    @elseif (($latestEmailAttempt['status'] ?? null) === 'sending')
                        <strong>Ett utskick pågår</strong>
                        <p>Vänta tills det senaste försöket är klart innan du skickar igen.</p>
                    @else
                        <strong>Utskick kan inte startas just nu</strong>
                        <p>Vänta en stund innan du försöker igen. Era befintliga rapporter är kvar.</p>
                    @endif
                </div>
            @endif

            @if ($latestEmailAttempt)
                @php
                    $emailAttemptStatus = $latestEmailAttempt['status'];
                    $emailAttemptDate = match ($emailAttemptStatus) {
                        'failed' => $latestEmailAttempt['failedAt'],
                        'sent' => $latestEmailAttempt['sentAt'],
                        'unknown' => $latestEmailAttempt['unknownAt'],
                        default => $latestEmailAttempt['attemptedAt'],
                    } ?? $latestEmailAttempt['attemptedAt'];
                @endphp
                <div class="assignment-email-attempt assignment-email-attempt-{{ $emailAttemptStatus }}" role="status" aria-label="Senaste e-postförsöket">
                    <span class="assignment-email-attempt-mark" aria-hidden="true">{{ $emailAttemptStatus === 'failed' ? '!' : ($emailAttemptStatus === 'sending' ? '…' : ($emailAttemptStatus === 'unknown' ? '?' : '✓')) }}</span>
                    <div>
                        <strong>Senaste försök
                            @if ($emailAttemptStatus === 'sending')
                                · Skickar
                            @elseif ($emailAttemptStatus === 'failed')
                                · Misslyckades
                            @elseif ($emailAttemptStatus === 'unknown')
                                · Okänd status
                            @else
                                · Överlämnat till e-posttjänsten
                            @endif
                        </strong>
                        @if (! empty($latestEmailAttempt['recipientEmail']))
                            <span class="assignment-email-recipient-address">Mottagare: {{ $latestEmailAttempt['recipientEmail'] }}</span>
                        @endif
                        @if ($emailAttemptDate)
                            <time datetime="{{ $emailAttemptDate->toIso8601String() }}">{{ $emailAttemptDate->locale('sv')->translatedFormat('j F Y, H:i') }}</time>
                        @endif
                        @if ($emailAttemptStatus === 'sent')
                            <span>Det bekräftar inte att mejlet har levererats eller lästs.</span>
                        @elseif ($emailAttemptStatus === 'unknown')
                            <span>Det gick inte att bekräfta om mejlet skickades. Kontrollera med mottagaren innan du skickar igen.</span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="share-link-tools">
                <div class="share-link-tools-heading"><strong>Vill du skicka länken själv?</strong><p>Kopiera den och dela i valfri kanal.</p></div>
                <div class="share-link-row">
                    <label class="sr-only" for="share-link">Länk till rapporten</label>
                    <input id="share-link" type="text" readonly value="{{ $reportUrl }}" aria-describedby="share-copy-feedback">
                    <button class="button button-outline-dark" type="button" hidden data-copy-url="{{ $reportUrl }}" data-copy-feedback="share-copy-feedback">Kopiera länk</button>
                </div>
                <p id="share-copy-feedback" class="share-copy-feedback" aria-live="polite"></p>
                <noscript><p class="share-copy-noscript">Markera länken ovan och kopiera den manuellt.</p></noscript>
                <form method="post" action="{{ route('assignments.share', $assignment, false) }}" hidden data-record-copy>
                    @csrf
                </form>
            </div>
        </section>



        @if (auth()->user()->isOwner() && $customerResponses !== [])
            <details class="customer-response-history">
                <summary>Senaste svar via rapportlänken <span>{{ count($customerResponses) }}</span></summary>
                <p>Svaren skickas utan inloggning. Avsändarens identitet är därför inte verifierad.</p>
                <ol>
                    @foreach ($customerResponses as $response)
                        <li>
                            <div>
                                <strong>{{ $response['type'] === 'received' ? 'Mottagandet har bekräftats' : 'Kommentar' }}</strong>
                                <span>
                                    @if ($response['type'] === 'comment' && $response['reportUrl'])
                                        <a href="{{ $response['reportUrl'] }}">
                                            Version {{ $response['versionNumber'] }} · {{ $response['placeName'] }} · {{ $response['pairLabel'] ?: 'Bildpar '.((int) ($response['pairIndex'] ?? 0) + 1) }}
                                        </a>
                                    @elseif ($response['versionNumber'])
                                        Version {{ $response['versionNumber'] }}
                                    @endif
                                    · {{ $response['createdAt']->locale('sv')->translatedFormat('j F Y, H:i') }}
                                </span>
                            </div>
                            @if ($response['message'])
                                <p>{{ $response['message'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif

        @if ($canManageJobs)
            <section class="assignment-upload-panel" aria-labelledby="assignment-upload-heading">
                <div class="assignment-section-heading">
                    <div><p class="section-kicker">Lägg till bilder</p><h2 id="assignment-upload-heading">Komplettera rapporten</h2></div>
                </div>
                <form class="app-form assignment-form assignment-upload-form" method="post" action="{{ route('assignments.photos.store', $assignment, false) }}" enctype="multipart/form-data" data-submit-feedback data-photo-upload-mode="new-pairs">
                    @csrf
                    <div class="form-status form-status-error" role="alert" tabindex="-1" data-form-errors hidden></div>
                    <div class="assignment-upload-place">
                        <label for="upload_place_name">Plats för bilderna</label>
                        <input id="upload_place_name" name="place_name" type="text" list="upload-place-suggestions" maxlength="120" value="{{ old('place_name', $placeName) }}" required aria-describedby="upload-place-help">
                        <datalist id="upload-place-suggestions">
                            @foreach ($assignment->places as $place)
                                <option value="{{ $place->name }}"></option>
                            @endforeach
                            <option value="Kök"></option>
                            <option value="Badrum"></option>
                            <option value="Vardagsrum"></option>
                            <option value="Övrigt"></option>
                        </datalist>
                        <span class="field-help" id="upload-place-help">Samma namn lägger till i platsen. Nytt namn skapar en ny plats.</span>
                    </div>
                    <div class="assignment-photo-section">
                        @include('assignments.partials.photo-input', [
                            'inputId' => 'before_photos',
                            'inputName' => 'before_photos[]',
                            'uploadIdName' => 'before_upload_ids[]',
                            'label' => 'Välj förebilder',
                        ])
                    </div>
                    <div class="assignment-photo-section">
                        @include('assignments.partials.photo-input', [
                            'inputId' => 'after_photos',
                            'inputName' => 'after_photos[]',
                            'uploadIdName' => 'after_upload_ids[]',
                            'label' => 'Välj efterbilder',
                        ])
                    </div>
                    <div class="assignment-form-footer">
                        <p>Här skapas nya bildpar: före 1 paras med efter 1. Fyll i en tom ruta i ett befintligt par ovan.</p>
                        <button class="button button-lime" type="submit" data-upload-submit data-loading-text="Laddar upp…">Ladda upp nya bildpar</button>
                    </div>
                    <div class="photo-upload-progress assignment-upload-progress" data-upload-progress-list role="status" aria-live="polite" hidden></div>
                    <p class="photo-upload-feedback assignment-upload-feedback" data-upload-feedback role="alert" tabindex="-1" hidden></p>
                </form>
            </section>
        @endif

        @if ($canArchiveAssignment && ! $assignment->isArchived())
            <dialog class="assignment-dialog" id="archive-assignment-dialog" aria-labelledby="archive-assignment-title">
                <form method="post" action="{{ route('assignments.archive', $assignment, false) }}">
                    @csrf
                    <h2 id="archive-assignment-title">Arkivera uppdraget?</h2>
                    <p>{{ $assignment->customer_name }} <span aria-hidden="true">·</span> {{ $assignment->address }}</p>
                    <p>{{ \App\Models\Assignment::ARCHIVE_SHARE_LINK_NOTICE }}</p>
                    <div class="assignment-dialog-actions">
                        <button class="button button-outline-dark" type="submit" formmethod="dialog" data-close-dialog data-dialog-cancel autofocus>Avbryt</button>
                        <button class="button button-dark" type="submit">Arkivera</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canDeleteAssignment)
            <div class="assignment-delete">
                <p>Radering tar bort bilderna, rapportversionerna, PDF:erna och delningslänkarna. Det går inte att ångra.</p>
                <button class="button button-danger" type="button" data-open-dialog="delete-assignment-dialog">Radera uppdrag</button>
            </div>
            <dialog class="assignment-dialog" id="delete-assignment-dialog" aria-labelledby="delete-assignment-title">
                <form method="post" action="{{ route('assignments.destroy', $assignment, false) }}">
                    @csrf
                    @method('DELETE')
                    <h2 id="delete-assignment-title">Radera uppdraget?</h2>
                    <p>{{ $assignment->customer_name }} <span aria-hidden="true">·</span> {{ $assignment->address }}</p>
                    <p>Bilder, rapportversioner, PDF:er och delningslänkar tas bort permanent.</p>
                    <div class="assignment-dialog-actions">
                        <button class="button button-outline-dark" type="submit" formmethod="dialog" data-close-dialog data-dialog-cancel autofocus>Avbryt</button>
                        <button class="button button-danger-solid" type="submit">Radera permanent</button>
                    </div>
                </form>
            </dialog>
        @endif
    </section>
@endsection
