<?php

/*
| Acceptance test for the "Visit Us page" feature.
|
| A static page with the hours, the location and the admission rates.
| The Explore menu and the home page link to it.
*/

test('the visit us page shows hours and admission rates', function () {
    $this->get(route('visit-us'))
        ->assertOk()
        ->assertSee('Visit Us')
        ->assertSee('Tuesday – Saturday, 9:30 AM – 5:00 PM')
        ->assertSee('458 Seymour Street')
        ->assertSee('Admission')
        ->assertSee('Toddler (0–2)')
        ->assertSee('Family')
        ->assertSee('$18.00');
});

test('the explore menu links to the visit us page', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    preg_match('/<header\b.*?<\/header>/s', $html, $header);

    expect($header[0] ?? '')
        ->toContain('Explore')
        ->toContain('href="'.route('visit-us').'"');
});

test('the home page invites visitors to plan their visit', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Plan your visit')
        ->assertSee('href="'.route('visit-us').'"', false);
});
