<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Status;

class GuideCommand extends Command
{
    protected $signature = 'guide {feature? : The feature key, such as big-little-scientists}';

    protected $description = 'Show the BLSC feature map, or the steps of one feature';

    public function handle(FeatureMap $map): int
    {
        $key = $this->argument('feature');

        if ($key === null) {
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
        $this->line('Run <comment>php artisan guide {feature}</comment> to see the steps, or open <comment>'.url(config('blsc-guide.path')).'</comment>.');
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
            $this->components->info('A lead has not written the steps for this feature yet.');

            return;
        }

        foreach ($feature->stepList() as $index => $step) {
            $mark = match (true) {
                ! $step->isCheckable() => '<fg=gray>·</>',
                $step->passes() => '<fg=green>✔</>',
                default => '<fg=yellow>○</>',
            };

            $this->newLine();
            $this->line(" {$mark} <options=bold>".($index + 1).". {$step->title()}</>");

            if ($step->command() !== null) {
                $this->line("     <comment>{$step->command()}</comment>");
            }

            if ($step->explanation() !== null) {
                $this->line("     {$step->explanation()}");
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
