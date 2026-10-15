<section class="onboarding" aria-label="Kom igång">
    <h2>Kom igång</h2>
    <ol class="onboarding-list">
        @foreach ($onboardingSteps as $step)
            <li>
                @if ($step['done'])
                    <span>{{ $step['label'] }}</span>
                    <span>Klar</span>
                @elseif ($step['copyUrl'])
                    <form method="post" action="{{ $step['shareAction'] }}">
                        @csrf
                        <input type="hidden" name="checklist" value="1">
                        <button type="submit" data-copy-report="{{ $step['copyUrl'] }}">{{ $step['label'] }}</button>
                    </form>
                @elseif ($step['url'])
                    <a href="{{ $step['url'] }}">{{ $step['label'] }}</a>
                @else
                    <span>{{ $step['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
    <form method="post" action="{{ route('onboarding.dismiss', absolute: false) }}">
        @csrf
        <button class="button button-outline-dark" type="submit">Dölj</button>
    </form>
</section>
