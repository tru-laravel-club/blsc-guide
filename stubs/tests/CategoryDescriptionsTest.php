<?php

use App\Models\Category;
use App\Models\Program;
use App\Models\User;

/*
| Acceptance test for the "Category descriptions" feature.
|
| Staff write a description for each category with the editor. The category page
| shows it above the programs. The SafeHtml cast cleans the HTML before the save.
*/

/**
 * Save the category form with this description, as staff.
 */
function saveCategoryDescription(Category $category, ?string $html): void
{
    test()->actingAs(User::factory()->staff()->create())
        ->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'summary' => $category->summary,
            'position' => $category->position,
            'description' => $html,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.categories.index'));
}

test('staff write the description with the editor', function () {
    $category = Category::factory()->create(['description' => '<p>Book a party in the lab.</p>']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee('data-slot="editor"', false)
        ->assertSee('name="description"', false)
        ->assertSee('Book a party in the lab.');
});

test('the category page shows the description above the programs', function () {
    $parties = Category::factory()->create(['name' => 'Parties', 'slug' => 'parties']);
    Program::factory()->for($parties)->create(['name' => 'Slime Party']);

    saveCategoryDescription($parties, '<h2>Your party, our lab</h2><p>We bring the <strong>science</strong>. You bring the cake.</p>');

    $this->get(route('categories.show', $parties))
        ->assertOk()
        ->assertSee('<h2>Your party, our lab</h2>', false)
        ->assertSee('<strong>science</strong>', false)
        ->assertSeeInOrder(['Your party, our lab', 'Slime Party']);
});

test('unsafe HTML is removed from the description', function () {
    $category = Category::factory()->create();

    saveCategoryDescription($category, '<p onclick="alert(1)">Ages 9 to 12.</p><script>alert("hacked")</script><a href="javascript:alert(1)">Apply</a>');

    expect($category->fresh()->description)
        ->toContain('<p>Ages 9 to 12.</p>')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');

    $this->get(route('categories.show', $category))->assertOk()->assertDontSee('hacked');
});

test('the description is optional, and an empty editor saves nothing', function () {
    $category = Category::factory()->create(['description' => '<p>Old text.</p>']);

    saveCategoryDescription($category, '<p></p>');

    expect($category->fresh()->description)->toBeNull();

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertDontSee('Old text.');
});
