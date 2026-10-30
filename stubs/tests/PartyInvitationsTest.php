<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('invitations');
    Storage::disk('invitations')->put('dinosaurs.pdf', '%PDF-1.4 test');
    Storage::disk('invitations')->put('outer-space.pdf', '%PDF-1.4 test');
});

test('the parties page links to each invitation', function () {
    $this->get(route('parties'))
        ->assertOk()
        ->assertSee(route('parties.invitations', 'dinosaurs'))
        ->assertSee(route('parties.invitations', 'outer-space'));
});

test('a parent can download an invitation', function () {
    $this->get(route('parties.invitations', 'dinosaurs'))
        ->assertOk()
        ->assertDownload();
});

test('an invitation that does not exist is not found', function () {
    $this->get(route('parties.invitations', 'unicorns'))->assertNotFound();
});

test('a path outside the invitations folder is not found', function () {
    $this->get('/parties/invitations/..%2F..%2F.env')->assertNotFound();
});
