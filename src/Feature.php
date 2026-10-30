<?php

namespace TruLaravelClub\BlscGuide;

use Illuminate\Support\Str;

/**
 * A piece of the BLSC website that one member can build in one pull request.
 */
final class Feature
{
    private string $title;

    private string $summary = '';

    private Section $section = Section::Foundation;

    private int $level = 1;

    /** @var list<string> */
    private array $needs = [];

    /** @var list<string> */
    private array $teaches = [];

    /** @var list<Step> */
    private array $steps = [];

    private ?string $intro = null;

    /** @var list<string> */
    private array $reflect = [];

    /** @var list<array{type: 'docs'|'video', url: string, title: string}> */
    private array $links = [];

    private ?int $issue = null;

    private ?string $testTemplate = null;

    private function __construct(private readonly string $key)
    {
        $this->title = Str::headline($key);
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function summary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function section(Section $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function level(int $level): self
    {
        $this->level = $level;

        return $this;
    }

    /**
     * @param  list<string>  $features
     */
    public function needs(array $features): self
    {
        $this->needs = $features;

        return $this;
    }

    /**
     * @param  list<string>  $concepts
     */
    public function teaches(array $concepts): self
    {
        $this->teaches = $concepts;

        return $this;
    }

    /**
     * @param  list<Step>  $steps
     */
    public function steps(array $steps): self
    {
        $this->steps = $steps;

        return $this;
    }

    /**
     * Explain the big picture: what the feature does and how its parts fit together.
     * Separate paragraphs with a blank line.
     */
    public function intro(string $intro): self
    {
        $this->intro = $intro;

        return $this;
    }

    /**
     * @param  list<string>  $questions  Questions that the contributor answers in the pull request.
     */
    public function reflect(array $questions): self
    {
        $this->reflect = $questions;

        return $this;
    }

    /**
     * Link to a page to read before the member starts.
     */
    public function docs(string $url, string $title = 'Laravel docs'): self
    {
        $this->links[] = ['type' => 'docs', 'url' => $url, 'title' => $title];

        return $this;
    }

    /**
     * Link to a video to watch before the member starts.
     */
    public function video(string $url, string $title): self
    {
        $this->links[] = ['type' => 'video', 'url' => $url, 'title' => $title];

        return $this;
    }

    public function issue(int $number): self
    {
        $this->issue = $number;

        return $this;
    }

    /**
     * Name a test file in the package's stubs/tests folder.
     * The guide:start command copies it into the host app.
     */
    public function test(string $filename): self
    {
        $this->testTemplate = $filename;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->summary;
    }

    public function sectionName(): Section
    {
        return $this->section;
    }

    public function difficulty(): int
    {
        return $this->level;
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return $this->needs;
    }

    /**
     * @return list<string>
     */
    public function concepts(): array
    {
        return $this->teaches;
    }

    /**
     * @return list<Step>
     */
    public function stepList(): array
    {
        return $this->steps;
    }

    public function introduction(): ?string
    {
        return $this->intro;
    }

    /**
     * @return list<string>
     */
    public function questions(): array
    {
        return $this->reflect;
    }

    /**
     * @return list<array{type: 'docs'|'video', url: string, title: string}>
     */
    public function links(): array
    {
        return $this->links;
    }

    public function issueNumber(): ?int
    {
        return $this->issue;
    }

    public function testTemplate(): ?string
    {
        return $this->testTemplate;
    }

    /**
     * The test class name the template uses, such as "BigLittleScientistsTest".
     */
    public function testName(): ?string
    {
        return $this->testTemplate === null ? null : pathinfo($this->testTemplate, PATHINFO_FILENAME);
    }

    public function isPlanned(): bool
    {
        return $this->steps !== [];
    }
}
