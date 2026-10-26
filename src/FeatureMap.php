<?php

namespace TruLaravelClub\BlscGuide;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Every planned feature of the BLSC website and how they depend on each other.
 */
final class FeatureMap
{
    /** @var array<string, Feature> */
    private array $features = [];

    /** @var array<string, Status> */
    private array $statuses = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param  iterable<Feature>  $features
     */
    public function __construct(iterable $features)
    {
        foreach ($features as $feature) {
            if (isset($this->features[$feature->key()])) {
                throw new InvalidArgumentException("Two features use the key [{$feature->key()}].");
            }

            $this->features[$feature->key()] = $feature;
        }
    }

    /**
     * Load every PHP file in a folder. Each file must return a Feature.
     */
    public static function fromDirectory(string $directory): self
    {
        $files = glob(rtrim($directory, '/').'/*.php') ?: [];
        sort($files);

        return new self(array_map(static function (string $file): Feature {
            $feature = require $file;

            if (! $feature instanceof Feature) {
                throw new UnexpectedValueException("The file [{$file}] must return a Feature.");
            }

            return $feature;
        }, $files));
    }

    /**
     * @return array<string, Feature>
     */
    public function all(): array
    {
        return $this->features;
    }

    public function find(string $key): ?Feature
    {
        return $this->features[$key] ?? null;
    }

    /**
     * @return array<string, list<Feature>> Features grouped by section value, in section order.
     */
    public function bySection(): array
    {
        $groups = [];

        foreach (Section::cases() as $section) {
            $features = array_values(array_filter(
                $this->features,
                static fn (Feature $feature): bool => $feature->sectionName() === $section,
            ));

            usort($features, static fn (Feature $a, Feature $b): int => [$a->difficulty(), $a->name()] <=> [$b->difficulty(), $b->name()]);

            if ($features !== []) {
                $groups[$section->value] = $features;
            }
        }

        return $groups;
    }

    public function status(Feature $feature): Status
    {
        $key = $feature->key();

        if (isset($this->statuses[$key])) {
            return $this->statuses[$key];
        }

        // A dependency loop is a planning mistake. Treat it as blocked instead of looping forever.
        if (isset($this->resolving[$key])) {
            return Status::Blocked;
        }

        $this->resolving[$key] = true;
        $status = $this->resolveStatus($feature);
        unset($this->resolving[$key]);

        return $this->statuses[$key] = $status;
    }

    /**
     * The features this one needs that are not done yet.
     *
     * @return list<Feature>
     */
    public function blockers(Feature $feature): array
    {
        $blockers = [];

        foreach ($feature->dependencies() as $key) {
            $dependency = $this->features[$key] ?? null;

            if ($dependency !== null && $this->status($dependency) !== Status::Done) {
                $blockers[] = $dependency;
            }
        }

        return $blockers;
    }

    /**
     * The features that list this one as a dependency.
     *
     * @return list<Feature>
     */
    public function unlocks(Feature $feature): array
    {
        return array_values(array_filter(
            $this->features,
            static fn (Feature $other): bool => in_array($feature->key(), $other->dependencies(), true),
        ));
    }

    public function completedSteps(Feature $feature): int
    {
        return count(array_filter($feature->stepList(), static fn (Step $step): bool => $step->passes()));
    }

    /**
     * Find planning mistakes: unknown dependencies and dependency loops.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach ($this->features as $feature) {
            foreach ($feature->dependencies() as $key) {
                if (! isset($this->features[$key])) {
                    $problems[] = "[{$feature->key()}] needs [{$key}], but no feature has that key.";
                }
            }

            if ($this->dependsOn($feature->key(), $feature->key())) {
                $problems[] = "[{$feature->key()}] depends on itself through a loop.";
            }
        }

        return $problems;
    }

    private function resolveStatus(Feature $feature): Status
    {
        if (! $feature->isPlanned()) {
            return Status::NotPlanned;
        }

        $checkable = array_filter($feature->stepList(), static fn (Step $step): bool => $step->isCheckable());
        $passed = array_filter($checkable, static fn (Step $step): bool => $step->passes());

        if ($checkable !== [] && count($passed) === count($checkable)) {
            return Status::Done;
        }

        if ($passed !== []) {
            return Status::InProgress;
        }

        return $this->blockers($feature) === [] ? Status::Ready : Status::Blocked;
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function dependsOn(string $from, string $target, array $visited = []): bool
    {
        foreach (($this->features[$from] ?? null)?->dependencies() ?? [] as $key) {
            if ($key === $target) {
                return true;
            }

            if (! isset($visited[$key]) && isset($this->features[$key])) {
                $visited[$key] = true;

                if ($this->dependsOn($key, $target, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }
}
