<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

/*
 * The opening tags of a kind of element in a view file. A quoted value can hold
 * a ">", such as `request()->routeIs()`, so the pattern skips quoted values.
 */
$tags = static function (string $view, string $element): array {
    $source = (string) @file_get_contents(resource_path("views/{$view}"));

    preg_match_all('/<'.preg_quote($element, '/').'\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $source, $matches);

    return $matches[0];
};

/*
 * True when every tag of this element that contains the needle also has wire:navigate.
 */
$navigates = static function (string $view, string $element, string $needle = '') use ($tags): bool {
    $found = array_filter($tags($view, $element), fn (string $tag): bool => str_contains($tag, $needle));

    return $found !== [] && array_filter($found, fn (string $tag): bool => ! str_contains($tag, 'wire:navigate')) === [];
};

return Feature::make('instant-navigation')
    ->title('Instant navigation')
    ->section(Section::Foundation)
    ->level(1)
    ->needs(['layout'])
    ->summary('Links between pages swap the page without a full reload, with a loading bar. The controllers do not change.')
    ->teaches(['wire:navigate', 'What a page load does', 'The browser developer tools', 'livewire:navigated'])
    ->test('InstantNavigationTest.php')
    ->intro(<<<'TEXT'
        Click a link on most websites, and the browser does a **full page load**. It throws away the page, asks the server for the new URL, downloads the HTML, reads the CSS and the JavaScript again, and draws everything from the start. You see a short white flash. Each page of this site loads the same header, the same styles and the same scripts, so most of that work repeats.

        Livewire has a small attribute for this: `wire:navigate`. Put it on a link, and Livewire takes over the click. It fetches the next page in the background, swaps the `<body>` of the page, and changes the address in the browser. The styles and scripts stay loaded, so the new page shows at once. The Back button still works.

        The important part: **the server does not change**. The route, the controller and the view work as before. The server still sends a complete HTML page, and a link still works without JavaScript. Only the browser does less work. This is the first, and the smallest, part of "the reactive way" in this project. The guide page "The reactive way" explains the rest. Read its table "When to choose which" now. The last row is this feature.

        In this feature, you add `wire:navigate` to the links in the two layouts: the public header and the staff sidebar. Then you look at what changes for scripts, and you try `@persist`, an idea that looks good but does not fit this header.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
    ->docs('/guide/reactive', 'BLSC guide: The reactive way')
    ->docs('https://livewire.laravel.com/docs/4.x/navigate', 'Livewire docs: Navigate')
    ->reflect([
        'A new page has a link to a PDF file and a link to the website of a partner school. Which of the two links gets `wire:navigate`? Why?',
        'Why does this feature need no change in any controller? What does the server send for a `wire:navigate` click?',
        'You tried `@persist` on the header. What broke? When would `@persist` be a good choice?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start instant-navigation')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/InstantNavigationTest.php` into the app.

                Read the test before you write code. It does not click anything, because a feature test has no browser. It reads the HTML that the server sends, finds the `<a>` tags, and checks that each link to a page of the site has `wire:navigate`. That is enough, because the attribute is all that Livewire needs.

                Look at the rule "links that leave the site keep a normal page load". It already passes. It protects the footer while you work.
                TEXT)
            ->think('The test only checks the HTML. What can it not prove about the click in the browser?')
            ->tryIt('Run `php artisan test tests/Feature/InstantNavigationTest.php`. Four rules fail, because no link in the public header has `wire:navigate` yet.')
            ->commit('Add the instant navigation acceptance test')
            ->docs('https://laravel.com/docs/http-tests#making-requests', 'Laravel docs: Making requests')
            ->check(Check::fileExists('tests/Feature/InstantNavigationTest.php')),

        Step::make('Make the public menu links instant')
            ->explain(<<<'TEXT'
                First, see a normal page load. Run `php artisan serve` and open http://localhost:8000. Open the developer tools of your browser (F12) and choose the Network tab. Click "Visit Us" in the Explore menu. The list fills with requests: the page, the CSS, the JavaScript, the fonts. The first row has the type "document". That is a full page load.

                Now open `resources/views/layouts/site.blade.php`. Find the `<a>` inside the `@foreach ($links as $link)` loop. Add `wire:navigate` to it. Add it to the logo too: `<x-app-logo href="{{ route('home') }}" wire:navigate />`. The logo is a Blade component, and it passes extra attributes to its `<a>` tag.

                Here is what happens on a click now:

                1. Livewire stops the normal click.
                2. It sends a `fetch` request for the same URL, with the header `X-Livewire-Navigate: 1`.
                3. The route and the controller run as usual and send back the complete page.
                4. Livewire swaps the `<body>`, adds any new styles or scripts from the `<head>`, and changes the address with the browser history API.

                The menu loop makes every menu link, so one change covers all of them. A new menu entry in `$menus` gets `wire:navigate` with no extra work.
                TEXT)
            ->think('The server sends the complete page, header and footer too. Where is the time saved, then?')
            ->hint('Put the attribute anywhere inside the opening tag: `<a href="{{ $link[\'url\'] }}" wire:navigate @class([...])>`.')
            ->code(<<<'PHP'
                <x-app-logo href="{{ route('home') }}" wire:navigate />

                {{-- In the menu loop --}}
                <a href="{{ $link['url'] }}" wire:navigate @class([
                    'block rounded-md px-3 py-2 hover:bg-accent hover:text-accent-foreground',
                    'bg-accent' => request()->url() === $link['url'],
                ])>
                PHP)
            ->tryIt(<<<'TEXT'
                Reload the home page. Open the Console tab of the developer tools and type `window.hello = 'still here'`. Click "Visit Us" in the Explore menu. Type `window.hello` again. You see `'still here'`. A full page load clears every JavaScript variable, so the page was swapped, not reloaded.

                Now open the Network tab and click "News". The first row has the type "fetch", not "document". Click it, and find `X-Livewire-Navigate` in the request headers. The response is the full HTML of the News page.
                TEXT)
            ->commit('Use wire:navigate in the public menu')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#basic-usage', 'Livewire docs: wire:navigate')
            ->docs('https://developer.mozilla.org/en-US/docs/Web/API/History/pushState', 'MDN: The pushState() method')
            ->check(fn (): bool => $navigates('layouts/site.blade.php', 'a', "\$link['url']")),

        Step::make('Add it to the header buttons, but not to every link')
            ->explain(<<<'TEXT'
                The right side of the header has buttons: Cart, My account, Log in and Create account. They are `<april:button-link>` components. An April UI component passes the attributes that it does not know to its HTML tag. So `wire:navigate` on the component lands on the `<a>`. Add it to all four.

                Not every link gets `wire:navigate`. Use it only for a link to a page of this site that shows HTML. Keep a normal link for:

                - a link that leaves the site, such as `tel:`, `mailto:` or the website of a partner school,
                - a file to download, such as a PDF,
                - a form that sends data, such as the Log out button. A form is a POST request, not a link.

                Look at the footer. Its two links are a phone number and an email address. They leave the site, so they stay as they are.
                TEXT)
            ->think('Look at the `href` of each link in the footer. How do you see, from the `href` alone, that a link leaves the site?')
            ->hint('`<april:button-link href="{{ route(\'login\') }}" variant="ghost" size="sm" wire:navigate>Log in</april:button-link>`')
            ->tryIt('Click "Log in" in the header. The login page opens without a reload. Log in, then click the logo. You land on the home page, and the header shows "My account".')
            ->commit('Use wire:navigate on the header buttons')
            ->docs('https://laravel.com/docs/blade#component-attributes', 'Laravel docs: Component attributes')
            ->check(Check::all(
                fn (): bool => $navigates('layouts/site.blade.php', 'april:button-link', "route('login')"),
                fn (): bool => $navigates('layouts/site.blade.php', 'april:button-link', "route('dashboard')"),
            )),

        Step::make('Make the staff sidebar instant')
            ->explain(<<<'TEXT'
                Open `resources/views/layouts/app.blade.php`. This is the layout of the account and staff pages. Some sidebar links already have `wire:navigate`: Dashboard, Profile, Security and Appearance. They came with the Laravel starter kit. The links that contributors added later do not have it.

                Add `wire:navigate` to every `<april:sidebar-menu-button-link>`, and to the `<x-app-logo>` at the top of the sidebar. Staff move between these pages all day, so they gain the most.

                The sidebar remembers if it is open or closed in a cookie. The server reads the cookie on every page, so the sidebar keeps its state after a swap too.
                TEXT)
            ->think('Why did the links that contributors added not get `wire:navigate`? How can a project stop that from happening again?')
            ->hint('Search the file for `<april:sidebar-menu-button-link`. Count the links, and count the ones with `wire:navigate`.')
            ->code(<<<'PHP'
                <april:sidebar-menu-button-link href="{{ route('admin.programs.index') }}" :active="request()->routeIs('admin.programs.*', 'admin.sessions.*')" wire:navigate>
                PHP)
            ->tryIt('Log in as `staff@example.com` with the password `password`. Click Programs, Categories and Forms in the sidebar. The sidebar does not flash, and the page under it changes.')
            ->commit('Use wire:navigate in the staff sidebar')
            ->check(Check::all(
                fn (): bool => $navigates('layouts/app.blade.php', 'april:sidebar-menu-button-link'),
                fn (): bool => $navigates('layouts/app.blade.php', 'x-app-logo'),
            )),

        Step::make('Watch the loading bar')
            ->explain(<<<'TEXT'
                A full page load shows progress in the tab of the browser. A swap does not, so Livewire shows its own loading bar: a thin line at the top of the page. It shows only when the next page takes more than 150 milliseconds. On your computer, most pages are faster than that, so you do not see it.

                The bar is on by default. Its settings live in the Livewire config: `navigate.show_progress_bar` and `navigate.progress_bar_color`. The app does not have the file `config/livewire.php`, so Livewire uses its defaults. `php artisan livewire:config` copies the file into the app when you want to change a setting. You do not need to change anything for this feature.

                Livewire also starts the request early. It sends the request when you press the mouse button, not when you release it. That small time is often enough to load half of the page.
                TEXT)
            ->think('Most visitors open the site on a phone, on a slow network. Why does the loading bar matter more for them than for you?')
            ->tryIt('In the Network tab, change "No throttling" to "Slow 4G". Click between the menu links. A thin blue line moves across the top of the page while the next page loads. Set the throttling back to "No throttling" when you finish.')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#customizing-the-progress-bar', 'Livewire docs: Customizing the progress bar')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#prefetching-links', 'Livewire docs: Prefetching links')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', 'wire:navigate')),

        Step::make('Keep page scripts working after a swap')
            ->explain(<<<'TEXT'
                A swap is not a page load. That changes one thing for JavaScript.

                The browser fires the `DOMContentLoaded` event once, when it reads a page for the first time. After a swap, it does not fire again. So a script that waits for `DOMContentLoaded` runs on the first page only. Livewire fires its own event after every swap: `livewire:navigated`. It also fires on the first page.

                Open `resources/views/partials/head.blade.php`. The script at the bottom sets the colour theme (light or dark) and marks the selected Appearance button. Find these two lines:

                `document.addEventListener('DOMContentLoaded', syncControls)`
                `document.addEventListener('livewire:navigated', handleLivewireNavigation)`

                The second line runs the theme code again after every swap. Without it, the Appearance buttons would show the wrong choice after a swap.

                April UI does the same in its own JavaScript, so its menus, dialogs and editors work after a swap. You do not need to change any code in this step. Remember the rule for every script that you add: if it must run on every page, listen for `livewire:navigated`.
                TEXT)
            ->think('Scripts in the `<head>` run once. Scripts in the `<body>` run again after each swap. Where would you put a script that counts page views?')
            ->tryIt(<<<'TEXT'
                Log in as staff and open Programs. In the Console tab, run these two lines:

                `document.addEventListener('DOMContentLoaded', () => console.log('loaded'))`
                `document.addEventListener('livewire:navigated', () => console.log('swapped'))`

                Click Categories in the sidebar, then Programs. The console shows "swapped" twice and never "loaded".
                TEXT)
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#dont-rely-on-domcontentloaded', 'Livewire docs: Do not rely on DOMContentLoaded')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#script-evaluation', 'Livewire docs: Script evaluation')
            ->check(Check::all(
                Check::fileContains('resources/views/partials/head.blade.php', 'livewire:navigated'),
                Check::fileContains('resources/views/layouts/site.blade.php', 'wire:navigate'),
            )),

        Step::make('Try @persist, and see why the header does not use it')
            ->explain(<<<'TEXT'
                Livewire can keep one element between pages: `@persist('name') ... @endpersist`. Livewire does not swap that element. It moves the same element into the new page. An audio player is the classic example: the music must not stop when the visitor changes the page.

                The header is the same on every page, so it looks like a good fit. Try it. Wrap the `<header>` in `resources/views/layouts/site.blade.php` in `@persist('site-header')` and `@endpersist`. Then do the Try it below.

                You see two problems. First, the Explore menu stays open over the new page. The menu is an April UI popover, and its "open" state lives in the element. The element is kept, so the state is kept. Second, the highlight of the current page stays on the old link. The server draws the highlight with `request()->url()`, but the kept header was drawn for the old page. The Cart count and the Discover categories would get old in the same way.

                Each problem has a fix, such as the `wire:current` directive for the highlight. But the header is cheap: the server draws it in a few milliseconds. The fixes add code, and the gain is small. So remove `@persist` again. Keep an element only when it holds state that the user must not lose.
                TEXT)
            ->think('The header looks the same on every page. Why is "the same on every page" not a reason to keep it?')
            ->tryIt('With `@persist` in place, open the Explore menu and click "Visit Us". The menu stays open, and "Visit Us" is not highlighted. Remove `@persist` and `@endpersist`, and do it again. The menu closes, and "Visit Us" is highlighted.')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#persisting-elements-across-page-visits', 'Livewire docs: Persisting elements across page visits')
            ->docs('https://livewire.laravel.com/docs/4.x/navigate#highlighting-active-links', 'Livewire docs: Highlighting active links')
            ->check(Check::all(
                Check::fileContains('resources/views/layouts/site.blade.php', 'wire:navigate'),
                fn (): bool => ! str_contains((string) file_get_contents(resource_path('views/layouts/site.blade.php')), '@persist'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify instant-navigation')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request. All the old tests pass without a change, because no controller changed.

                In your pull request, add a short screen recording: click between three pages with the Network tab open.

                From now on, give every new link to a page of the site `wire:navigate`. The later features do it in their own views.
                TEXT)
            ->think('A reviewer clicks a link and sees an error in the console after the swap. Which step of this guide do you read again?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for instant navigation')
            ->check(Check::testPassed('InstantNavigationTest')),
    ]);
