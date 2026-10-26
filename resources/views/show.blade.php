@php($status = $map->status($feature))
@php($blockers = $map->blockers($feature))

<x-blsc-guide::layout :title="$feature->name()">
    <a class="back" href="{{ route('blsc-guide.index') }}">← All features</a>

    <h1>{{ $feature->name() }}</h1>
    <p><x-blsc-guide::status :status="$status" /> <span class="muted">· Level {{ $feature->difficulty() }} · {{ $feature->sectionName()->label() }}</span></p>
    <p>{{ $feature->description() }}</p>

    @if ($feature->concepts() !== [])
        <div class="tags">
            @foreach ($feature->concepts() as $concept)
                <span class="tag">{{ $concept }}</span>
            @endforeach
        </div>
    @endif

    @if ($blockers !== [])
        <div class="notice">
            <strong>This feature is blocked.</strong> It needs:
            <ul>
                @foreach ($blockers as $blocker)
                    <li>
                        <a href="{{ route('blsc-guide.show', $blocker->key()) }}">{{ $blocker->name() }}</a>
                        (<x-blsc-guide::status :status="$map->status($blocker)" />)
                    </li>
                @endforeach
            </ul>
            @php($readyBlockers = array_filter($blockers, fn ($b) => $map->status($b) === TruLaravelClub\BlscGuide\Status::Ready))
            @if ($readyBlockers !== [])
                You can build {{ collect($readyBlockers)->map->name()->join(', ', ' or ') }} now. That moves this feature closer.
            @endif
        </div>
    @elseif ($status === TruLaravelClub\BlscGuide\Status::Done)
        <div class="notice ok">This feature is done.</div>
    @endif

    @if ($feature->issueNumber())
        <p><a href="{{ $repository }}/issues/{{ $feature->issueNumber() }}">GitHub issue #{{ $feature->issueNumber() }}</a>: claim it there before you start.</p>
    @endif

    @if ($unlocks = $map->unlocks($feature))
        <p class="muted">Finishing this unlocks: {{ collect($unlocks)->map->name()->join(', ', ' and ') }}.</p>
    @endif

    <h2>Steps</h2>

    @if (! $feature->isPlanned())
        <p class="muted">A lead has not written the steps for this feature yet.</p>
    @else
        @if ($status !== TruLaravelClub\BlscGuide\Status::Done)
            <p>Start with:</p>
            <pre>php artisan guide:start {{ $feature->key() }}</pre>
        @endif

        <ol class="steps">
            @foreach ($feature->stepList() as $index => $step)
                <li class="card step">
                    <span class="mark {{ $step->passes() ? 'status-done' : 'muted' }}">{{ $step->passes() ? '✔' : $index + 1 }}</span>
                    <div>
                        <div class="card-title">{{ $step->title() }}</div>
                        @if ($step->command())
                            <pre>{{ $step->command() }}</pre>
                        @endif
                        @if ($step->explanation())
                            <p class="meta">{{ $step->explanation() }}</p>
                        @endif
                        @if ($step->snippet())
                            <pre>{{ $step->snippet() }}</pre>
                        @endif
                        @if ($step->docsUrl())
                            <p class="meta"><a href="{{ $step->docsUrl() }}">Read the docs</a></p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-blsc-guide::layout>
