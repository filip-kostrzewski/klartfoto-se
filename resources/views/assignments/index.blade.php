@extends('layouts.app')

@section('title', 'Uppdrag · Klartfoto')
@section('page-heading', 'Uppdrag')

@section('content')
    @php
        $hasAssignmentFilters = $filters['q'] !== '' || $filters['status'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['arkiv'] !== '';
        $showingArchived = $filters['arkiv'] === 'arkiverade';
        $archiveOnly = $showingArchived && $filters['q'] === '' && $filters['status'] === '' && $filters['from'] === '' && $filters['to'] === '';
    @endphp
    <section class="dashboard-page">
        <div class="app-page-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span> {{ $companyName }}</p>
                <h1>Uppdrag</h1>
                <p class="auth-lead">Samla bilder och dela rapporten efter varje besök.</p>
            </div>
            @if ($canManageJobs)
                <a class="button button-lime" href="{{ route('assignments.create', absolute: false) }}"><x-icon name="camera" /> Nytt uppdrag</a>
            @endif
        </div>

        @if ($isFeedbackPilot)
            <div class="trial-strip"><span class="trial-strip-icon" aria-hidden="true">✓</span><p><strong>Feedbackpilot</strong><span>Feedbackpilot – Team gratis till 31 december 2026</span></p></div>
        @elseif ($onTrial && $trialEndsAt)
            <div class="trial-strip"><span class="trial-strip-icon" aria-hidden="true">✓</span><p><strong>Provperiod</strong><span>Gratis till {{ $trialEndsAt->locale('sv')->translatedFormat('j F Y') }}. Inget kort behövs. Därefter har ni ingen aktiv åtkomst till nya uppdrag. Ni kan fortfarande öppna befintliga rapporter.</span></p></div>
        @elseif (! $canManageJobs)
            <div class="form-status form-status-error" role="status">Ingen aktiv åtkomst till nya uppdrag. Ni kan fortfarande öppna befintliga rapporter.</div>
        @endif

        @if ($assignmentTotal > 0 || $hasAssignmentFilters)
            <div class="dashboard-stats @isset($readyToSendCount) dashboard-stats-ready @endisset" aria-label="Översikt">
                <div class="dashboard-stat"><span>Uppdrag i urvalet</span><strong>{{ $assignmentTotal }}</strong><x-icon name="report" /></div>
                <div class="dashboard-stat"><span>Bilder i urvalet</span><strong>{{ $photoTotal }}</strong><x-icon name="camera" /></div>
                @isset($readyToSendCount)
                    <div class="dashboard-stat dashboard-stat-ready"><span>Redo att skicka totalt</span><strong>{{ $readyToSendCount }}</strong><x-icon name="check" /></div>
                @endisset
            </div>
        @endif

        @if ($showOnboarding)
            @include('assignments.partials.onboarding')
        @elseif ($showOnboardingRestore)
            <form class="onboarding-restore" method="post" action="{{ route('onboarding.restore', absolute: false) }}">
                @csrf
                <button type="submit">Visa Kom igång</button>
            </form>
        @endif

        <section class="assignment-list-section" aria-labelledby="assignment-list-heading">
            <div class="assignment-list-heading">
                <div><h2 id="assignment-list-heading">{{ $showingArchived ? 'Arkiverade uppdrag' : 'Era uppdrag' }}</h2><p>{{ $assignments->isEmpty() ? ($showingArchived ? 'Arkiverade kundbesök visas här.' : 'Era sparade kundbesök visas här.') : 'Sök bland kundbesök och öppna rapporten ni behöver.' }}</p></div>
                @if ($canManageJobs && $assignments->isNotEmpty())
                    <a class="text-link" href="{{ route('assignments.create', absolute: false) }}">Lägg till</a>
                @endif
            </div>

            <div class="assignment-filters-panel">
                <form class="assignment-filters" method="get" action="{{ route('assignments.index', absolute: false) }}" role="search" aria-label="Sök och filtrera uppdrag">
                    <div class="assignment-filter-search">
                        <label for="assignment-search">Sök kund eller adress</label>
                        <input id="assignment-search" name="q" type="search" value="{{ $filters['q'] }}" maxlength="120" autocomplete="off" placeholder="Namn eller adress">
                    </div>
                    <div class="assignment-filter-status">
                        <label for="assignment-status">Rapportstatus</label>
                        <select id="assignment-status" name="status">
                            <option value="">Alla statusar</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Utkast</option>
                            <option value="ready" @selected($filters['status'] === 'ready')>Klar att skicka</option>
                            <option value="sent" @selected($filters['status'] === 'sent')>Delad</option>
                        </select>
                    </div>
                    <div class="assignment-filter-archive">
                        <label for="assignment-archive">Lista</label>
                        <select id="assignment-archive" name="arkiv">
                            <option value="">Aktiva</option>
                            <option value="arkiverade" @selected($showingArchived)>Arkiverade</option>
                        </select>
                    </div>
                    <div class="assignment-filter-date">
                        <label for="assignment-from">Från datum</label>
                        <x-swedish-date id="assignment-from" name="from" :value="$dateInputs['from']" :invalid="$errors->has('from')" error-id="assignment-from-error" />
                        @error('from') <span class="field-error" id="assignment-from-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="assignment-filter-date">
                        <label for="assignment-to">Till datum</label>
                        <x-swedish-date id="assignment-to" name="to" :value="$dateInputs['to']" :invalid="$errors->has('to')" error-id="assignment-to-error" />
                        @error('to') <span class="field-error" id="assignment-to-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="assignment-filter-actions">
                        <button class="button button-dark" type="submit">Visa uppdrag</button>
                        @if ($hasAssignmentFilters)
                            <a class="assignment-filter-reset" href="{{ route('assignments.index', absolute: false) }}">Rensa filter</a>
                        @endif
                    </div>
                </form>
            </div>

            @if ($assignments->isEmpty())
                @if ($archiveOnly)
                    <div class="assignment-no-results" role="status">
                        <span class="empty-state-icon"><x-icon name="report" /></span>
                        <div><h3>Inga arkiverade uppdrag</h3><p>Arkiverade uppdrag visas här. Rapportlänken fortsätter att fungera tills uppdraget raderas.</p></div>
                        <a class="button button-outline-dark" href="{{ route('assignments.index', absolute: false) }}">Visa aktiva</a>
                    </div>
                @elseif ($hasAssignmentFilters)
                    <div class="assignment-no-results" role="status">
                        <span class="empty-state-icon"><x-icon name="search" /></span>
                        <div><h3>Inga uppdrag matchar urvalet</h3><p>Prova ett annat namn, datum eller rapportstatus.</p></div>
                        <a class="button button-outline-dark" href="{{ route('assignments.index', absolute: false) }}">Rensa filter</a>
                    </div>
                @elseif ($archivedAssignmentCount > 0)
                    <div class="assignment-no-results" role="status">
                        <span class="empty-state-icon"><x-icon name="report" /></span>
                        <div><h3>Inga aktiva uppdrag</h3><p>Arkiverade uppdrag visas under filtret Arkiverade.</p></div>
                        <a class="button button-outline-dark" href="{{ route('assignments.index', ['arkiv' => 'arkiverade'], false) }}">Visa arkiverade</a>
                    </div>
                @else
                    <div class="dashboard-empty-state">
                        <div class="empty-state-image">
                            <img src="{{ asset('images/cleaning-detail-small.webp') }}?v={{ config('klartfoto.demo_asset_version') }}" width="768" height="512" alt="" loading="lazy" decoding="async">
                            <span>AI-demo · inte kundarbete</span>
                        </div>
                        <div class="empty-state-copy">
                            <span class="empty-state-icon"><x-icon name="camera" /></span>
                            <h3>Här börjar nästa rapport</h3>
                            <p>Lägg till kundens namn, adress och datum. Ladda sedan upp bilder före och efter arbetet.</p>
                            @if ($canManageJobs)
                                <a class="button button-dark" href="{{ route('assignments.create', absolute: false) }}">Skapa ert första uppdrag</a>
                            @endif
                        </div>
                    </div>
                @endif
            @else
                <ul class="job-list">
                    @foreach ($assignments as $assignment)
                        <li>
                            <a href="{{ route('assignments.show', $assignment, false) }}">
                                <span class="job-list-icon" aria-hidden="true"><x-icon name="report" /></span>
                                <span class="job-list-copy">
                                    <strong>{{ $assignment->customer_name }}</strong>
                                    <span>{{ $assignment->address }}</span>
                                </span>
                                <span class="job-list-meta">
                                    <span>{{ $assignment->performedLabel() }}</span>
                                    <span>{{ $assignment->photos_count }} {{ $assignment->photos_count === 1 ? 'bild' : 'bilder' }}</span>
                                <span class="job-status {{ $assignment->isArchived() ? 'job-status-archived' : 'job-status-'.$assignment->status }}">
                                    @if ($assignment->isArchived())
                                        Arkiverat
                                    @elseif ($assignment->status === 'ready')
                                        Klar att skicka
                                    @elseif ($assignment->status === 'sent')
                                        Skickad
                                    @else
                                        Utkast
                                    @endif
                                </span>
                                </span>
                                @if ($assignment->isArchived())
                                    <span class="job-archive-note">{{ \App\Models\Assignment::ARCHIVE_SHARE_LINK_NOTICE }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($assignments->hasPages())
                    <nav class="assignment-pagination" aria-label="Sidnavigering">
                        {{ $assignments->onEachSide(1)->links() }}
                    </nav>
                @endif
            @endif
        </section>
    </section>
@endsection
