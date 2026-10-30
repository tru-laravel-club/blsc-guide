<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('member-pricing')
    ->title('Member pricing')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['membership-plans', 'registrations'])
    ->summary('Members get the member price of a session automatically. Parents do not confirm it by hand.')
    ->teaches(['Business rules in one class', 'Static methods', 'Dates in rules', 'Testing with travelTo'])
    ->test('MemberPricingTest.php')
    ->intro(<<<'TEXT'
        Each session has a price and an optional member price, such as $150.00 and $135.00. The program page shows both. But today every parent pays $150.00, because `Registration::priceCents()` returns the normal price. In this feature, a member pays the member price, and nobody has to check it by hand.

        The rule sounds simple: "members pay less". The details are where bugs hide. Which day counts? A parent registers in November for a club that starts in December. If the membership ends on November 30, the family is not a member when the club runs. If a renewal starts on November 20, the family is a member when the club runs, but not today. So the rule uses the first day of the session, not today.

        The price is a business rule, and the centre will add more rules. A sibling discount, an early-bird price or a staff price are all possible next year. So the rule lives in one class, `App\Pricing`, with one method: `Pricing::for(ProgramSession $session, ?User $user): int`. `Registration::priceCents()` calls it. The registration page calls it too, so the parent sees the price before they pay. When the centre adds a rule, a contributor changes one class.

        Follow the price through a request. The cart asks each item for `priceCents($user)` when it shows the total. Checkout asks again and copies the answer into each order item. The browser never sends a price. So the member price is safe from a changed form, and the order keeps the price that the parent saw.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
    ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time')
    ->reflect([
        'The centre adds a sibling discount: the second child in the same order pays 10% less. Where does it go? What does `Pricing::for()` need to know that it does not know today?',
        'Why does the rule use the first day of the session and not today? Describe one family that each choice treats unfairly.',
        'The order item copies the price at checkout. What happens to a paid order when the membership expires a week later? Why is that correct?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start member-pricing')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/MemberPricingTest.php` into the app.

                Read the dates in the test with care. `beforeEach()` sets today to November 15, 2026. Every session starts on December 1, 2026. Then look at the two rules about memberships that end and start between those days. In one rule, `activeMembership()` says "yes" today, but the parent pays the normal price. In the other, it says "no" today, but the parent pays the member price.

                Most rules go through the routes: a parent registers a child and checks out, and the test reads the order. One rule calls `Pricing::for()` directly. It fixes the name of the class and the method, so later features can use them.
                TEXT)
            ->think('The helper makes the program with `age_min` and `age_max` set to `null`. What would go wrong in this test without that?')
            ->tryIt('Run `php artisan test tests/Feature/MemberPricingTest.php`. Some rules fail, because members still pay $150.00 and `App\Pricing` does not exist.')
            ->commit('Add the member pricing acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time')
            ->check(Check::fileExists('tests/Feature/MemberPricingTest.php')),

        Step::make('Write the price rule in one class')
            ->explain(<<<'TEXT'
                Create `app/Pricing.php` in the `App` namespace, next to `Cart.php`. Give it one static method: `for(ProgramSession $session, ?User $user): int`.

                The method answers one question: what does this buyer pay for this session? Work it out in this order:

                1. If the session has no member price, everybody pays the normal price.
                2. If the buyer is a guest (`null`), or has no membership on the first day of the session, the buyer pays the normal price.
                3. Otherwise the buyer pays the member price.

                `User::activeMembership()` takes a date. Give it `$session->starts_on`. It ignores memberships that nobody has paid for, because they have no dates.

                Add one guard: a member never pays more than the normal price. Staff type both prices by hand, and a typing error must not punish members. `min()` does it.

                Why a static method? `Pricing` keeps no state. It reads the session and the user, and returns a number. `FormRules::for()` works in the same way.
                TEXT)
            ->think('`$user?->activeMembership(...)` uses `?->`. What does the expression give when `$user` is `null`?')
            ->hint('Return early: `if ($session->member_price_cents === null || $user?->activeMembership($session->starts_on) === null) { return $session->price_cents; }`.')
            ->code(<<<'PHP'
                namespace App;

                use App\Models\ProgramSession;
                use App\Models\User;

                /**
                 * Decides what a buyer pays for a session. Every price rule for sessions lives here.
                 */
                final class Pricing
                {
                    /**
                     * The price in cents for this buyer. A guest buyer is null.
                     */
                    public static function for(ProgramSession $session, ?User $user): int
                    {
                        if ($session->member_price_cents === null || $user?->activeMembership($session->starts_on) === null) {
                            return $session->price_cents;
                        }

                        return min($session->member_price_cents, $session->price_cents);
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Pricing::for(App\Models\ProgramSession::first(), null)`. You see the normal price of the first session in cents.')
            ->commit('Add the Pricing class')
            ->docs('https://www.php.net/manual/en/language.oop5.static.php', 'PHP manual: Static methods')
            ->docs('https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.nullsafe', 'PHP manual: The nullsafe operator')
            ->check(Check::fileContains('app/Pricing.php', 'function for')),

        Step::make('Let the registration ask for its price')
            ->explain(<<<'TEXT'
                Open `app/Models/Registration.php`. `priceCents(?User $user)` returns `$this->session->price_cents`. Change it to return `Pricing::for($this->session, $user)`.

                That is the whole change for the cart. The cart page, the total and checkout all call `priceCents()` with the user who is logged in. You do not touch them.

                Notice which user the cart passes: the parent who buys, not the child. A membership belongs to the account, so this is the right person.
                TEXT)
            ->think('A parent puts a registration in the cart, then buys a membership in the same cart. Which price does the registration get at checkout? Why?')
            ->hint('Import the class at the top with `use App\Pricing;`.')
            ->code(<<<'PHP'
                public function priceCents(?User $user): int
                {
                    return Pricing::for($this->session, $user);
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/MemberPricingTest.php`. The rules about orders pass now. One rule still fails: the registration page does not show the price yet.')
            ->commit('Use member prices for registrations')
            ->check(Check::fileContains('app/Models/Registration.php', 'Pricing::for')),

        Step::make('Show the price on the registration page')
            ->explain(<<<'TEXT'
                A parent must see the price before they answer the form. In `RegistrationController::create()`, pass `Pricing::for($session, $request->user())` to the view as `price`.

                In `registrations/create.blade.php`, add a line under the header: "Your price: $135.00 (the member price)". Show "(the member price)" only when the price is lower than the normal price. Use the `<x-money>` component to format the cents.

                Do not compute the price in the view. If the view had its own copy of the rule, the page and the cart could disagree.
                TEXT)
            ->think('The program page shows both prices to everybody. The registration page shows one price to one parent. Why does each page show what it shows?')
            ->hint('`<x-money :cents="$price" />` shows `$135.00` for `13500`.')
            ->code(<<<'PHP'
                <p>
                    Your price: <span class="font-medium"><x-money :cents="$price" /></span>
                    @if ($price < $session->price_cents)
                        (the member price)
                    @endif
                </p>
                PHP)
            ->tryIt(<<<'TEXT'
                Log in as `test@example.com` and open a club registration page. You see the normal price.

                Then buy a membership on http://localhost:8000/memberships and pay with the fake gateway. Open the registration page again. You see the member price. Register a child, and the cart shows the same price.
                TEXT)
            ->commit('Show the price on the registration page')
            ->check(Check::all(
                Check::fileContains('app/Http/Controllers/RegistrationController.php', 'Pricing::for'),
                Check::fileContains('resources/views/registrations/create.blade.php', '$price'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify member-pricing')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                `RegistrationsTest` must still pass. Its sessions have no member price, so the price there does not change.

                In your pull request, name the rule in one sentence: "A member pays the member price when the membership is active on the first day of the session." Reviewers check the code against that sentence.
                TEXT)
            ->think('PHPStan reads `activeMembership(?CarbonInterface $on)`. Why does `$session->starts_on` fit that type, even though the app uses immutable dates?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for member pricing')
            ->check(Check::testPassed('MemberPricingTest')),
    ]);
