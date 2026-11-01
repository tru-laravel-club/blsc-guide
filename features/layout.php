<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('layout')
    ->title('Site layout and menus')
    ->section(Section::Foundation)
    ->level(1)
    ->summary('The frame of every public page: a header with the logo, the menus and the account links, and a footer with the address, hours and contact details.')
    ->teaches(['Blade components', 'Layouts', 'Slots', 'Blade loops', '@auth'])
    ->test('LayoutTest.php')
    ->intro(<<<'TEXT'
        Open http://localhost:8000. The home page is one plain page. It has no header, no menu and no footer. In this feature, you build the frame that every public page uses. Then you put the home page inside it.

        The frame is a layout: one Blade file, `resources/views/layouts/site.blade.php`. A page wraps its content in `<x-layouts::site>`. The layout shows the header, then the content of the page where it says `{{ $slot }}`, then the footer. So all pages look the same, and a change to the footer changes every page at once.

        The starter kit already has a layout for the account pages: `resources/views/layouts/app.blade.php`, with the sidebar. Read it before you start. Your layout uses the same `<head>`, the same April UI components and the same scripts.

        The menus are not written link by link. They come from a PHP array at the top of the layout, and a loop makes the links. When a later feature adds a public page, it adds one entry to that array. The array is empty when you finish this feature, because no public page exists yet. The Visit Us feature adds the first link.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
    ->docs('https://laravel.com/docs/blade#layouts-using-components', 'Laravel docs: Layouts using components')
    ->docs('https://laravel.com/docs/blade#slots', 'Laravel docs: Slots')
    ->reflect([
        'The menus come from an array, not from HTML link by link. What does the next contributor change to add a page to a menu?',
        'The header shows "Log in" to a guest and "My account" to a signed-in person. Which line decides that? Where else could the same check be useful?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start layout')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/LayoutTest.php` into the app.

                Read the whole test before you write code. Each `test()` is one rule that the page must follow. The test sends a request to the home page and reads the HTML that comes back. It looks for the header, the footer and the links in them. It does not open a browser.

                The test names exact text, such as the address and the phone number. Use the same text in your layout.
                TEXT)
            ->think('The test checks for `href="tel:+12505542572"`, not only for the phone number. Why is a `tel:` link better than plain text for a visitor on a phone?')
            ->tryIt('Run `php artisan test tests/Feature/LayoutTest.php`. All the rules fail, because the home page has no header and no footer yet.')
            ->commit('Add the site layout acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/LayoutTest.php')),

        Step::make('Make the layout file')
            ->run('php artisan make:view layouts.site')
            ->explain(<<<'TEXT'
                Make the layout with the command below. The dot in `layouts.site` means a folder, so the file is `resources/views/layouts/site.blade.php`. The command writes a `<div>` with a quote in it. Delete it.

                A layout is a full HTML page with one gap in the middle. Write the outer parts first. Copy them from `resources/views/layouts/app.blade.php`:

                1. `<!DOCTYPE html>` and the `<html>` tag with its `lang` attribute.
                2. The `<head>`. It only includes `partials.head`, which loads the title, the styles and the scripts.
                3. A `<body>` with the same classes, plus `flex flex-col`. Then the footer stays at the bottom of a short page.
                4. A `<main class="flex-1">` with `{{ $slot }}` inside. The page content goes there.
                5. `@aprilScripts` and `@livewireScripts` at the end of the body.

                The name `<x-layouts::site>` comes from the path. The `layouts::` prefix points to the `resources/views/layouts` folder, and `site` is the file name. A Blade file in that folder is a component, and `{{ $slot }}` shows whatever the page puts between the opening and the closing tag.
                TEXT)
            ->think('Each page also gives the layout a title, such as `<x-layouts::site title="Visit Us">`. Open `resources/views/partials/head.blade.php`. Where does the title go, and what shows when a page gives no title?')
            ->hint('Open `resources/views/layouts/app.blade.php`. Keep the first five lines and the last four lines. Replace everything between `<body>` and `@aprilScripts` with your `<main>`.')
            ->code(<<<'BLADE'
                <!DOCTYPE html>
                <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
                    <head>
                        @include('partials.head')
                    </head>
                    <body class="flex min-h-full flex-col bg-background text-foreground antialiased">
                        <main class="flex-1">
                            {{ $slot }}
                        </main>

                        @aprilScripts
                        @livewireScripts
                    </body>
                </html>
                BLADE)
            ->tryIt('Run `php artisan view:cache`. Blade compiles every view, and you see no error. Run `php artisan view:clear` after it.')
            ->commit('Add the public layout')
            ->docs('https://laravel.com/docs/blade#layouts-using-components', 'Laravel docs: Layouts using components')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->check(Check::view('layouts.site')),

        Step::make('Put the home page in the layout')
            ->explain(<<<'TEXT'
                Open `routes/web.php`. The first route is `Route::view('/', 'home')->name('home')`. It shows the view `resources/views/home.blade.php` with no controller, because the home page needs no data from the database.

                Open `home.blade.php`. It is a full HTML page of its own, with a `<head>` and scripts. The layout has those parts now. So remove them from the home page, and wrap the content in `<x-layouts::site>`.

                Write a short welcome for the science centre: a heading, such as "Explore, build and discover at the BIG Little Science Centre.", and one sentence about what visitors find there. Put it in a `<section>` with a maximum width and some padding, so the text does not touch the edges of the screen. An `<april:badge>` above the heading can say "Kamloops' hands-on science centre".

                Remove the Log in, Register and Dashboard buttons. The header shows those links in a later step, on every page.
                TEXT)
            ->think('The home page shows no data from the database today. When would it need a controller instead of `Route::view()`?')
            ->hint('The file starts with `<x-layouts::site>` and ends with `</x-layouts::site>`. Everything between them goes into `{{ $slot }}`.')
            ->hint('`mx-auto max-w-6xl px-4 py-16 sm:px-6` centres the section, limits its width and adds padding.')
            ->code(<<<'BLADE'
                <x-layouts::site>
                    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-24">
                        <div class="max-w-3xl">
                            <april:badge variant="secondary">Kamloops' hands-on science centre</april:badge>
                            <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-6xl">Explore, build and discover at the BIG Little Science Centre.</h1>
                            <p class="mt-6 max-w-2xl text-lg leading-8 text-muted-foreground">
                                Hundreds of hands-on exhibits, Saturday science shows, after-school clubs and camps for curious people of every age.
                            </p>
                        </div>
                    </section>
                </x-layouts::site>
                BLADE)
            ->tryIt('Run `composer dev` and open http://localhost:8000. You see your welcome text. Open the page source in the browser (Ctrl+U). The `<head>` and the scripts come from the layout.')
            ->commit('Put the home page in the public layout')
            ->docs('https://laravel.com/docs/routing#view-routes', 'Laravel docs: View routes')
            ->check(Check::fileContains('resources/views/home.blade.php', '<x-layouts::site')),

        Step::make('Add the footer')
            ->explain(<<<'TEXT'
                Every page shows the same footer, so it goes in the layout, after `<main>`. It has three columns:

                1. The name of the centre and its address: 458 Seymour Street, Kamloops, BC V2C 2G6. Use `{{ config('app.name') }}` for the name, so it comes from one place.
                2. The hours: Tuesday – Saturday, 9:30 AM – 5:00 PM. Closed Sunday, Monday and holidays.
                3. The contact details: the phone number (250) 554-2572 and the email address info@blscs.org.

                Make the phone number and the email address links. `href="tel:+12505542572"` starts a call on a phone. `href="mailto:info@blscs.org"` opens an email. The test checks both.

                Use a `<footer>` tag, not a `<div>`. Screen readers and the test both find the footer by its tag.
                TEXT)
            ->think('The hours are in the footer and later on the Visit Us page too. What goes wrong when the centre changes its hours? How could you keep the hours in one place?')
            ->hint('A grid makes the three columns: `grid gap-8 sm:grid-cols-3`. On a small screen, the columns stack.')
            ->hint('Look at the test for the exact text of the hours. The dash is an en dash (–), not a hyphen (-).')
            ->code(<<<'BLADE'
                <footer class="border-t bg-muted/40">
                    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-3 sm:px-6">
                        <div class="space-y-1">
                            <p class="font-semibold">{{ config('app.name') }}</p>
                            <p class="text-muted-foreground">458 Seymour Street<br>Kamloops, BC V2C 2G6</p>
                        </div>
                        {{-- The hours and the contact columns look the same. --}}
                        <div class="space-y-1">
                            <p class="font-semibold">Contact</p>
                            <p class="text-muted-foreground">
                                <a href="tel:+12505542572" class="hover:underline">(250) 554-2572</a><br>
                                <a href="mailto:info@blscs.org" class="hover:underline">info@blscs.org</a>
                            </p>
                        </div>
                    </div>
                </footer>
                BLADE)
            ->tryIt('Open http://localhost:8000 and scroll down. You see the footer. Make the browser window narrow. The three columns stack.')
            ->commit('Add the footer to the public layout')
            ->docs('https://tailwindcss.com/docs/grid-template-columns#responsive-design', 'Tailwind docs: Grid columns on different screens')
            ->docs('https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/a#linking_to_telephone_numbers', 'MDN: Links to telephone numbers')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', 'mailto:info@blscs.org')),

        Step::make('Add the header with the logo and the account links')
            ->explain(<<<'TEXT'
                The header goes before `<main>`. It has the logo on the left and the account links on the right. The menus go between them in the next step.

                The starter kit has a logo component: `<x-app-logo>`. Give it `href="{{ route('home') }}"`, so a click on the logo opens the home page.

                The account links depend on the visitor. Blade has two directives for this. `@auth ... @else ... @endauth` shows the first part to a signed-in person and the second part to a guest.

                - A signed-in person sees "My account", a link to the `dashboard` route.
                - A guest sees "Log in", a link to the `login` route, and "Create account", a link to the `register` route.

                Use `<april:button-link>` for the links. It looks like a button, but it is a normal link. Push the links to the right with `ml-auto`.
                TEXT)
            ->think('The header uses `route(\'login\')`, not `/login`. What happens to each kind of link when a maintainer changes the URL of the login page?')
            ->hint('Run `git show main:resources/views/home.blade.php`. It shows the home page as it is on `main`, with the same `@auth` buttons.')
            ->hint('Fortify can turn off registration. Wrap "Create account" in `@if (Route::has(\'register\'))`, so the layout still works then.')
            ->code(<<<'BLADE'
                <header class="border-b">
                    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4 sm:px-6">
                        <x-app-logo href="{{ route('home') }}" />

                        <div class="ml-auto flex items-center gap-2">
                            @auth
                                <april:button-link href="{{ route('dashboard') }}" size="sm">My account</april:button-link>
                            @else
                                <april:button-link href="{{ route('login') }}" variant="ghost" size="sm">Log in</april:button-link>
                                @if (Route::has('register'))
                                    <april:button-link href="{{ route('register') }}" size="sm">Create account</april:button-link>
                                @endif
                            @endauth
                        </div>
                    </div>
                </header>
                BLADE)
            ->tryIt('Open http://localhost:8000 in a private window. You see "Log in" and "Create account". Log in as `test@example.com` with the password `password`, and open the home page again. You see "My account".')
            ->commit('Add the header to the public layout')
            ->docs('https://laravel.com/docs/blade#authentication-directives', 'Laravel docs: Authentication directives')
            ->docs('https://laravel.com/docs/urls#urls-for-named-routes', 'Laravel docs: URLs for named routes')
            ->check(Check::all(
                Check::fileContains('resources/views/layouts/site.blade.php', '<header'),
                Check::fileContains('resources/views/layouts/site.blade.php', '@auth'),
            )),

        Step::make('Make the menus from an array')
            ->explain(<<<'TEXT'
                The site has two menus. "Explore" is about a visit to the centre. "Discover" is about the programs. Each menu opens a small panel with links. Each link has a label and a short description.

                You could write each link as HTML. Then every new page means a copy of the same ten lines of HTML. Instead, keep the links as data, and let a loop make the HTML. Add a `@php` block at the very top of the layout with a `$menus` array. Each key is the heading of a menu. Each value is a list of links, and each link has a `label`, a `route` name and a `description`.

                No public page exists yet, so start with an empty array: `$menus = [];`. Visit Us adds the first entry.

                In the header, after the logo, add a `<nav aria-label="Main">`. Inside it, loop over `$menus`. For each menu, show an `<april:popover>`. Its trigger is a ghost button with the heading and a chevron icon. Its content is a list of the links. `request()->routeIs($link['route'])` is true on the page of that link. Use it to mark the current page with a background colour.

                The `aria-label` names the menu for screen readers. The test looks for it.
                TEXT)
            ->think('A menu entry holds a route name, such as `visit-us`, not a URL. Why is the route name the better choice?')
            ->hint('The outer loop is `@foreach ($menus as $heading => $links)`. The inner loop is `@foreach ($links as $link)`.')
            ->hint('The popover has two named slots: `<slot:trigger>` and `<slot:content>`. The icon is `<x-lucide-chevron-down class="ml-1 size-4" />`.')
            ->hint('`@class([...])` adds a class only when its condition is true: `\'bg-accent\' => request()->routeIs($link[\'route\'])`.')
            ->code(<<<'BLADE'
                @php
                    $menus = [];
                @endphp

                {{-- In the header, after the logo --}}
                <nav aria-label="Main" class="flex items-center gap-1">
                    @foreach ($menus as $heading => $links)
                        <april:popover>
                            <slot:trigger>
                                <april:button variant="ghost" size="sm">
                                    {{ $heading }}
                                    <x-lucide-chevron-down class="ml-1 size-4" />
                                </april:button>
                            </slot:trigger>
                            <slot:content class="w-72 p-2">
                                <ul class="grid gap-1">
                                    @foreach ($links as $link)
                                        <li>
                                            <a href="{{ route($link['route']) }}" @class([
                                                'block rounded-md px-3 py-2 hover:bg-accent hover:text-accent-foreground',
                                                'bg-accent' => request()->routeIs($link['route']),
                                            ])>
                                                <span class="block text-sm font-medium">{{ $link['label'] }}</span>
                                                <span class="block text-xs text-muted-foreground">{{ $link['description'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </slot:content>
                        </april:popover>
                    @endforeach
                </nav>
                BLADE)
            ->tryIt(<<<'TEXT'
                The menu is empty, so you see nothing yet. Try it with a test entry. Change the array to:

                `$menus = ['Explore' => [['label' => 'Home', 'route' => 'home', 'description' => 'A test link.']]];`

                Open http://localhost:8000 and click "Explore". You see the "Home" link, with a background, because you are on that page. Now set the array back to `$menus = [];`.
                TEXT)
            ->commit('Make the menus from an array')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->docs('https://laravel.com/docs/blade#conditional-classes', 'Laravel docs: Conditional classes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::all(
                Check::fileContains('resources/views/layouts/site.blade.php', '$menus'),
                Check::fileContains('resources/views/layouts/site.blade.php', 'aria-label="Main"'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify layout')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. It is the spec of this feature. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                A good test fails when the rule is broken. Prove it for one rule. Change `@auth` to `@guest` in the header, and run the test again. Two rules fail. Change it back.

                Then run `php artisan guide:done layout`, and commit the file that it makes. Follow "Git habits" in the guide to open your pull request.
                TEXT)
            ->think('The test checks the HTML of the home page only. Your layout will wrap many pages. Why is one page enough for this test?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for the site layout')
            ->docs('/guide/git', 'BLSC guide: Git habits')
            ->check(Check::testPassed('LayoutTest')),
    ]);
