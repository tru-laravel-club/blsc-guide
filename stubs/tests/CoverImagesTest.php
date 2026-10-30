<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
| Acceptance test for the "Cover images" feature.
|
| Pages and posts get a cover image with alt text. The file goes on the disk
| named in config('filesystems.uploads'), never in the public folder.
| Storage::fake() replaces the disk, so the test writes no real files.
*/

beforeEach(function () {
    Storage::fake(config('filesystems.uploads'));
});

/**
 * Make a small PNG upload. It needs no image library, unlike UploadedFile::fake()->image().
 */
function fakeCover(string $name = 'cover.png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    ));
}

/**
 * Send the page form with these fields, as staff.
 *
 * @param  array<string, mixed>  $fields
 */
function savePageCover(Page $page, array $fields): TestResponse
{
    return test()->actingAs(User::factory()->staff()->create())
        ->put(route('admin.pages.update', $page), [
            'title' => $page->title,
            'slug' => $page->slug,
            'type' => $page->type->value,
            'published_at' => $page->published_at?->format('Y-m-d\TH:i'),
            ...$fields,
        ]);
}

test('the upload disk comes from the environment and is public by default', function () {
    $config = require config_path('filesystems.php');

    expect(file_get_contents(config_path('filesystems.php')))->toContain("'uploads' => env(")
        ->and($config['disks'])->toHaveKey($config['uploads']);
});

test('staff upload a cover image with alt text', function () {
    $page = Page::factory()->create();

    savePageCover($page, [
        'cover' => fakeCover('slime.png'),
        'cover_alt' => 'Two children stretch green slime.',
    ])->assertSessionHasNoErrors();

    $page->refresh();

    expect($page->cover_alt)->toBe('Two children stretch green slime.');
    Storage::disk(config('filesystems.uploads'))->assertExists($page->cover_path);
});

test('the file goes on the disk named in the config', function () {
    config(['filesystems.uploads' => 'cloud-uploads']);
    Storage::fake('cloud-uploads');
    Storage::fake('public');
    $page = Page::factory()->create();

    savePageCover($page, [
        'cover' => fakeCover('slime.png'),
        'cover_alt' => 'Green slime.',
    ]);

    Storage::disk('cloud-uploads')->assertExists($page->fresh()->cover_path);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('visitors see the cover with its alt text', function () {
    $post = Page::factory()->post()->create(['title' => 'Slime Lab']);

    savePageCover($post, [
        'cover' => fakeCover('slime.png'),
        'cover_alt' => 'Green slime.',
    ]);

    $url = $post->fresh()->coverUrl();

    expect($url)->not->toBeNull();

    $this->get(route('news.show', $post))->assertSee($url)->assertSee('alt="Green slime."', false);
    $this->get(route('news.index'))->assertSee($url);
});

test('a page without a cover has no cover address', function () {
    expect(Page::factory()->create()->coverUrl())->toBeNull();
});

test('a new cover replaces the old file', function () {
    $page = Page::factory()->create();
    savePageCover($page, ['cover' => fakeCover('old.png'), 'cover_alt' => 'Old.']);
    $oldPath = $page->fresh()->cover_path;

    savePageCover($page, ['cover' => fakeCover('new.png'), 'cover_alt' => 'New.']);

    $disk = Storage::disk(config('filesystems.uploads'));
    $disk->assertMissing($oldPath);
    $disk->assertExists($page->fresh()->cover_path);
});

test('staff remove a cover and the file is deleted', function () {
    $page = Page::factory()->create();
    savePageCover($page, ['cover' => fakeCover('slime.png'), 'cover_alt' => 'Slime.']);
    $path = $page->fresh()->cover_path;

    savePageCover($page, ['remove_cover' => '1'])->assertSessionHasNoErrors();

    expect($page->fresh())->cover_path->toBeNull()->cover_alt->toBeNull();
    Storage::disk(config('filesystems.uploads'))->assertMissing($path);
});

test('deleting a page deletes its cover', function () {
    $page = Page::factory()->create();
    savePageCover($page, ['cover' => fakeCover('slime.png'), 'cover_alt' => 'Slime.']);
    $path = $page->fresh()->cover_path;

    $this->actingAs(User::factory()->staff()->create())->delete(route('admin.pages.destroy', $page));

    Storage::disk(config('filesystems.uploads'))->assertMissing($path);
});

test('a file larger than 2 MB is refused', function () {
    savePageCover(Page::factory()->create(), [
        'cover' => fakeCover('huge.png')->size(3000),
        'cover_alt' => 'A huge photo.',
    ])->assertSessionHasErrors('cover');
});

test('a file that is not an image is refused', function () {
    savePageCover(Page::factory()->create(), [
        'cover' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'cover_alt' => 'Notes.',
    ])->assertSessionHasErrors('cover');
});

test('an SVG file is refused, because it can hold a script', function () {
    savePageCover(Page::factory()->create(), [
        'cover' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'cover_alt' => 'Logo.',
    ])->assertSessionHasErrors('cover');
});

test('alt text is required with a new image', function () {
    savePageCover(Page::factory()->create(), [
        'cover' => fakeCover('slime.png'),
    ])->assertSessionHasErrors('cover_alt');
});

test('alt text is required while a page keeps its cover', function () {
    $page = Page::factory()->create();
    savePageCover($page, ['cover' => fakeCover('slime.png'), 'cover_alt' => 'Slime.']);

    savePageCover($page, ['cover_alt' => ''])->assertSessionHasErrors('cover_alt');
});

test('staff see the upload field on the form', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.pages.create'))
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="cover"', false)
        ->assertSee('name="cover_alt"', false);
});
