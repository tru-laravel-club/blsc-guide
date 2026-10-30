<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('member-lookup')
    ->title('Member lookup')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['membership-plans'])
    ->summary('Staff search for a member at the front desk by name or email, and see the plan, the end date and if the membership is active.')
    ->teaches(['Search queries', 'Logical grouping', 'whereHas', 'Has one of many'])
    ->test('MemberLookupTest.php')
    ->intro(<<<'TEXT'
        A family comes to the front desk and says "We are members". Staff need an answer in a few seconds: which plan, until when, and is it still active?

        The Membership plans feature stores each membership with a start date and an end date. A family that renews has several memberships. The front desk needs only the one that ends last. So you add one relationship to `User`: `latestMembership()`, the paid membership with the latest `ends_on`.

        The page is a search form. Staff type part of a name or an email. The query finds users who have at least one paid membership, and whose name or email contains the text. Each row shows the plan, the end date and "Active" or "Expired".

        Many users are children's parents. The page shows only what the desk needs: name, email, plan and dates. It does not show children or answers from forms.
        TEXT)
    ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
    ->docs('https://laravel.com/docs/eloquent-relationships#has-one-of-many', 'Laravel docs: Has one of many')
    ->reflect([
        'Why does the query wrap the name and email conditions in one `where(fn ...)`? What would it find without the group?',
        'The page shows a parent\'s email to every staff member. Is that necessary at the front desk? What would you hide?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start member-lookup')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/MemberLookupTest.php` into the app. Read the whole test first.

                Look at the rule about a renewal. The parent has two memberships, one from last year and one from this year. The page must show only the newer one. Look at the last rule, too: a person with an unpaid membership in their cart is not a member.
                TEXT)
            ->think('Staff search for "sam". Which users must the page show? Which users must it not show?')
            ->tryIt('Run `php artisan test tests/Feature/MemberLookupTest.php`. All the rules fail, because the `admin.members.index` route does not exist yet.')
            ->commit('Add the member lookup acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/MemberLookupTest.php')),

        Step::make('Find the latest membership of a user')
            ->explain(<<<'TEXT'
                Open `app/Models/User.php`. Add `latestMembership(): HasOne`. It returns the one membership with the largest `ends_on`.

                Laravel has a "one of many" relationship for this: `$this->hasOne(Membership::class)->ofMany('ends_on', 'max')`. It builds one query that finds the latest membership for each user. So the page can load it for 25 users at once with `with('latestMembership.plan')`.

                An unpaid membership has no `ends_on`. `max` ignores empty values, so an unpaid membership is never the latest one.
                TEXT)
            ->think('`activeMembership()` already exists. Why is it not enough for this page?')
            ->hint('Look at `Order::payment()`. It uses `latestOfMany()`, which is `ofMany()` on the ID.')
            ->code(<<<'PHP'
                /**
                 * The paid membership that ends last. It can be active or expired.
                 *
                 * @return HasOne<Membership, $this>
                 */
                public function latestMembership(): HasOne
                {
                    return $this->hasOne(Membership::class)->ofMany('ends_on', 'max');
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$m = App\Models\Membership::factory()->expired()->create(); $m->user->latestMembership->is($m)`. You see `true`.')
            ->commit('Add User::latestMembership()')
            ->docs('https://laravel.com/docs/eloquent-relationships#has-one-of-many', 'Laravel docs: Has one of many')
            ->check(Check::fileContains('app/Models/User.php', 'function latestMembership')),

        Step::make('Search for members')
            ->run('php artisan make:controller Admin/MemberController')
            ->explain(<<<'TEXT'
                Add `GET members` named `members.index` in the staff group. With the group, the name is `admin.members.index`.

                `index()` reads `search` from the query string and trims it. When it is empty, the page shows only the form. Otherwise, find the users:

                1. `whereHas('memberships', ...)` keeps users with at least one paid membership: `ends_on` is not `null`.
                2. `whereLike('name', ...)` or `whereLike('email', ...)` matches part of the name or the email. `whereLike()` ignores upper and lower case.
                3. Put the two `like` conditions in one `where(fn ...)` group. Without the group, the `or` would skip the membership condition.
                4. Load `latestMembership.plan`, sort by name and stop at 25 results.

                The view shows a table with the name, the email, the plan, the end date and a badge. The membership is "Active" when `ends_on` is today or later. Otherwise it is "Expired". When nothing matches, show "No member matches" and the search text. Add a "Members" link to the staff menu.
                TEXT)
            ->think('Without the `where(fn ...)` group, the SQL is `has membership AND name LIKE ... OR email LIKE ...`. Which users does that find by mistake?')
            ->hint('`$request->string(\'search\')->toString()` returns the text, or an empty string.')
            ->hint('`$membership?->ends_on?->gte(today())` is `true` for an active membership.')
            ->code(<<<'PHP'
                $members = $search === '' ? collect() : User::query()
                    ->whereHas('memberships', fn (Builder $query) => $query->whereNotNull('ends_on'))
                    ->where(fn (Builder $query) => $query
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%"))
                    ->with('latestMembership.plan')
                    ->orderBy('name')
                    ->limit(25)
                    ->get();
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Membership::factory()->for(App\Models\User::firstWhere(\'email\', \'test@example.com\'))->create()`. Log in as `staff@example.com`, click "Members" and search for `test`. You see the test user, the Family plan, the end date and "Active".')
            ->commit('Add the member lookup page')
            ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
            ->docs('https://laravel.com/docs/eloquent-relationships#querying-relationship-existence', 'Laravel docs: Querying relationship existence')
            ->docs('https://laravel.com/docs/queries#where-clauses', 'Laravel docs: Where clauses')
            ->check(Check::all(Check::route('admin.members.index'), Check::view('admin.members.index'))),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify member-lookup')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                PHPStan may ask for the types of the closures. Type the parameter as `Illuminate\Database\Eloquent\Builder`.
                TEXT)
            ->think('The centre has 5,000 members next year. Which part of the search gets slow? What could help?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for member lookup')
            ->check(Check::testPassed('MemberLookupTest')),
    ]);
