<?php

test('the page shows the membership types and their prices', function () {
    $this->get(route('memberships'))
        ->assertOk()
        ->assertSee('Membership')
        ->assertSee('$');
});

test('the FAQ explains reciprocal admission', function () {
    $this->get(route('memberships'))
        ->assertOk()
        ->assertSee('reciprocal');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('memberships'));
});
