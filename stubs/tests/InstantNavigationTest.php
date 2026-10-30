<?php

use App\Models\Category;
use App\Models\User;

/*
| Acceptance test for the "Instant navigation" feature.
|
| Links between the pages of the site use wire:navigate. Livewire then fetches
| the next page in the background and swaps it in, without a full reload.
| The controllers do not change.
*/

/**
 * The opening <a> tags in a piece of HTML. A quoted value can hold a ">",
 * such as the Tailwind class "[&>svg]:size-4", so the pattern skips quoted values.
 *
 * @return list<string>
 */
function instantNavigationLinks(string $html): array
{
    preg_match_all('/<a\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $html, $matches);

    return $matches[0];
}

/**
 * The links in one part of the page, such as the header, that go to a page of this site.
 *
 * @return list<string>
 */
function instantNavigationSiteLinks(string $html, string $tag): array
{
    preg_match("/<{$tag}\b.*?<\/{$tag}>/s", $html, $part);

    return array_values(array_filter(
        instantNavigationLinks($part[0] ?? ''),
        fn (string $link): bool => str_contains($link, 'href="'.url('/')),
    ));
}

test('the logo and every menu link in the public header use wire:navigate', function () {
    Category::factory()->create(['name' => 'Clubs', 'slug' => 'clubs']);

    $links = instantNavigationSiteLinks($this->get(route('home'))->assertOk()->getContent(), 'header');

    expect($links)->not->toBeEmpty();

    foreach ([route('home'), route('visit-us'), route('categories.show', 'clubs'), route('programs.index'), route('login')] as $url) {
        expect(collect($links)->contains(fn (string $link) => str_contains($link, 'href="'.$url.'"')))->toBeTrue("No header link to {$url}.");
    }

    foreach ($links as $link) {
        expect($link)->toContain('wire:navigate');
    }
});

test('the account link in the header uses wire:navigate for a signed-in person', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->getContent();

    $account = collect(instantNavigationSiteLinks($html, 'header'))
        ->first(fn (string $link) => str_contains($link, 'href="'.route('dashboard').'"'));

    expect($account)->not->toBeNull()->toContain('wire:navigate');
});

test('links that leave the site keep a normal page load', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    preg_match('/<footer\b.*?<\/footer>/s', $html, $footer);

    $external = array_filter(
        instantNavigationLinks($footer[0]),
        fn (string $link): bool => str_contains($link, 'href="tel:') || str_contains($link, 'href="mailto:'),
    );

    expect($external)->not->toBeEmpty();

    foreach ($external as $link) {
        expect($link)->not->toContain('wire:navigate');
    }
});

test('every link in the staff sidebar uses wire:navigate', function () {
    $html = $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index'))
        ->assertOk()
        ->getContent();

    $sidebar = array_filter(instantNavigationLinks($html), fn (string $link): bool => str_contains($link, 'data-sidebar="menu-button"'));

    expect($sidebar)->not->toBeEmpty();

    foreach ($sidebar as $link) {
        expect($link)->toContain('wire:navigate');
    }
});

test('the logo in the staff sidebar uses wire:navigate', function () {
    $html = $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index'))
        ->getContent();

    $logo = collect(instantNavigationLinks($html))
        ->first(fn (string $link) => str_contains($link, 'href="'.route('dashboard').'"') && ! str_contains($link, 'data-sidebar'));

    expect($logo)->not->toBeNull()->toContain('wire:navigate');
});

test('a menu link still opens its page with a normal request', function () {
    $this->get(route('visit-us'))->assertOk()->assertSee('Visit Us');
});
