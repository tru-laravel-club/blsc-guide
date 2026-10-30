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

    /** @var list<array{type: 'docs'|'video', url: string, title: string}> */
    private array $links = [];

    private ?string $question = null;

    /** @var list<string> */
    private array $hints = [];

    private ?string $tryIt = null;

    private ?string $commit = null;

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

    /**
     * A question for the member to think about before they write code.
     */
    public function think(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    /**
     * Add a hint. The guide shows hints one at a time, so give the smallest hint first.
     */
    public function hint(string $hint): self
    {
        $this->hints[] = $hint;

        return $this;
    }

    /**
     * Link to a page of the Laravel documentation, or another page to read.
     */
    /**
     * Tell the member how to see the result of the step, such as a page to open.
     */
    public function tryIt(string $text): self
    {
        $this->tryIt = $text;

        return $this;
    }

    /**
     * Suggest a commit message for the work of this step, such as "Add the Category model".
     */
    public function commit(string $message): self
    {
        $this->commit = $message;

        return $this;
    }

    public function docs(string $url, string $title = 'Laravel docs'): self
    {
        $this->links[] = ['type' => 'docs', 'url' => $url, 'title' => $title];

        return $this;
    }

    /**
     * Link to a video that explains the idea of this step.
     */
    public function video(string $url, string $title): self
    {
        $this->links[] = ['type' => 'video', 'url' => $url, 'title' => $title];

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

    public function question(): ?string
    {
        return $this->question;
    }

    /**
     * @return list<string>
     */
    public function hints(): array
    {
        return $this->hints;
    }

    public function tryItText(): ?string
    {
        return $this->tryIt;
    }

    public function commitMessage(): ?string
    {
        return $this->commit;
    }

    /**
     * @return list<array{type: 'docs'|'video', url: string, title: string}>
     */
    public function links(): array
    {
        return $this->links;
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
