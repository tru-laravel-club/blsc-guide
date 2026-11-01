<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('visit-us')
    ->title('Visit Us page')
    ->section(Section::Foundation)
    ->level(1)
    ->needs(['layout'])
    ->summary('Hours, location and admission rates on one public page, linked from the Explore menu and the home page.')
    ->teaches(['Route::view', 'Blade loops', 'Arrays as data', 'April UI cards and tables'])
    ->test('VisitUsPageTest.php')
    ->intro(<<<'TEXT'
        Most visitors want three answers before they come: when is the centre open, where is it, and what does it cost? In this feature, you build the Visit Us page that answers them.

        The page shows fixed text. It needs no database, so it needs no model and no controller. One route shows one view. This is the smallest kind of page in Laravel, so it is a good first feature.

        The admission rates are a list: a ticket and a price on each line. You keep the list as a PHP array at the top of the view, and a loop makes the table. To change a price, staff ask a contributor to change one line.

        Last, you make the page easy to find. You add it to the Explore menu of the layout and to the home page. Every public page that you build later follows the same pattern.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
    ->docs('https://laravel.com/docs/routing#view-routes', 'Laravel docs: View routes')
    ->reflect([
        'BLSC wants staff to change the hours and the prices from an admin page. What must change in this feature? Which parts can stay?',
        'The hours are in the footer and on this page. What can go wrong? How would you keep them in one place?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start visit-us')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/VisitUsPageTest.php` into the app.

                Read the whole test before you write code. It names the route, `visit-us`, and the text that the page must show. It also checks that visitors can find the page: the Explore menu and the home page link to it.
                TEXT)
            ->think('The test asks for `route(\'visit-us\')`, not for the address `/visit-us`. What does that let a maintainer change later without a change to the test?')
            ->tryIt('Run `php artisan test tests/Feature/VisitUsPageTest.php`. All three rules fail, because the page, the route and the links do not exist yet.')
            ->commit('Add the Visit Us acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/VisitUsPageTest.php')),

        Step::make('Add the route and the view')
            ->run('php artisan make:view visit-us')
            ->explain(<<<'TEXT'
                Open `routes/web.php`. The home page uses `Route::view()`: a URL, a view and a name, with no controller. Add the same kind of route for `/visit-us`, with the view `visit-us` and the name `visit-us`.

                Make the view with the command below, and delete the `<div>` that it writes. Wrap the page in the layout, and give the layout a title: `<x-layouts::site title="Visit Us">`. The title shows in the browser tab.

                Inside, add a `<header>` with an `<h1>` "Visit Us" and one sentence under it, such as "Drop in any time we are open. You do not need to book ahead." Put the content in a `<div>` with the same width and padding as the home page, so the pages line up.
                TEXT)
            ->think('The home page and this page both use `Route::view()`. When does a page need a controller instead?')
            ->hint('The route reads like the home route: `Route::view(\'/visit-us\', \'visit-us\')->name(\'visit-us\');`.')
            ->hint('`mx-auto max-w-6xl space-y-10 px-4 py-12 sm:px-6` centres the content and adds space between the parts.')
            ->code(<<<'BLADE'
                <x-layouts::site title="Visit Us">
                    <div class="mx-auto max-w-6xl space-y-10 px-4 py-12 sm:px-6">
                        <header class="max-w-3xl space-y-3">
                            <h1 class="text-4xl font-semibold tracking-tight">Visit Us</h1>
                            <p class="text-lg text-muted-foreground">Drop in any time we are open. You do not need to book ahead.</p>
                        </header>
                    </div>
                </x-layouts::site>
                BLADE)
            ->tryIt('Run `php artisan route:list --name=visit-us`. You see the route. Open http://localhost:8000/visit-us. You see the heading inside the header and the footer of the layout. The browser tab says "Visit Us".')
            ->commit('Add the Visit Us route and view')
            ->docs('https://laravel.com/docs/routing#named-routes', 'Laravel docs: Named routes')
            ->check(Check::all(Check::route('visit-us'), Check::view('visit-us'))),

        Step::make('Show the hours and the location')
            ->explain(<<<'TEXT'
                Under the header, show two cards side by side. April UI has a card component: `<april:card>`. It has named slots, such as `<slot:title>` and `<slot:content>`.

                - The "Hours" card: Tuesday – Saturday, 9:30 AM – 5:00 PM. Closed Sunday, Monday and holidays.
                - The "Location" card: 458 Seymour Street, Kamloops, BC V2C 2G6, and the phone number as a `tel:` link, as in the footer.

                A grid with two columns puts the cards side by side on a wide screen. On a phone, they stack.
                TEXT)
            ->think('The phone number is a link in the footer and on this page. A visitor on a laptop clicks it. What happens?')
            ->hint('`<div class="grid gap-6 md:grid-cols-2">` holds the two cards.')
            ->hint('The card component is in `vendor/yungifez/april-ui/resources/views/components/card.blade.php`. Its `@props` and slots show what it takes.')
            ->code(<<<'BLADE'
                <april:card>
                    <slot:title class="text-xl">Hours</slot:title>
                    <slot:content class="space-y-2 text-sm">
                        <p>Tuesday – Saturday, 9:30 AM – 5:00 PM</p>
                        <p class="text-muted-foreground">Closed Sunday, Monday and holidays.</p>
                    </slot:content>
                </april:card>
                BLADE)
            ->tryIt('Open http://localhost:8000/visit-us. You see the two cards side by side. Make the window narrow. The cards stack.')
            ->commit('Show the hours and the location')
            ->docs('https://tailwindcss.com/docs/grid-template-columns', 'Tailwind docs: Grid columns')
            ->check(Check::fileContains('resources/views/visit-us.blade.php', '458 Seymour Street')),

        Step::make('Show the admission rates from an array')
            ->explain(<<<'TEXT'
                The admission rates are six lines:

                - Toddler (0–2): Free
                - Youth (3–15): $4.00
                - Adult (16+): $7.00
                - Senior (60+): $5.00
                - Family: $18.00
                - Members: Free

                Do not write six table rows by hand. Put the rates in a `$rates` array in a `@php` block at the top of the view. Each item is a small array with the ticket and the price. Then a `@foreach` makes one row for each item. The HTML of a row is written once, and the data is easy to read and change.

                Show the table in a `<section>` with the heading "Admission". April UI has a table component: `<april:data-table>`, with `<april:data-table-row>`, `<april:data-table-head>` and `<april:data-table-cell>`. Under the table, add the rule for children: "Children under 16 must be with an adult. Bring 1 adult for every 5 children aged 5 and under, and 1 adult for every 10 children aged 6 to 15."
                TEXT)
            ->think('The family price changes to $20.00. How many lines change? And if each row were written by hand?')
            ->hint('`[\'Family\', \'$18.00\']` is one item. `@foreach ($rates as [$ticket, $price])` takes the two values out of each item.')
            ->hint('The table has two slots: `<slot:header>` for the head row and `<slot:body>` for the loop.')
            ->code(<<<'BLADE'
                @php
                    $rates = [
                        ['Toddler (0–2)', 'Free'],
                        ['Youth (3–15)', '$4.00'],
                        // ...
                    ];
                @endphp

                {{-- In the Admission section --}}
                <april:data-table class="max-w-xl">
                    <slot:header>
                        <april:data-table-row>
                            <april:data-table-head>Ticket</april:data-table-head>
                            <april:data-table-head>Price</april:data-table-head>
                        </april:data-table-row>
                    </slot:header>
                    <slot:body>
                        @foreach ($rates as [$ticket, $price])
                            <april:data-table-row>
                                <april:data-table-cell>{{ $ticket }}</april:data-table-cell>
                                <april:data-table-cell>{{ $price }}</april:data-table-cell>
                            </april:data-table-row>
                        @endforeach
                    </slot:body>
                </april:data-table>
                BLADE)
            ->tryIt('Open http://localhost:8000/visit-us. You see the table with six rows. Change "$18.00" to "$20.00" in the array, and reload. The Family row changes. Change it back.')
            ->commit('Show the admission rates')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->docs('https://www.php.net/manual/en/control-structures.foreach.php#control-structures.foreach.list', 'PHP manual: Unpacking nested arrays with list()')
            ->check(Check::fileContains('resources/views/visit-us.blade.php', '@foreach')),

        Step::make('Add the page to the menu and the home page')
            ->explain(<<<'TEXT'
                A page that nobody can find does not help. Link to it from two places.

                First, the menu. Open `resources/views/layouts/site.blade.php`. The `$menus` array is empty. Add an "Explore" menu with one link: the label "Visit Us", the route `visit-us` and the description "Hours, admission and how to find us." You write no HTML. The loop in the layout makes the link.

                Second, the home page. Open `resources/views/home.blade.php`. Under the welcome text, add a "Plan your visit" button: an `<april:button-link>` to `route('visit-us')` with `size="lg"`.

                Then add a row of cards under the welcome section. Each card is a link with a title and one sentence. Write the cards as data, as you did for the rates: an array of `[title, description, url]` items and a `@foreach`. Start with one card: "Visit Us", "Open Tuesday to Saturday. Members get in free." The Camps and clubs feature adds two more cards to the same array.

                If the Instant navigation feature is done, give the button and the card links `wire:navigate`: every new link to a page of the site uses it.
                TEXT)
            ->think('You added a page to the menu without any HTML. Why does that matter when the menu has twenty links?')
            ->hint('The menu entry is `\'Explore\' => [[\'label\' => \'Visit Us\', \'route\' => \'visit-us\', \'description\' => \'...\']]`. Note the two `[[`: a menu holds a list of links.')
            ->hint('Make the whole card a link: `<a href="{{ $url }}" class="group block">` around an `<april:card>`. `group-hover:bg-accent` on the card colours it when the mouse is on the link.')
            ->code(<<<'BLADE'
                <section class="mx-auto grid max-w-6xl gap-4 px-4 pb-20 sm:px-6 md:grid-cols-3">
                    @foreach ([
                        ['Visit Us', 'Open Tuesday to Saturday. Members get in free.', route('visit-us')],
                    ] as [$title, $description, $url])
                        <a href="{{ $url }}" class="group block">
                            <april:card class="h-full transition-colors group-hover:bg-accent">
                                <slot:title class="text-xl">{{ $title }}</slot:title>
                                <slot:content><p class="text-sm leading-6 text-muted-foreground">{{ $description }}</p></slot:content>
                            </april:card>
                        </a>
                    @endforeach
                </section>
                BLADE)
            ->tryIt('Open http://localhost:8000. Click "Explore" in the header. You see "Visit Us" with its description. Click it. On the Visit Us page, open the menu again. The link has a background, because it is the current page.')
            ->commit('Link to the Visit Us page')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->check(Check::all(
                Check::fileContains('resources/views/layouts/site.blade.php', "'visit-us'"),
                Check::fileContains('resources/views/home.blade.php', "route('visit-us')"),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify visit-us')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                A good test fails when the rule is broken. Prove it for one rule. Remove the Family line from `$rates`, and run the test again. A rule fails. Put the line back.

                In your pull request, add a screenshot of the page on a wide screen and on a phone. Most visitors use a phone.
                TEXT)
            ->think('The test checks the text "Family" and "$18.00". What does it not check about the table? Is that a problem?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for the Visit Us page')
            ->check(Check::testPassed('VisitUsPageTest')),
    ]);
