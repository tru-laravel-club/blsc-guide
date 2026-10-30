@php($status = $map->status($feature))
@php($blockers = $map->blockers($feature))
@php($branch = 'feature/'.$feature->key())
{{-- A marked feature is merged into main. Its page then teaches by reading, not by building. --}}
@php($merged = $map->isMarkedDone($feature))

<x-blsc-guide::layout :title="$feature->name()">
    <h1>{{ $feature->name() }}</h1>
    <p><x-blsc-guide::status :status="$status" /> <span class="muted">· Level {{ $feature->difficulty() }} · {{ $feature->sectionName()->label() }}</span></p>
    <p class="lead">{{ $feature->description() }}</p>

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
    @elseif ($map->isMarkedDone($feature))
        <div class="notice ok">This feature was marked as done on {{ $map->markedOn($feature) }}.</div>
    @elseif ($status === TruLaravelClub\BlscGuide\Status::Done)
        <div class="notice ok">This feature is done.</div>
    @endif

    @if ($feature->issueNumber())
        <p><a href="{{ $repository }}/issues/{{ $feature->issueNumber() }}">GitHub issue #{{ $feature->issueNumber() }}</a>: ask for it there before you start.</p>
    @endif

    @if ($unlocks = $map->unlocks($feature))
        <p class="meta">Finishing this unlocks: {{ collect($unlocks)->map->name()->join(', ', ' and ') }}.</p>
    @endif

    @if ($feature->introduction())
        <h2>How it works</h2>
        <div class="prose">
            <x-blsc-guide::prose :text="$feature->introduction()" />
        </div>
    @endif

    @if ($feature->links() !== [])
        <h2>Learn first</h2>
        <p class="muted">Watch or read these before you start. They explain the ideas that this feature uses.</p>
        <x-blsc-guide::links :links="$feature->links()" />
    @endif

    @if (! $feature->isPlanned())
        <h2>Build it</h2>
        <p class="muted">A maintainer has not written the steps for this feature yet.</p>
    @elseif ($merged)
        <h2>How it was built</h2>
        <p class="muted">This feature is already in <code>main</code>. You do not build it. Read each step and open the files that it names. Answer the questions for yourself. You use these patterns in the features that you build.</p>

        <ol class="steps">
            @foreach ($feature->stepList() as $index => $step)
                <li class="card step">
                    <div class="step-head">
                        <span class="step-number">{{ $index + 1 }}</span>
                        <span class="step-title">{{ $step->title() }}</span>
                    </div>
                    @if ($step->explanation())
                        <div class="prose">
                            <x-blsc-guide::prose :text="$step->explanation()" />
                        </div>
                    @endif
                    @if ($step->command() || $step->snippet())
                        <details class="reveal">
                            <summary>Show the code</summary>
                            @if ($step->command())
                                <pre>{{ $step->command() }}</pre>
                            @endif
                            @if ($step->snippet())
                                <pre>{{ $step->snippet() }}</pre>
                            @endif
                        </details>
                    @endif
                    @if ($step->question())
                        <div class="box think">
                            <span class="box-label">Think first</span>
                            <x-blsc-guide::prose :text="$step->question()" />
                        </div>
                    @endif
                    @if ($step->tryItText())
                        <div class="box try">
                            <span class="box-label">Try it</span>
                            <x-blsc-guide::prose :text="$step->tryItText()" />
                        </div>
                    @endif
                    <x-blsc-guide::links :links="$step->links()" />
                </li>
            @endforeach
        </ol>

        <h2>The done mark</h2>
        <div class="card mark-tools">
            <p class="meta">This feature counts as done for every contributor, and the features that need it are unlocked. Remove the mark only if it was a mistake.</p>
            <form method="POST" action="{{ route('blsc-guide.undone', $feature->key()) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="button secondary">Remove the mark</button>
            </form>
        </div>
    @else
        <h2>Before you start</h2>
        <div class="prose">
            <p>Start every feature from the latest <code>main</code>. Then other contributors' work is already in your code, and your pull request has fewer conflicts.</p>
            <p>Make one branch for this feature. If you need to stop, your <code>main</code> stays clean for the next feature.</p>
        </div>
        <div class="box git">
            <span class="box-label">Git</span>
            <pre>git switch main
