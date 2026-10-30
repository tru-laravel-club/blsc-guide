<?php

use App\Models\Category;
use App\Models\Download;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
| Acceptance test for the "File downloads" feature.
|
| Staff upload PDF files to a page or a category, such as party invitations or a
| camp packing list. Visitors download them through a route that sends a safe name.
| Storage::fake() replaces the uploads disk, so the test writes no real files.
*/

beforeEach(function () {
    Storage::fake(config('filesystems.uploads'));
});

/**
 * Make a small PDF upload. The fake file needs no real PDF content.
 */
function fakePdf(string $name = 'invitation.pdf', int $kilobytes = 20): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

/**
 * Upload a file to a page or a category, as staff.
 *
 * @param  array<string, mixed>  $fields
 */
function uploadDownload(Page|Category $owner, array $fields): TestResponse
{
    $route = $owner instanceof Page
        ? route('admin.pages.downloads.store', $owner)
        : route('admin.categories.downloads.store', $owner);

    return test()->actingAs(User::factory()->staff()->create())->post($route, $fields);
}

test('staff upload a PDF file to a category', function () {
    $parties = Category::factory()->create(['name' => 'Parties', 'slug' => 'parties']);

    uploadDownload($parties, [
        'download_title' => 'Dinosaur party invitation',
        'download_file' => fakePdf('dinosaurs.pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $download = $parties->downloads()->sole();

    expect($download->title)->toBe('Dinosaur party invitation');
    Storage::disk(config('filesystems.uploads'))->assertExists($download->path);
});

test('staff upload a PDF file to a page', function () {
    $page = Page::factory()->create(['title' => 'Summer Camp Guide']);

    uploadDownload($page, [
        'download_title' => 'Camp packing list',
        'download_file' => fakePdf('packing.pdf'),
    ])->assertSessionHasNoErrors();

    expect($page->downloads()->sole()->title)->toBe('Camp packing list');
});

test('visitors see the files on the category page and on the page', function () {
    $parties = Category::factory()->create(['name' => 'Parties', 'slug' => 'parties']);
    $page = Page::factory()->create();
    uploadDownload($parties, ['download_title' => 'Dinosaur party invitation', 'download_file' => fakePdf()]);
    uploadDownload($page, ['download_title' => 'Camp packing list', 'download_file' => fakePdf()]);

    $invitation = $parties->downloads()->sole();
    $packingList = $page->downloads()->sole();

    $this->get(route('categories.show', $parties))
        ->assertOk()
        ->assertSee('Dinosaur party invitation')
        ->assertSee(route('downloads.show', $invitation))
        ->assertDontSee('Camp packing list');

    $this->get(route('pages.show', $page))
        ->assertOk()
        ->assertSee('Camp packing list')
        ->assertSee(route('downloads.show', $packingList));
});

test('a visitor downloads the file with a safe name made from its title', function () {
    $parties = Category::factory()->create();
    uploadDownload($parties, ['download_title' => 'Dinosaur "party" invitation / A4', 'download_file' => fakePdf('my file (final).pdf')]);

    $this->get(route('downloads.show', $parties->downloads()->sole()))
        ->assertOk()
        ->assertDownload('dinosaur-party-invitation-a4.pdf');
});

test('a file of another type is refused', function () {
    $category = Category::factory()->create();

    uploadDownload($category, [
        'download_title' => 'Invitation',
        'download_file' => UploadedFile::fake()->create('invitation.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertSessionHasErrors('download_file');

    uploadDownload($category, [
        'download_title' => 'Invitation',
        'download_file' => UploadedFile::fake()->createWithContent('invitation.html', '<script>alert(1)</script>'),
    ])->assertSessionHasErrors('download_file');

    expect(Download::count())->toBe(0);
});

test('a file larger than 10 MB is refused', function () {
    uploadDownload(Category::factory()->create(), [
        'download_title' => 'Huge invitation',
        'download_file' => fakePdf('huge.pdf', 11000),
    ])->assertSessionHasErrors('download_file');
});

test('a refused upload keeps the page form as it was', function () {
    $page = Page::factory()->create(['title' => 'Summer Camp Guide']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->from(route('admin.pages.edit', $page))
        ->post(route('admin.pages.downloads.store', $page), [
            'download_title' => 'Camp packing list',
            'download_file' => UploadedFile::fake()->create('packing.docx', 20),
        ])
        ->assertRedirect(route('admin.pages.edit', $page));

    $this->actingAs($staff)->get(route('admin.pages.edit', $page))
        ->assertSee('name="title" value="Summer Camp Guide"', false)
        ->assertSee('name="download_title" value="Camp packing list"', false)
        ->assertSee('The file field must be a file of type: pdf.');
});

test('a file needs a title', function () {
    uploadDownload(Category::factory()->create(), [
        'download_file' => fakePdf(),
    ])->assertSessionHasErrors('download_title');
});

test('staff delete a download and its file is removed', function () {
    $category = Category::factory()->create();
    uploadDownload($category, ['download_title' => 'Old invitation', 'download_file' => fakePdf()]);
    $download = $category->downloads()->sole();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.downloads.destroy', $download))
        ->assertRedirect();

    expect($download->fresh())->toBeNull();
    Storage::disk(config('filesystems.uploads'))->assertMissing($download->path);
});

test('deleting a page deletes its files', function () {
    $page = Page::factory()->create();
    uploadDownload($page, ['download_title' => 'Camp packing list', 'download_file' => fakePdf()]);
    $download = $page->downloads()->sole();

    $this->actingAs(User::factory()->staff()->create())->delete(route('admin.pages.destroy', $page));

    expect(Download::count())->toBe(0);
    Storage::disk(config('filesystems.uploads'))->assertMissing($download->path);
});

test('an unknown download is not found', function () {
    $this->get(route('downloads.show', 999))->assertNotFound();
});

test('a file on a draft page is not found', function () {
    $page = Page::factory()->draft()->create();
    uploadDownload($page, ['download_title' => 'Camp packing list', 'download_file' => fakePdf()]);

    $this->get(route('downloads.show', $page->downloads()->sole()))->assertNotFound();
});

test('only staff upload and delete files', function () {
    $category = Category::factory()->create();
    uploadDownload($category, ['download_title' => 'Invitation', 'download_file' => fakePdf()]);
    $download = $category->downloads()->sole();
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->post(route('admin.categories.downloads.store', $category), ['download_title' => 'Mine', 'download_file' => fakePdf()])
        ->assertForbidden();

    $this->actingAs($parent)
        ->delete(route('admin.downloads.destroy', $download))
        ->assertForbidden();

    expect($category->downloads()->count())->toBe(1);
});

test('staff see the upload form on the page and category edit pages', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create();
    $page = Page::factory()->create();

    $this->actingAs($staff)->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee(route('admin.categories.downloads.store', $category))
        ->assertSee('name="download_file"', false);

    $this->actingAs($staff)->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertSee(route('admin.pages.downloads.store', $page))
        ->assertSee('name="download_file"', false);
});
