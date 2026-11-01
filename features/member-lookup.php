<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

$component = 'resources/views/components/admin/⚡member-search.blade.php';

return Feature::make('member-lookup')
    ->title('Member lookup')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['membership-plans', 'program-search'])
    ->summary('Staff search for a member at the front desk by name or email, and see the plan, the end date and if the membership is active. The results change while staff type.')
    ->teaches(['Search queries', 'Logical grouping', 'whereHas', 'Has one of many', 'Livewire components', 'The #[Url] attribute'])
    ->test('MemberLookupTest.php')
    ->intro(<<<'TEXT'
        A family comes to the front desk and says "We are members". Staff need an answer in a few seconds: which plan, until when, and is it still active?

        The Membership plans feature stores each membership with a start date and an end date. A family that renews has several memberships. The front desk needs only the one that ends last. So you add one relationship to `User`: `latestMembership()`, the paid membership with the latest `ends_on`.

        The page is a search box. Staff type part of a name or an email. The query finds users who have at least one paid membership, and whose name or email contains the text. Each row shows the plan, the end date and "Active" or "Expired".

        Staff at a desk often do not know how a name is spelled. They type "pri", look, and type more. So the results change while they type, with a small Livewire component, as in the program search feature. The search also stays in the address, so `?search=priya` works and an HTTP test can check it. The page only reads data, so it needs no service. The query lives in the component, the one place that uses it.

        Many users are children's parents. The page shows only what the desk needs: name, email, plan and dates. It does not show children or answers from forms.
        TEXT)
    ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
    ->docs('https://laravel.com/docs/eloquent-relationships#has-one-of-many', 'Laravel docs: Has one of many')
    ->reflect([
        'Why does the query wrap the name and email conditions in one `where(fn ...)`? What would it find without the group?',
        'The member search changes nothing in the database. Why does it need no service? When would it need one?',
        'The page shows a parent\'s email to every staff member. Is that necessary at the front desk? What would you hide?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start member-lookup')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/MemberLookupTest.php` into the app. Read the whole test first.

                Look at the rule about a renewal. The parent has two memberships, one from last year and one from this year. The page must show only the newer one. Look at the rule about unpaid memberships, too: a person with an unpaid membership in their cart is not a member.

                Most rules open `admin.members.index` with `?search=` in the address. The last two rules use `Livewire::test()` on the component `admin.member-search`, as the program search test does.
                TEXT)
            ->think('Staff search for "sam". Which users must the page show? Which users must it not show?')
            ->tryIt('Run `php artisan test tests/Feature/MemberLookupTest.php`. All the rules fail, because the `admin.members.index` route and the `admin.member-search` component do not exist yet.')
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

        Step::make('Add the members page')
            ->explain(<<<'TEXT'
                Add `GET members` named `members.index` in the staff group. With the group, the name is `admin.members.index`, and only staff can open it.

                The page reads nothing itself. The component in the next step does the search. So the route needs no controller: `Route::view('members', 'admin.members.index')` returns the view. Make `resources/views/admin/members/index.blade.php` with the staff layout and a short text for now, such as "Search for a member by name or email."

                Add a "Members" link to the staff menu in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('`Route::view()` has no controller. Which part of the app still checks that the user is staff?')
            ->hint('Look at `Route::view(\'dashboard\', \'dashboard\')` in `routes/web.php`.')
            ->code(<<<'PHP'
                Route::view('members', 'admin.members.index')->name('members.index');
                PHP)
            ->tryIt('Log in as `staff@example.com` and click "Members". You see your text. Run `php artisan test tests/Feature/MemberLookupTest.php --filter="only staff"`. The rule passes.')
            ->commit('Add the members page')
            ->docs('https://laravel.com/docs/routing#view-routes', 'Laravel docs: View routes')
            ->check(Check::all(Check::route('admin.members.index'), Check::view('admin.members.index'))),

        Step::make('Search for members while staff type')
            ->run('php artisan make:livewire admin.member-search')
            ->explain(<<<'TEXT'
                Run the command below. It makes `resources/views/components/admin/⚡member-search.blade.php`. Put `<livewire:admin.member-search />` on the members page under your text. You built a component like this in the program search feature. Open `⚡program-list.blade.php` and use it as your map:

                1. `#[Url] public string $search = '';`. The address keeps the search, so `?search=priya` fills the box.
                2. `boot()` with `Gate::authorize('access-admin')`, so every request checks the user.
                3. A `#[Computed]` method `members()`. When the trimmed search is empty, return an empty collection. Otherwise, find the users:
                   - `whereHas('memberships', ...)` keeps users with at least one paid membership: `ends_on` is not `null`.
                   - `whereLike('name', ...)` or `whereLike('email', ...)` matches part of the name or the email. `whereLike()` ignores upper and lower case.
                   - Put the two `like` conditions in one `where(fn ...)` group. Without the group, the `or` would skip the membership condition.
                   - Load `latestMembership.plan`, sort by name and stop at 25 results.

                The view has a search field with `wire:model.live.debounce.300ms="search"` in a `<form method="GET">`, so Enter still works without JavaScript. Under it, a table with the name, the email, the plan, the end date and a badge. The membership is "Active" when `ends_on` is today or later. Otherwise it is "Expired". When the search finds nothing, show "No member matches" and the search text. Give each row a `:wire:key`.
                TEXT)
            ->think('Without the `where(fn ...)` group, the SQL is `has membership AND name LIKE ... OR email LIKE ...`. Which users does that find by mistake?')
            ->hint('`trim($this->search)` removes the spaces. An empty search shows only the box, so the desk does not list every member at once.')
            ->hint('`$member->latestMembership?->ends_on?->gte(today())` is `true` for an active membership.')
            ->hint('PHPStan may ask for the types of the closures. Type the parameter as `Illuminate\Database\Eloquent\Builder`.')
            ->code(<<<'PHP'
                /**
                 * The members whose name or email contains the search, with their latest membership.
                 *
                 * @return Collection<int, User>
                 */
                #[Computed]
                public function members(): Collection
                {
                    $search = trim($this->search);

                    if ($search === '') {
                        return new Collection;
                    }

                    return User::query()
                        ->whereHas('memberships', fn (Builder $query) => $query->whereNotNull('ends_on'))
                        ->where(fn (Builder $query) => $query
                            ->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%"))
                        ->with('latestMembership.plan')
                        ->orderBy('name')
                        ->limit(25)
                        ->get();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Membership::factory()->for(App\Models\User::firstWhere(\'email\', \'test@example.com\'))->create()`. Log in as `staff@example.com`, click "Members" and type `tes`. The test user, the Family plan, the end date and "Active" appear while you type. The address changes to `?search=tes`. Run `php artisan test tests/Feature/MemberLookupTest.php`. All the rules pass.')
            ->commit('Search for members while staff type')
            ->docs('/guide/reactive', 'BLSC guide: The reactive way')
            ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
            ->docs('https://laravel.com/docs/eloquent-relationships#querying-relationship-existence', 'Laravel docs: Querying relationship existence')
            ->docs('https://livewire.laravel.com/docs/4.x/url#basic-usage', 'Livewire docs: URL query parameters')
            ->check(Check::all(
                Check::fileContains($component, '#[Computed]'),
                Check::fileContains($component, 'latestMembership'),
                Check::fileContains($component, 'function boot'),
                Check::fileContains('resources/views/admin/members/index.blade.php', '<livewire:admin.member-search'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify member-lookup')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.
                TEXT)
            ->think('The centre has 5,000 members next year. Which part of the search gets slow? What could help?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for member lookup')
            ->check(Check::testPassed('MemberLookupTest')),
    ]);
