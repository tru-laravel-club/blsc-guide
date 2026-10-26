# BLSC contributor guide

This package is a step-by-step guide for club members who build the [BIG Little Science Centre website](https://github.com/tru-laravel-club/blsc-website). It shows every planned feature, what each feature needs first, and the steps to build it. The guide checks the app code, so the steps tick off as you work.

The guide is a development tool only. The BLSC website never depends on it.

## Install

1. Clone `blsc-website` and set it up. Read its README.
2. In the `blsc-website` folder, install the guide as a dev dependency:

   ```shell
   composer require --dev tru-laravel-club/blsc-guide
   ```

3. Start the app with `composer dev`.
4. Open <http://localhost:8000/guide>.

The guide loads only when `APP_ENV=local`. Servers install with `composer install --no-dev`, so the guide is never on a server.

### Before the package is on Packagist

Clone this repository next to `blsc-website`. Then add a path repository in `blsc-website`:

```shell
composer config repositories.blsc-guide path ../blsc-guide
composer require --dev tru-laravel-club/blsc-guide:@dev
```

Do not commit these `composer.json` changes.

## Build a feature

1. Open `/guide` or run `php artisan guide`. Find a feature with the status **Ready**. Start with Level 1.
2. Claim the GitHub issue for the feature, so nobody else builds it.
3. Start the feature:

   ```shell
   php artisan guide:start big-little-scientists
   ```

   This copies the acceptance test of the feature into `tests/Feature`. The test fails at first. You are done when it passes. Do not change what the test checks.

4. Do each step. Run each command yourself and read the files it creates.
5. Run the acceptance test:

   ```shell
   php artisan guide:verify big-little-scientists
   ```

6. Run `composer ci:check`, then open a pull request.

## Statuses

| Status | Meaning |
| --- | --- |
| Done | Every step passes its check. |
| In progress | Some steps pass. |
| Ready | Every feature it needs is done. Pick it up. |
| Blocked | A feature it needs is not done. The guide names it. |
| Not planned | A lead has not written the steps yet. |

The guide reads the code on your machine. Run `git pull` to see work that other members merged.

## For leads: plan a feature

Each feature is one PHP file in `features/`. The file returns a `Feature`:

```php
return Feature::make('saturday-programs')
    ->title('Saturday programming page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('Special Saturday events. Drop-in and free with admission.')
    ->teaches(['Models', 'Migrations', 'Query scopes'])
    ->test('SaturdayProgramsTest.php')
    ->steps([
        Step::make('Create the SaturdayEvent model')
            ->run('php artisan make:model SaturdayEvent -mf')
            ->check(Check::classExists('App\Models\SaturdayEvent')),
    ]);
```

Follow these rules:

- Give each step a check. Use the helpers in `src/Check.php`.
- Name app classes as strings. The app must never depend on the guide.
- Put teaching text in the guide, not in comments in the app.
- Put the acceptance test in `stubs/tests`. Fix the names of routes, models and tables in the test, so later features can depend on them.
- Run `vendor/bin/pest`. A test fails if a feature needs a key that does not exist, or if dependencies make a loop.

## Remove the guide before handover

The BLSC website must not mention the guide. Before you hand over the repository, run this in `blsc-website`:

```shell
composer remove --dev tru-laravel-club/blsc-guide
```

Reject pull requests that import `TruLaravelClub\BlscGuide` in app code.

## License

MIT
