<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('membership-plans')
    ->title('Membership plans')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['cart-orders'])
    ->summary('Staff manage membership plans. Families buy and renew memberships online. Each membership has an end date, and a renewal starts when the current membership ends.')
    ->teaches(['Implementing an interface', 'Date casts and date math', 'Dynamic scopes', 'Time travel in tests', 'Checkbox fields'])
    ->test('MembershipPlansTest.php')
    ->intro(<<<'TEXT'
        A family membership lets a family visit all year, and later it gives member prices for programs. The centre sells a few plans, such as Family and Individual. Next year it will add or change plans, so the plans are data that staff manage, not code.

        A `MembershipPlan` is what staff sell: a name, a description, a price, a length in months, a position and an "active" flag. A `Membership` is one family's membership: a user, a plan, a start date and an end date. It implements `Purchasable`, so it goes through the cart from Cart and orders.

        This is the path of a purchase. A logged-in parent clicks "Add to cart" on the Memberships page. The app makes an unpaid `Membership` with no dates and adds it to the cart. When the order is paid, `fulfil()` gives the membership its dates. A membership without dates is not paid, so it is never active.

        Renewals follow one rule. If the family has a membership that ends today or later, the new one starts the day after it ends. Otherwise it starts today. So a family that renews early loses no days.

        Other features ask one question: "Is this user a member on this day?" `User::activeMembership(?CarbonInterface $on = null)` answers it. Member pricing and Member lookup use it, so keep its name and parameters.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
    ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
    ->reflect([
        'The centre adds a "Grandparent" plan. Does it need a developer? What if it wants a plan that lasts until a fixed date, such as August 31?',
        'Why does a membership get its dates in `fulfil()`, and not when the parent adds it to the cart?',
        '`activeMembership()` takes a date. Which later feature needs a date other than today? Why?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start membership-plans')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/MembershipPlansTest.php` into the app. Read the whole test first.

                Look at `beforeEach()`. `$this->travelTo()` sets "now" to November 15, 2026, for every rule. Date rules need a fixed day. Without it, a rule that passes today can fail next month.

                The helper `buyMembership()` does the whole purchase: add to cart, check out and pay with the fake gateway. The renewal rules give the parent an old membership first, then buy, and check the dates of the new one.
                TEXT)
            ->think('A 12-month membership starts on November 15, 2026. Which day is its last day? Why is it not November 15, 2027?')
            ->tryIt('Run `php artisan test tests/Feature/MembershipPlansTest.php`. All the rules fail, because the `MembershipPlan` model does not exist yet.')
            ->commit('Add the membership plans acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
            ->check(Check::fileExists('tests/Feature/MembershipPlansTest.php')),

        Step::make('Create the plans and memberships tables')
            ->run("php artisan make:model MembershipPlan -mf\nphp artisan make:model Membership -mf")
            ->explain(<<<'TEXT'
                Run the two commands below. Fill in both migrations, then run `php artisan migrate`.

                A plan has a `name`, a nullable `description`, a `price_cents`, a length in `months`, an `is_active` flag and a `position`. Each value is a column because the code reads it: the price for the cart, the months for the end date, the flag and the position for the public page.

                A membership has a `user_id`, a `membership_plan_id`, a `starts_on` and an `ends_on`. Use `date()` columns: a membership is about days, not hours. Both dates are nullable, because an unpaid membership has no dates yet.

                Staff never delete a plan that families bought. So `membership_plan_id` uses `restrictOnDelete()`. Add an index on `user_id` and `ends_on`. The app often asks for the memberships of one user by end date.
                TEXT)
            ->think('Why does a membership store its end date, when the app could add the months of the plan to the start date?')
            ->hint('`$table->boolean(\'is_active\')->default(true);` makes a new plan active.')
            ->hint('`$table->index([\'user_id\', \'ends_on\']);` adds one index on both columns.')
            ->code(<<<'PHP'
                Schema::create('memberships', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('membership_plan_id')->constrained()->restrictOnDelete();
                    $table->date('starts_on')->nullable();
                    $table->date('ends_on')->nullable();
                    $table->timestamps();

                    $table->index(['user_id', 'ends_on']);
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'membership_plans\')`. You see your columns.')
            ->commit('Add the membership plans and memberships tables')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->check(Check::all(
                Check::table('membership_plans', ['name', 'description', 'price_cents', 'months', 'is_active', 'position']),
                Check::table('memberships', ['user_id', 'membership_plan_id', 'starts_on', 'ends_on']),
            )),

        Step::make('Fill in the plan model and the factories')
            ->explain(<<<'TEXT'
                Open `app/Models/MembershipPlan.php`. Put the six columns in `#[Fillable]`, cast `is_active` to a boolean, and add `memberships(): HasMany`. Add a `forSale` scope: active plans only, sorted by `position` and then by `name`.

                Open `app/Models/Membership.php`. Put only `membership_plan_id` in `#[Fillable]`. The dates come from `fulfil()`, never from a form. Cast both dates with `date`. Add `user(): BelongsTo` and `plan(): BelongsTo`. The relationship is called `plan`, not `membershipPlan`, so pass the column `membership_plan_id` as the second argument.

                In `MembershipPlanFactory`, make a Family plan for $95.00 and 12 months. In `MembershipFactory`, make a paid membership that is active today: it started a month ago and ends in 11 months. Add an `expired()` state and an `unpaid()` state with no dates.
                TEXT)
            ->think('The test writes `Membership::factory()->for($plan, \'plan\')`. Why does it name the relationship?')
            ->hint('Look at `Category::ordered()`. `forSale` is the same kind of scope with one more `where`.')
            ->hint('`$this->belongsTo(MembershipPlan::class, \'membership_plan_id\')` names the column, because Laravel would guess `plan_id` from the method name.')
            ->tryIt('In `php artisan tinker`, run `App\Models\Membership::factory()->create()->plan->name`. You see "Family".')
            ->commit('Fill in the membership models and factories')
            ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::all(
                Check::fileContains('app/Models/MembershipPlan.php', 'forSale'),
                Check::fileContains('app/Models/Membership.php', 'function plan'),
                Check::fileContains('database/factories/MembershipFactory.php', 'function expired'),
            )),

        Step::make('Ask: is this user a member on this day?')
            ->explain(<<<'TEXT'
                Add an `activeOn` scope to `Membership`. It takes a day and keeps the memberships whose `starts_on` is on or before the day and whose `ends_on` is on or after it. A scope with a parameter is a dynamic scope. An unpaid membership has no dates, so it never matches.

                In `app/Models/User.php`, add `memberships(): HasMany` and `activeMembership(?CarbonInterface $on = null): ?Membership`. It uses `activeOn($on ?? today())` and returns the membership that ends last, with its plan loaded.

                Type the parameter as `Carbon\CarbonInterface`, not as one Carbon class. The app uses `CarbonImmutable` for dates (look at `AppServiceProvider`), and a date cast gives you that class. The interface accepts every kind of Carbon date.
                TEXT)
            ->think('Member pricing asks "is this user a member on the first day of the session?". Why can it not ask "is this user a member today?"')
            ->hint('`whereDate(\'starts_on\', \'<=\', $day)` compares only the date part.')
            ->hint('`->latest(\'ends_on\')->first()` returns the membership with the latest end date.')
            ->code(<<<'PHP'
                /**
                 * The membership that is active on this day (today by default), or null.
                 */
                public function activeMembership(?CarbonInterface $on = null): ?Membership
                {
                    return $this->memberships()
                        ->activeOn($on ?? today())
                        ->with('plan')
                        ->latest('ends_on')
                        ->first();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$m = App\Models\Membership::factory()->create(); $m->user->activeMembership()?->id === $m->id`. You see `true`. Then run `$m->user->activeMembership(today()->addYears(2))`. You see `null`.')
            ->commit('Add User::activeMembership()')
            ->docs('https://laravel.com/docs/eloquent#dynamic-scopes', 'Laravel docs: Dynamic scopes')
            ->docs('https://laravel.com/docs/helpers#method-today', 'Laravel docs: The today() helper')
            ->check(Check::fileContains('app/Models/User.php', 'function activeMembership')),

        Step::make('Make a membership purchasable')
            ->explain(<<<'TEXT'
                Add `implements Purchasable` to `Membership` and write the four methods.

                `orderDescription()` returns the plan name and its length, such as "Family membership, 12 months". `priceCents()` returns the plan price. `confirmationLine()` returns the plan name and both dates.

                `fulfil()` gives the membership its dates. If it already has a start date, it does nothing. Otherwise, find the latest `ends_on` of the user's memberships. If that day is today or later, start the day after it. If not, start today. The end date is the start date plus the months of the plan, minus one day.

                The dates are `CarbonImmutable`, so `addDay()` returns a new date and does not change the old one. With a mutable date, `$startsOn->addMonths()` would also move the start date.
                TEXT)
            ->think('A membership starts on January 31 and lasts one month. Which end date do you want? Look up `addMonthsNoOverflow()`.')
            ->hint('`$this->user->memberships()->max(\'ends_on\')` returns the latest end date as text, or `null`. `max()` ignores memberships without dates.')
            ->hint('`Date::parse($currentEnd)->addDay()` turns the text into a date and adds one day. `Date` is `Illuminate\Support\Facades\Date`.')
            ->code(<<<'PHP'
                public function fulfil(): void
                {
                    if ($this->starts_on !== null) {
                        return;
                    }

                    $currentEnd = $this->user->memberships()->max('ends_on');

                    $startsOn = $currentEnd !== null && today()->lte($currentEnd)
                        ? Date::parse($currentEnd)->addDay()
                        : today();

                    $this->forceFill([
                        'starts_on' => $startsOn,
                        'ends_on' => $startsOn->addMonthsNoOverflow($this->plan->months)->subDay(),
                    ])->save();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$m = App\Models\Membership::factory()->unpaid()->create(); $m->fulfil(); $m->confirmationLine()`. You see "Family membership:" with today and the day before the same date next year.')
            ->commit('Make memberships purchasable')
            ->docs('https://laravel.com/docs/queries#aggregates', 'Laravel docs: Aggregates')
            ->check(Check::fileContains('app/Models/Membership.php', 'implements Purchasable')),

        Step::make('Sell memberships on a public page')
            ->run('php artisan make:controller MembershipController')
            ->explain(<<<'TEXT'
                Add `GET /memberships` named `memberships.index` for everyone, and `POST /memberships` named `memberships.store` with the `auth` middleware. A membership belongs to a family, so the buyer needs an account. A guest who posts the form goes to the login page.

                `index()` passes the plans `forSale()` and the user's `activeMembership()`. A guest has no user, so use `$request->user()?->activeMembership()`. The view shows each plan with its price, length and description. A logged-in parent sees "Add to cart". A guest sees "Log in to buy". When the parent is a member, show "Your Family membership is active until" and the end date.

                `store()` validates `membership_plan_id`: it must exist and be active. Then it makes an unpaid membership with `$request->user()->memberships()->create([...])`, adds it to the cart, and redirects to the cart.

                Add a "Memberships" link to the Explore menu in `resources/views/layouts/site.blade.php`.
                TEXT)
            ->think('The form sends only the plan ID. Where does the price come from? Where does the user come from?')
            ->hint('`Rule::exists(\'membership_plans\', \'id\')->where(\'is_active\', true)` refuses an inactive plan with a validation error.')
            ->hint('`->middleware(\'auth\')` on one route protects only that route.')
            ->code(<<<'PHP'
                $validated = $request->validate([
                    'membership_plan_id' => ['required', 'integer', Rule::exists('membership_plans', 'id')->where('is_active', true)],
                ]);

                $membership = $request->user()->memberships()->create([
                    'membership_plan_id' => $validated['membership_plan_id'],
                ]);

                $cart->add($membership);
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\MembershipPlan::create(['name' => 'Family', 'price_cents' => 9500, 'months' => 12])`.

                Open http://localhost:8000/memberships while you are logged out. You see "Family", $95.00 and "Log in to buy". Log in as `test@example.com`, add the plan to the cart, check out and approve the test payment. Open the Memberships page again. You see "Your Family membership is active until" and a date next year.
                TEXT)
            ->commit('Sell memberships on the public site')
            ->docs('https://laravel.com/docs/validation#rule-exists', 'Laravel docs: The exists rule')
            ->docs('https://laravel.com/docs/authentication#protecting-routes', 'Laravel docs: Protecting routes')
            ->check(Check::all(Check::route('memberships.index', 'memberships.store'), Check::view('memberships.index'))),

        Step::make('Let staff manage the plans')
            ->run("php artisan make:controller Admin/MembershipPlanController --resource --model=MembershipPlan\nphp artisan make:request MembershipPlanRequest")
            ->explain(<<<'TEXT'
                Add `Route::resource('membership-plans', ...)->except(['show', 'destroy'])` in the staff group. Staff do not delete a plan. Families bought it, and their memberships point to it. To stop selling a plan, staff untick "Sell this plan on the public site".

                A checkbox that is not ticked sends nothing. So `MembershipPlanRequest::prepareForValidation()` merges `is_active` with `$this->boolean('is_active')`. The last rule of the test sends no `is_active`, and the plan must stop selling.

                Staff type the price in dollars. Write `planData()`, which turns `price` into `price_cents`, as `ProgramSessionRequest::sessionData()` does. Copy the four views from the category admin and add a "Membership plans" link to the staff menu.
                TEXT)
            ->think('Staff change the price of the Family plan from $95 to $100. What happens to the memberships that families already bought? What happens to a membership that is in a cart?')
            ->hint('Look at `ProgramRequest`. It does the same with `is_published`.')
            ->hint('In the form, put `<input type="hidden" name="is_active" value="0">` before the checkbox, as the program form does.')
            ->tryIt('Log in as `staff@example.com` and click "Membership plans". Create "Grandparent" for $80.00 and 12 months. Open the Memberships page. You see the new plan, with no code change.')
            ->commit('Add the membership plan admin')
            ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::all(Check::route('admin.membership-plans.index', 'admin.membership-plans.update'), Check::view('admin.membership-plans.index'))),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify membership-plans')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                If a renewal rule fails by one day, check two things: the "minus one day" on the end date, and the `lte()` comparison with today.
                TEXT)
            ->think('A membership ends on December 31. The parent renews on December 31. On which day does the new membership start? Is that what the family expects?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for membership plans')
            ->check(Check::testPassed('MembershipPlansTest')),
    ]);
