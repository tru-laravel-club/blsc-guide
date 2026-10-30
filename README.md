# BLSC contributor guide

[![tests](https://github.com/tru-laravel-club/blsc-guide/actions/workflows/tests.yml/badge.svg)](https://github.com/tru-laravel-club/blsc-guide/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/tru-laravel-club/blsc-guide)](https://packagist.org/packages/tru-laravel-club/blsc-guide)

This package teaches contributors to build the [BIG Little Science Centre website](https://github.com/tru-laravel-club/blsc-website). The goal is modern backend development: databases, ORMs, package managers, file storage, queues, caching, security and testing. Laravel is the tool, because it gives each of these ideas a clean form. It shows every planned feature, what each feature needs first, and how to build it. Each step explains an idea, asks a question, and gives hints before it gives the answer. The guide checks the app code, so the steps tick off as you work.

The guide is a development tool only. The BLSC website never depends on it.

## Install

`blsc-website` lists the guide as a dev dependency. Contributors get it with `composer install`. Then they start the app with `composer dev` and open <http://localhost:8000/guide>.

The guide loads only when `APP_ENV=local`. Servers install with `composer install --no-dev`, so the guide is never on a server.

## Build a feature

Follow `CONTRIBUTING.md` in `blsc-website`. In short:

1. Find a feature with the status **Ready** in `/guide` or with `php artisan guide`.
2. Claim the GitHub issue for the feature.
3. Fork `blsc-website` and make a branch.
4. Run `php artisan guide:start <feature>` to copy in the acceptance test. Do not change what the test checks.
5. Do each step.
6. Run `php artisan guide:verify <feature>` and `composer ci:check`.
7. Run `php artisan guide:done <feature>` and commit the file that it creates in `.blsc-guide/done`.
8. Open a pull request from your fork.

`php artisan guide <feature>` hides the answers that hints cover. Add `--answers` to show them.

`guide:start` reads the GitHub issue of the feature and shows who is assigned. If nobody is assigned, it asks before it starts. The repository is private, so put a GitHub token with read access to issues in `.env` as `GITHUB_TOKEN`. Never commit the token. Without a token, the command shows a warning and continues.

## Statuses

| Status | Meaning |
| --- | --- |
| Done | Every step passes its check, or a merged pull request marked the feature as done. |
| In progress | Some steps pass. |
| Ready | Every feature it needs is done. Pick it up. |
| Blocked | A feature it needs is not done. The guide names it. |
| Not planned | A maintainer has not written the steps yet. |

The guide reads the code on your machine. Get the latest `main` to see work that other contributors merged.

## Mark a feature as done

The result of `guide:verify` stays on the machine that ran it. Other contributors do not see it. So the pull request that builds a feature also marks it as done:

```shell
php artisan guide:done big-little-scientists
```

This creates one small file, `.blsc-guide/done/<feature>`, in `blsc-website`. Commit the file with your work. Each feature has its own file, so two pull requests that mark different features never conflict. When the pull request merges, the features that need it unlock for every contributor.

You can also click **Mark as done** at the bottom of the page of the feature. To remove a mark, click **Remove the mark** or add `--undo` to the command.

## Improve the guide

Anyone can improve the guide. If a step confused you, fix it for the next contributor.

1. Fork this repository and clone your fork next to `blsc-website`.
2. Make a branch.
3. Run the checks:

   ```shell
   composer install
   composer ci:check
   ```

   This runs Pint, PHPStan and the test suite. CI runs the same checks on PHP 8.3, 8.4 and 8.5.

4. To try your change in the app, use a path repository in `blsc-website`:

   ```shell
   composer config repositories.blsc-guide path ../blsc-guide
   composer require --dev tru-laravel-club/blsc-guide:@dev
   ```

   Do not commit these `composer.json` changes.

5. Open a pull request from your fork.

## Write a feature

Maintainers plan new features. Each feature is one PHP file in `features/`. The file returns a `Feature`. See `features/payments.php` for a full example. A feature without steps has the status "Not planned".

```php
return Feature::make('program-categories')
    ->title('Program categories')
    ->section(Section::Catalog)
    ->level(2)
    ->needs(['programs-admin'])
    ->summary('Staff manage categories, such as Camps and Clubs.')
    ->teaches(['Models', 'Migrations', 'belongsTo relationships'])
    ->test('ProgramCategoriesTest.php')
    ->intro('The big picture: what the feature does and how its parts fit together.')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->reflect(['The code never checks the name of a category. Why is this important?'])
    ->steps([
        Step::make('Make a model for categories')
            ->explain('What a model is, and why the feature needs one.')
            ->think('Look at the test. Which columns does a category need?')
            ->hint('The smallest hint first.')
            ->hint('A bigger hint.')
            ->run('php artisan make:model Category -mf')
            ->docs('https://laravel.com/docs/eloquent#generating-model-classes', 'Laravel docs: Generating model classes')
            ->check(Check::classExists('App\Models\Category')),
    ]);
```

| Method | Shows |
| --- | --- |
| `intro()` | "How it works", before the steps. Separate paragraphs with a blank line. |
| `docs()`, `video()` | Links to read or watch. Use them on the feature and on each step. |
| `reflect()` | Questions that the contributor answers in the pull request. |
| `explain()` | The idea behind the step. Explain why, not only what. |
| `think()` | A question to answer before writing code. |
| `hint()` | Hints that open one at a time. When a step has hints, the guide hides its `run()` and `code()` under "Show the answer". |
| `run()`, `code()` | The command and the code. |
| `check()` | The check that ticks off the step. |

Follow these rules:

- Teach the idea first. Give the answer last.
- Give each step a check. Use the helpers in `src/Check.php`.
- Put `code` in backticks in the text. The guide shows it as code.
- Link only to pages that you opened and read.
- Name app classes as strings. The app must never depend on the guide.
- Put teaching text in the guide, not in comments in the app.
- Put the acceptance test in `stubs/tests`. Fix the names of routes, models and tables in the test, so later features can depend on them.
- Build the feature from your own steps before you open a pull request. This finds steps that do not match the test.
- Run `vendor/bin/pest`. A test fails if a feature needs a key that does not exist, or if dependencies make a loop.

## Remove the guide before handover

The BLSC website must not mention the guide. Before you hand over the repository, run this in `blsc-website`:

```shell
composer remove --dev tru-laravel-club/blsc-guide
rm -r .blsc-guide
```

Reject pull requests that import `TruLaravelClub\BlscGuide` in app code.

## License

MIT
