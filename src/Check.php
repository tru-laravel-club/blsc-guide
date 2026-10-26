<?php

namespace TruLaravelClub\BlscGuide;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * Ready-made checks that look at the host app.
 *
 * Feature files name app classes as strings, so the app never depends on the guide.
 */
final class Check
{
    public static function route(string ...$names): Closure
    {
        return static function () use ($names): bool {
            foreach ($names as $name) {
                if (! Route::has($name)) {
                    return false;
                }
            }

            return true;
        };
    }

    public static function classExists(string $class): Closure
    {
        return static fn (): bool => class_exists($class);
    }

    /**
     * @param  list<string>  $columns
     */
    public static function table(string $table, array $columns = []): Closure
    {
        return static fn (): bool => Schema::hasTable($table) && Schema::hasColumns($table, $columns);
    }

    public static function view(string $name): Closure
    {
        return static fn (): bool => View::exists($name);
    }

    public static function gate(string $ability): Closure
    {
        return static fn (): bool => Gate::has($ability);
    }

    /**
     * @param  string  $path  A path relative to the app root, such as "routes/web.php".
     */
    public static function fileExists(string $path): Closure
    {
        return static fn (): bool => is_file(base_path($path));
    }

    /**
     * @param  string  $path  A path relative to the app root, such as "routes/web.php".
     */
    public static function fileContains(string $path, string $needle): Closure
    {
        return static fn (): bool => is_file(base_path($path))
            && str_contains((string) file_get_contents(base_path($path)), $needle);
    }

    /**
     * Pass when the last guide:verify run of this acceptance test passed.
     */
    public static function testPassed(string $testName): Closure
    {
        return static fn (): bool => app(TestResults::class)->passed($testName);
    }

    public static function all(Closure ...$checks): Closure
    {
        return static function () use ($checks): bool {
            foreach ($checks as $check) {
                if (! $check()) {
                    return false;
                }
            }

            return true;
        };
    }
}
