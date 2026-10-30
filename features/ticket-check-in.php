<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('ticket-check-in')
    ->title('Ticket check-in')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['tickets'])
    ->summary('Staff type the code of a ticket at the door and mark it as used. The same ticket cannot get in twice.')
    ->teaches(['Conditional updates', 'Race conditions', 'Redirects with errors', 'Staff routes'])
    ->test('TicketCheckInTest.php')
    ->intro(<<<'TEXT'
        On the night of BIG Night Out, a visitor shows their ticket code at the door. A staff member types the code on a staff page. The page says "Checked in" with the name of the ticket, or it says why the visitor cannot get in.

        A ticket from the Tickets feature has a `code` when it is paid and a `used_at` date when it is used. Check-in sets `used_at`. An unpaid ticket has no code, so staff cannot find it.

        Two things can go wrong at a door. A visitor shows a screenshot of a ticket that a friend already used. Or two staff members at two doors type the same code at the same moment. Both cases must give "already used" to one of them. So the code never checks first and changes later. One database query does both: it sets `used_at` only where `used_at` is still empty, and it tells you how many rows it changed.

        This is the same pattern as redeeming a gift certificate. If you built that feature, compare the two controllers.
        TEXT)
    ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
    ->docs('https://laravel.com/docs/responses#redirecting-with-flashed-session-data', 'Laravel docs: Redirecting with flashed session data')
    ->reflect([
        'Two staff members type the same code at the same moment. Explain, step by step, why only one of them sees "Checked in".',
        'A visitor shows a BIG Night Out ticket on the wrong night. What does the page do today? What would you change?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start ticket-check-in')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/TicketCheckInTest.php` into the app. Read the whole test first.

                Look at the second check-in rule. It travels five minutes forward and checks in the same ticket again. Then it expects the time of the first use in the error, and `used_at` must not change. The second scan must not move the time forward.
                TEXT)
            ->think('Why does the test move the clock forward between the two check-ins?')
            ->tryIt('Run `php artisan test tests/Feature/TicketCheckInTest.php`. All the rules fail, because the `admin.check-in.show` route does not exist yet.')
            ->commit('Add the ticket check-in acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
            ->check(Check::fileExists('tests/Feature/TicketCheckInTest.php')),

        Step::make('Add the check-in page')
            ->run('php artisan make:controller Admin/CheckInController')
            ->explain(<<<'TEXT'
                Add two routes in the staff group: `GET check-in` named `check-in.show` and `POST check-in` named `check-in.store`. With the staff group, the names start with `admin.`, and only staff can open them.

                `show()` returns `resources/views/admin/check-in/show.blade.php`. The page has one form: a `code` field with `autofocus` and a "Check in" button. Show the status message with `<x-admin.status />`. `<x-form-field>` already shows the error for `code`.

                Add a "Check-in" link to the staff menu in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('Staff check in many visitors in a row. Why does the field need `autofocus`?')
            ->hint('Look at the routes of the category admin. Your two routes go in the same group.')
            ->code(<<<'PHP'
                Route::get('check-in', [Admin\CheckInController::class, 'show'])->name('check-in.show');
                Route::post('check-in', [Admin\CheckInController::class, 'store'])->name('check-in.store');
                PHP)
            ->tryIt('Log in as `staff@example.com` and click "Check-in". You see the code field, with the cursor in it.')
            ->commit('Add the ticket check-in page')
            ->docs('https://laravel.com/docs/routing#route-groups', 'Laravel docs: Route groups')
            ->check(Check::all(Check::route('admin.check-in.show', 'admin.check-in.store'), Check::view('admin.check-in.show'))),

        Step::make('Check in a ticket only once')
            ->explain(<<<'TEXT'
                Write `store()`. Validate that `code` is present. Then clean it: trim the spaces and make it upper case, because people type codes in many ways.

                Find the ticket by its code, with its type. If there is none, redirect to the page with a `code` error: "Ticket not found. Check the code with the visitor."

                Then update `used_at` with one query: `whereKey($ticket->id)->whereNull('used_at')->update([...])`. The database checks and changes the row in the same moment, so two clicks cannot both pass. The query returns the number of changed rows.

                If it is `0`, another check-in was first. Load the ticket again and show the time of that check-in: "This ticket was already used at 7:42 PM." Otherwise, show "Checked in:" and the ticket name with `$ticket->ticketType->displayName()`.

                Redirect to `admin.check-in.show` each time, so the field is empty for the next visitor.
                TEXT)
            ->think('Why is `if ($ticket->used_at === null) { $ticket->update([...]); }` not safe at a busy door?')
            ->hint('`Str::upper(trim($request->string(\'code\')->toString()))` cleans what staff typed.')
            ->hint('`$ticket->fresh()?->used_at?->format(\'g:i A\')` reads the time that the other check-in saved.')
            ->code(<<<'PHP'
                $checkedIn = Ticket::whereKey($ticket->id)
                    ->whereNull('used_at')
                    ->update(['used_at' => now()]);

                if ($checkedIn === 0) {
                    return to_route('admin.check-in.show')->withErrors([
                        'code' => 'This ticket was already used at '.$ticket->fresh()?->used_at?->format('g:i A').'.',
                    ]);
                }

                return to_route('admin.check-in.show')->with('status', 'Checked in: '.$ticket->ticketType->displayName().'.');
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\Ticket::factory()->paid()->create()->code`. Note the code.

                On the check-in page, type the code in small letters. You see "Checked in: General admission." Type it again. You see "This ticket was already used at" and the time. Type `NOPE1234`. You see "Ticket not found."
                TEXT)
            ->commit('Check in each ticket only once')
            ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
            ->docs('https://laravel.com/docs/responses#redirecting-with-flashed-session-data', 'Laravel docs: Redirecting with flashed session data')
            ->check(Check::fileContains('app/Http/Controllers/Admin/CheckInController.php', "whereNull('used_at')")),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify ticket-check-in')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                Look at the time in the "already used" message on your machine. The app stores times in UTC, the time zone in `config/app.php`. Note in your pull request what staff in Kamloops see, and suggest a fix if the time is wrong for them.
                TEXT)
            ->think('Staff want a scanner instead of typing. Which part of your code changes? Which part stays the same?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for ticket check-in')
            ->check(Check::testPassed('TicketCheckInTest')),
    ]);
