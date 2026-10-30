<x-blsc-guide::layout title="Contributor guide" :wide="true">
    <h1>BLSC contributor guide</h1>
    <p class="lead">Pick a feature marked <strong>Ready</strong>. Start with Level 1. Each feature explains the ideas first, then guides you step by step. The guide checks your code, so the steps tick off as you work.</p>
    <div class="notice ok">New here? Read <a href="{{ route('blsc-guide.start') }}">Start here</a> first. It tells you why this project exists, what we build and how. Then read <a href="{{ route('blsc-guide.git') }}">Git habits</a>.</div>

    <div class="legend">
        @foreach (TruLaravelClub\BlscGuide\Status::cases() as $status)
            <x-blsc-guide::status :status="$status" />
        @endforeach
    </div>

    @foreach ($map->bySection() as $section => $features)
        <h2>{{ TruLaravelClub\BlscGuide\Section::from($section)->label() }}</h2>
        <div class="grid">
            @foreach ($features as $feature)
                @php($status = $map->status($feature))
                <a class="card" href="{{ route('blsc-guide.show', $feature->key()) }}">
                    <div class="card-title">{{ $feature->name() }}</div>
                    <x-blsc-guide::status :status="$status" />
                    <div class="meta">
                        Level {{ $feature->difficulty() }}
                        @if ($feature->isPlanned())
                            · {{ $map->completedSteps($feature) }}/{{ $map->checkableSteps($feature) }} steps
                        @endif
                        @if ($map->isMarkedDone($feature))
                            · Marked as done
                        @endif
                    </div>
                    @if ($status === TruLaravelClub\BlscGuide\Status::Blocked)
                        <div class="meta">Needs {{ collect($map->blockers($feature))->map->name()->join(', ', ' and ') }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach
</x-blsc-guide::layout>
