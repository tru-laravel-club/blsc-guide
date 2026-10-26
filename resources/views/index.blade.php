<x-blsc-guide::layout title="Contributor guide">
    <h1>BLSC contributor guide</h1>
    <p class="muted">Pick a feature marked <strong>Ready</strong>. Start with Level 1. The guide checks your code, so the steps tick themselves off as you work.</p>

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
                            · {{ $map->completedSteps($feature) }}/{{ count($feature->stepList()) }} steps
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
