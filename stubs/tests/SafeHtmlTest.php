<?php

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Arr;

/*
| Acceptance test for the "Safe HTML" feature.
|
| Staff write program descriptions with the April UI editor. The editor sends HTML.
| The SafeHtml cast cleans that HTML with an allowlist before the model saves it.
| One list in config/editor.php decides the toolbar buttons and the allowed tags.
*/

/**
 * Save a new program through the staff form and return its description.
 */
function saveProgramDescription(string $html): ?string
{
    test()->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), Program::factory()->raw(['description' => $html]))
        ->assertSessionHasNoErrors();

    return Program::latest('id')->firstOrFail()->description;
}

test('staff write the description with the editor', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.create'))
        ->assertOk()
        ->assertSee('data-slot="editor"', false)
        ->assertSee('name="description"', false)
        ->assertSee('april-ui/editor', false);
});

test('the toolbar shows a button for each allowed format', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.create'))
        ->assertSee('aria-label="Bold"', false)
        ->assertSee('aria-label="Heading"', false)
        ->assertSee('aria-label="Bullet list"', false)
        ->assertSee('aria-label="Link"', false);
});

test('a script tag is removed', function () {
    expect(saveProgramDescription('<p>Bring a lunch.</p><script>alert("hacked")</script>'))
        ->toBe('<p>Bring a lunch.</p>');
});

test('an onclick attribute is removed', function () {
    expect(saveProgramDescription('<p onclick="alert(1)">Bring a lunch.</p>'))
        ->toBe('<p>Bring a lunch.</p>');
});

test('a javascript link loses its address, and a web link keeps it', function () {
    $html = saveProgramDescription('<p><a href="javascript:alert(1)">Prizes</a> <a href="https://blscs.org/visit">Visit us</a></p>');

    expect($html)
        ->not->toContain('javascript:')
        ->toContain('<a href="https://blscs.org/visit">Visit us</a>');
});

test('the allowed formats stay', function () {
    $html = '<h2>What to bring</h2>'
        .'<p><strong>Lunch</strong> and <em>water</em>.</p>'
        .'<ul><li><p>A hat</p></li></ul>'
        .'<ol><li><p>Arrive</p></li></ol>'
        .'<blockquote><p>Best camp ever!</p></blockquote>';

    expect(saveProgramDescription($html))->toBe($html);
});

test('a format removed from the config disappears from the toolbar and from saved HTML', function () {
    config(['editor.formats' => Arr::except(config('editor.formats'), 'blockquote')]);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.create'))
        ->assertSee('aria-label="Bold"', false)
        ->assertDontSee('aria-label="Blockquote"', false);

    expect(saveProgramDescription('<blockquote><p>Best camp ever!</p></blockquote>'))
        ->toBe('<p>Best camp ever!</p>');
});

test('the cast cleans the HTML however the model is saved', function () {
    $program = Program::factory()->create(['description' => '<p onclick="alert(1)">Hi</p><script>alert(2)</script>']);

    expect($program->fresh()->description)->toBe('<p>Hi</p>');
});

test('an empty editor saves no description', function () {
    expect(saveProgramDescription('<p></p>'))->toBeNull();
});

test('the program page shows the formatted description', function () {
    $program = Program::factory()->create(['description' => '<p><strong>Bring a lunch.</strong></p>']);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertOk()
        ->assertSee('<strong>Bring a lunch.</strong>', false);
});
