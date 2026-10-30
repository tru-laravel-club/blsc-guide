<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Status;

class GuideCommand extends Command
{
    protected $signature = 'guide
        {feature? : The feature key, such as big-little-scientists}
        {--answers : Also show the commands and code that hints hide}';

    protected $description = 'Show the BLSC feature map, or the steps of one feature';

    public function handle(FeatureMap $map): int
    {
        $key = $this->argument('feature');

        if (! is_string($key)) {
            $this->showMap($map);

            return self::SUCCESS;
        }

        $feature = $map->find($key);

        if ($feature === null) {
            $this->components->error("No feature has the key [{$key}]. Run `php artisan guide` to see them all.");

            return self::FAILURE;
        }

        $this->showFeature($map, $feature);

        return self::SUCCESS;
    }

    private function showMap(FeatureMap $map): void
    {
        foreach ($map->bySection() as $section => $features) {
            $this->newLine();
            $this->line('<options=bold>'.Section::from($section)->label().'</>');

            foreach ($features as $feature) {
                $status = $map->status($feature);
                $detail = "Level {$feature->difficulty()} · {$status->label()}";

                if ($status === Status::Blocked) {
                    $detail .= ' · needs '.implode(', ', array_map(fn (Feature $f) => $f->key(), $map->blockers($feature)));
                }

                $this->components->twoColumnDetail(
                    "{$status->symbol()} {$feature->key()}",
                    $detail,
                );
            }
        }

        $this->newLine();
        $this->line('Run <comment>php artisan guide {feature}</comment> to see the steps, or open <comment>'.route('blsc-guide.index').'</comment>.');
    }

    private function showFeature(FeatureMap $map, Feature $feature): void
    {
        $status = $map->status($feature);

        $this->newLine();
        $this->line("<options=bold>{$feature->name()}</> ({$this->colour($status)} {$status->label()}, Level {$feature->difficulty()})");
        $this->line($feature->description());

        if ($feature->concepts() !== []) {
            $this->line('<fg=gray>You will learn: '.implode(', ', $feature->concepts()).'</>');
        }

        foreach ($map->blockers($feature) as $blocker) {
            $this->components->warn("Needs [{$blocker->key()}] first. It is {$map->status($blocker)->label()}.");
        }

        if (! $feature->isPlanned()) {
            $this->components->info('A maintainer has not written the steps for this feature yet.');

            return;
        }

        if ($feature->introduction() !== null) {
            $this->newLine();
            $this->line(' <options=bold>How it works</>');

            foreach (explode("\n", $feature->introduction()) as $line) {
                $this->line('   '.OutputFormatter::escape($line));
            }
        }

        if ($feature->links() !== []) {
            $this->newLine();
            $this->line(' <options=bold>Learn first</>');

            foreach ($feature->links() as $link) {
                $this->line('   '.($link['type'] === 'video' ? 'Watch' : 'Read').': '.OutputFormatter::escape($link['title']).' <fg=gray>'.$link['url'].'</>');
            }
        }

        $this->newLine();
        $this->line(' <options=bold>Before you start</> <fg=gray>(see /guide/git for why)</>');
        $this->line('   <comment>git switch main && git pull upstream main</comment>');
        $this->line("   <comment>git switch -c feature/{$feature->key()}</comment>");

        foreach ($feature->stepList() as $index => $step) {
            $mark = match (true) {
                ! $step->isCheckable() => '<fg=gray>·</>',
                $step->passes() => '<fg=green>✔</>',
                default => '<fg=yellow>○</>',
            };

            $this->newLine();
            $this->line(" {$mark} <options=bold>".($index + 1).". {$step->title()}</>");

            if ($step->explanation() !== null) {
                foreach (explode("\n", $step->explanation()) as $line) {
                    $this->line('     '.OutputFormatter::escape($line));
                }
            }

            foreach ($step->links() as $link) {
                $this->line('     <fg=gray>'.($link['type'] === 'video' ? 'Watch' : 'Read').':</> '.OutputFormatter::escape($link['title']).' <fg=gray>'.$link['url'].'</>');
            }

            if ($step->question() !== null) {
                $this->line('     <fg=blue>Think first:</> '.OutputFormatter::escape($step->question()));
            }

            if ($step->tryItText() !== null) {
                $this->line('     <fg=green>Try it:</> '.OutputFormatter::escape(str_replace("\n", ' ', $step->tryItText())));
            }

            if ($step->commitMessage() !== null) {
                $this->line('     <fg=yellow>Commit:</> git status && git diff, then git add . && git commit -m "'.OutputFormatter::escape($step->commitMessage()).'"');
            }

            // Hints hide the answer, so members try first. The web page shows the hints.
            if ($step->hints() !== [] && ! $this->option('answers')) {
                $this->line('     <fg=gray>'.count($step->hints()).' hints on the web page. Add --answers to show the answer here.</>');

                continue;
            }

            if ($step->command() !== null) {
                $this->line("     <comment>{$step->command()}</comment>");
            }

            if ($step->snippet() !== null) {
                foreach (explode("\n", $step->snippet()) as $line) {
                    $this->line('     <fg=cyan>'.OutputFormatter::escape($line).'</>');
                }
            }
        }

        if ($feature->questions() !== []) {
            $this->newLine();
            $this->line(' <options=bold>Finish:</> run guide:done, merge upstream/main, push, then open a pull request. Answer these questions in it:');

            foreach ($feature->questions() as $question) {
                $this->line('   - '.OutputFormatter::escape($question));
            }
        }

        $this->newLine();
    }

    private function colour(Status $status): string
    {
        $colour = match ($status) {
            Status::Done => 'green',
            Status::InProgress => 'cyan',
            Status::Ready => 'yellow',
            Status::Blocked => 'red',
            Status::NotPlanned => 'gray',
        };

        return "<fg={$colour}>{$status->symbol()}</>";
    }
}
