<?php

use App\Models\User;

/*
| Acceptance test for the "Site layout and menus" feature.
|
| Every public page wraps its content in <x-layouts::site>. The layout adds a
| header with the logo, the menus and the account links, and a footer with the
| address, the hours and the contact details.
*/

/**
 * One part of the page, such as the header or the footer.
 */
function layoutPart(string $html, string $tag): string
{
    preg_match("/<{$tag}\b.*?<\/{$tag}>/s", $html, $part);

    return $part[0] ?? '';
}

test('the home page has a header with the logo and the main menu', function () {
    $header = layoutPart($this->get(route('home'))->assertOk()->getContent(), 'header');

    expect($header)
        ->toContain('href="'.route('home').'"')
        ->toContain('aria-label="Main"');
});

test('the footer shows the address, the hours and how to contact the centre', function () {
    $footer = layoutPart($this->get(route('home'))->assertOk()->getContent(), 'footer');

    expect($footer)
        ->toContain('458 Seymour Street')
        ->toContain('Tuesday – Saturday, 9:30 AM – 5:00 PM')
        ->toContain('href="tel:+12505542572"')
        ->toContain('href="mailto:info@blscs.org"');
});

test('a guest sees links to log in and to create an account', function () {
    $header = layoutPart($this->get(route('home'))->getContent(), 'header');

    expect($header)
        ->toContain('href="'.route('login').'"')
        ->toContain('href="'.route('register').'"')
        ->toContain('Create account')
        ->not->toContain('My account');
});

test('a signed-in person sees a link to their account', function () {
    $header = layoutPart($this->actingAs(User::factory()->create())->get(route('home'))->getContent(), 'header');

    expect($header)
        ->toContain('href="'.route('dashboard').'"')
        ->toContain('My account')
        ->not->toContain('Create account');
});