git pull upstream main
composer install
php artisan migrate
git switch -c {{ $branch }}</pre>
        </div>
        <p class="meta"><a href="{{ route('blsc-guide.git') }}">Why these commands?</a></p>

        <h2>Build it</h2>
        <p class="muted">Read each step. Answer its question for yourself before you write code. Open a hint only when you are stuck. Open the answer last, and make sure that you understand it.</p>

        <ol class="steps">
            @foreach ($feature->stepList() as $index => $step)
                <li class="card step">
                    <div class="step-head">
                        <span @class(['step-number', 'done' => $step->passes()])>{{ $step->passes() ? '✔' : $index + 1 }}</span>
                        <span class="step-title">{{ $step->title() }}</span>
                    </div>

                    @if ($step->explanation())
                        <div class="prose">
                            <x-blsc-guide::prose :text="$step->explanation()" />
                        </div>
                    @endif

                    {{-- Without hints, the command is the instruction itself, so it follows the text. --}}
                    @if ($step->hints() === [])
                        @if ($step->command())
                            <pre>{{ $step->command() }}</pre>
                        @endif
                        @if ($step->snippet())
                            <pre>{{ $step->snippet() }}</pre>
                        @endif
                    @endif

                    @if ($step->question())
                        <div class="box think">
                            <span class="box-label">Think first</span>
                            <x-blsc-guide::prose :text="$step->question()" />
                        </div>
                    @endif

                    @if ($step->hints() !== [])
                        <div class="reveals">
                            @foreach ($step->hints() as $number => $hint)
                                <details class="reveal">
                                    <summary>Hint {{ $number + 1 }}</summary>
                                    <x-blsc-guide::prose :text="$hint" />
                                </details>
                            @endforeach

                            @if ($step->command() || $step->snippet())
                                <details class="reveal">
                                    <summary>Show the answer</summary>
                                    @if ($step->command())
                                        <pre>{{ $step->command() }}</pre>
                                    @endif
                                    @if ($step->snippet())
                                        <pre>{{ $step->snippet() }}</pre>
                                    @endif
                                </details>
                            @endif
                        </div>
                    @endif

                    @if ($step->tryItText())
                        <div class="box try">
                            <span class="box-label">Try it</span>
                            <x-blsc-guide::prose :text="$step->tryItText()" />
                        </div>
                    @endif

                    @if ($step->commitMessage())
                        <div class="box git">
                            <span class="box-label">Commit your work</span>
                            <p>Look at what changed. Then commit only this step.</p>
                            <pre>git status
git diff
git add .
git commit -m "{{ $step->commitMessage() }}"</pre>
                        </div>
                    @endif

                    <x-blsc-guide::links :links="$step->links()" />
                </li>
            @endforeach
        </ol>

        <h2>Finish</h2>

        <h3>1. Mark the feature as done</h3>
        <div class="prose">
            <p>Your test result stays on your computer. Mark the feature as done, so other contributors see it as done after your pull request merges.</p>
        </div>
        <div class="card mark-tools">
            <form method="POST" action="{{ route('blsc-guide.done', $feature->key()) }}">
                @csrf
                <button type="submit" class="button">Mark as done</button>
            </form>
            <p class="meta">Or run <code>php artisan guide:done {{ $feature->key() }}</code>. Both create the file <code>{{ $progressFile }}</code>.</p>
        </div>
        <div class="box git">
            <span class="box-label">Commit your work</span>
            <pre>git add {{ $progressFile }}
git commit -m "Mark {{ $feature->key() }} as done"</pre>
        </div>

        <h3>2. Update your branch and push it</h3>
        <div class="prose">
            <p>Other pull requests merged while you worked. Bring them into your branch, and run the checks again. Then you find conflicts and broken tests before the reviewer does.</p>
        </div>
        <div class="box git">
            <span class="box-label">Git</span>
            <pre>git fetch upstream
git merge upstream/main
composer ci:check
git push -u origin {{ $branch }}</pre>
        </div>

        <h3>3. Open the pull request</h3>
        <div class="prose">
            <p>On GitHub, open a pull request from <code>{{ $branch }}</code> on your fork into <code>main</code>. Fill in the template, and add a screenshot of each page that you changed.</p>
            @if ($feature->questions() !== [])
                <p>Answer these questions in the pull request, in your own words. Your reviewer reads the answers.</p>
            @endif
        </div>
        @if ($feature->questions() !== [])
            <ol class="list">
                @foreach ($feature->questions() as $question)
                    <li><x-blsc-guide::prose :text="$question" class="inline" /></li>
                @endforeach
            </ol>
        @endif

        <h3>4. After the pull request merges</h3>
        <div class="prose">
            <p>Get the merged work, and delete the branch. Your next feature then starts clean.</p>
            <p>The maintainer squashed your commits into one commit on <code>main</code>. So Git does not see your branch as merged, and you must use <code>-D</code> to delete it.</p>
        </div>
        <div class="box git">
            <span class="box-label">Git</span>
            <pre>git switch main
git pull upstream main
git push origin main
git branch -D {{ $branch }}</pre>
        </div>
    @endif
</x-blsc-guide::layout>
