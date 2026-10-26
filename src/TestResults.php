<?php

namespace TruLaravelClub\BlscGuide;

use Illuminate\Filesystem\Filesystem;

/**
 * Remembers the last result of each acceptance test that guide:verify ran.
 *
 * The file lives in storage/framework/cache, which Laravel already ignores in git.
 */
final class TestResults
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
    ) {}

    public function passed(string $testName): bool
    {
        return ($this->all()[$testName]['passed'] ?? false) === true;
    }

    public function record(string $testName, bool $passed): void
    {
        $results = $this->all();
        $results[$testName] = ['passed' => $passed, 'at' => date(DATE_ATOM)];

        $this->files->ensureDirectoryExists(dirname($this->path));
        $this->files->put($this->path, json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, array{passed: bool, at: string}>
     */
    private function all(): array
    {
        if (! $this->files->exists($this->path)) {
            return [];
        }

        $results = json_decode($this->files->get($this->path), true);

        return is_array($results) ? $results : [];
    }
}
