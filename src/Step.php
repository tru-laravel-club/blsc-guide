<?php

namespace TruLaravelClub\BlscGuide;

use Closure;
use Throwable;

/**
 * One task inside a feature, such as "Create the WeeklyTheme model".
 *
 * A step can show a command to run, explain what the command does,
 * and check the host app to see if the step is complete.
 */
final class Step
{
    private ?string $command = null;

    private ?string $explanation = null;

    private ?string $code = null;

    private ?string $docs = null;

    private ?Closure $check = null;

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function run(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    public function explain(string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function code(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function docs(string $url): self
    {
        $this->docs = $url;

        return $this;
    }

    /**
     * @param  Closure(): bool  $check
     */
    public function check(Closure $check): self
    {
        $this->check = $check;

        return $this;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function command(): ?string
    {
        return $this->command;
    }

    public function explanation(): ?string
    {
        return $this->explanation;
    }

    public function snippet(): ?string
    {
        return $this->code;
    }

    public function docsUrl(): ?string
    {
        return $this->docs;
    }

    public function isCheckable(): bool
    {
        return $this->check !== null;
    }

    /**
     * Run the check against the host app. A check that throws counts as not done.
     */
    public function passes(): bool
    {
        if ($this->check === null) {
            return false;
        }

        try {
            return (bool) ($this->check)();
        } catch (Throwable) {
            return false;
        }
    }
}
