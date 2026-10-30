<?php

namespace TruLaravelClub\BlscGuide;

use Illuminate\Filesystem\Filesystem;

/**
 * The features that are marked as done.
 *
 * Each marked feature has its own small file in a folder that is committed to blsc-website.
 * Test results stay on one machine, so these files are how every contributor sees that a
 * merged feature is done. One file for each feature means that two pull requests never
 * change the same file when they mark different features.
 */
final class Progress
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $directory,
    ) {}

    public function isMarked(string $key): bool
    {
        return $this->files->isFile($this->fileFor($key));
    }

    /**
     * The date when the feature was marked, such as "2026-10-30".
     */
    public function markedOn(string $key): ?string
    {
        return $this->isMarked($key) ? trim($this->files->get($this->fileFor($key))) : null;
    }

    public function mark(string $key): void
    {
        if ($this->isMarked($key)) {
            return;
        }

        $this->files->ensureDirectoryExists($this->directory);
        $this->files->put($this->fileFor($key), date('Y-m-d')."\n");
    }

    public function unmark(string $key): void
    {
        $this->files->delete($this->fileFor($key));
    }

    /**
     * The file that marks one feature as done.
     */
    public function fileFor(string $key): string
    {
        if (preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
            throw new \InvalidArgumentException("[{$key}] is not a feature key.");
        }

        return $this->directory.'/'.$key;
    }
}
