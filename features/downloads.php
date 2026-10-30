<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('downloads')
    ->title('File downloads')
    ->section(Section::Content)
    ->level(2)
    ->needs(['cover-images', 'category-descriptions'])
    ->summary('Staff upload files, such as printable party invitations or a camp packing list, to a page or a category. Visitors download them.')
    ->teaches(['Polymorphic relationships', 'Traits', 'Model events', 'File downloads', 'File validation', 'Storage::fake'])
    ->test('DownloadsTest.php')
    ->intro(<<<'TEXT'
        Families who book a birthday party want printable invitations for their guests. Staff have the invitations as PDF files. They want to put them on the Parties category page, so parents can print one.

        You can build exactly that: an `invitations` folder and a Parties page that lists it. But think about next year. The camp team wants a packing list on the Summer Camps page. The QUARKS team wants an application checklist. A news post about a science fair wants a poster. Each one is the same thing: a PDF file that belongs to a page, and a link that visitors click. So you build "downloads", and party invitations are the first use.

        A download has a title, such as "Dinosaur party invitation", and the path of its file on the uploads disk from Cover images. It also has an owner. The owner can be a `Page` or a `Category`. A normal `belongsTo` points to one table. Here the owner can be in two tables, so you use a polymorphic relationship. The `downloads` table stores the kind of owner in `downloadable_type` and its ID in `downloadable_id`.

        The path of a download:

        1. Staff open the edit page of a category or a page, choose a PDF file and give it a title.
        2. `DownloadRequest` checks the file: a PDF, at most 10 MB, with a title.
        3. The admin controller stores the file on the uploads disk and creates a `Download` for the owner.
        4. The category page and the page list the downloads. Each link goes to `/downloads/{download}`.
        5. The public controller checks that the owner is public, then sends the file with a safe name made from the title.
        6. When staff delete a download, or its owner, the `Download` model deletes the file.
        TEXT)
    ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relationships')
    ->docs('https://laravel.com/docs/filesystem#downloading-files', 'Laravel docs: Downloading files')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/36', 'Laravel From Scratch, episode 36: Upload Featured Images To Storage')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->reflect([
        'The first request was "party invitations". Why did we build "downloads"? Name two other uses that need no new code.',
        'Staff want a packing list on a program page. Which files change? Which line in `AppServiceProvider` must you add, and why?',
        'The model deletes its file in a `deleted` event, but Cover images deletes the file in the controller. Why is the event a better place here?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start downloads')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/DownloadsTest.php` into the app.

                Look at `beforeEach()`. Like the Cover images test, it replaces the uploads disk with `Storage::fake()`. The rules check files with `assertExists()` and `assertMissing()`, and your real disk stays clean.

                Look at `fakePdf()`. `UploadedFile::fake()->create()` makes a file of any size with a MIME type that you choose. It has no real PDF content, but the validation reads the type from the fake file, so it is enough for the test.

                Then read `uploadDownload()`. It sends `download_title` and `download_file`, not `title` and `file`. The rule "a refused upload keeps the page form as it was" explains why. You find out in a later step.
                TEXT)
            ->think('The test never names a category "Parties" in the code that it checks. What does that tell you about the code that you write?')
            ->tryIt('Run `php artisan test tests/Feature/DownloadsTest.php`. Every rule fails, because the `Download` model does not exist yet.')
            ->commit('Add the file downloads acceptance test')
            ->docs('https://laravel.com/docs/http-tests#testing-file-uploads', 'Laravel docs: Testing file uploads')
            ->docs('https://laravel.com/docs/filesystem#testing', 'Laravel docs: Testing the filesystem')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/DownloadsTest.php')),

        Step::make('Create the Download model and its table')
            ->run('php artisan make:model Download -mf')
            ->explain(<<<'TEXT'
                Run the command below. It makes the `Download` model, a migration and a factory.

                A download needs a `title` and a `path`. The `path` is the place of the file on the disk, such as `downloads/x7Kq2.pdf`, like `cover_path` in Cover images. It is never a full address.

                The owner is the new part. `$table->morphs('downloadable')` adds two columns: `downloadable_type`, the kind of owner, and `downloadable_id`, its ID. It also adds an index on both, because every page view looks up the downloads of one owner.

                There is no foreign key. A foreign key points to one table, and the owner can be in two. So the database cannot delete the downloads of a deleted page. Your code must do it. You do that in a later step.
                TEXT)
            ->think('Why not two nullable columns, `page_id` and `category_id`? What happens when the centre wants downloads on programs too?')
            ->hint('Look at the "Table structure" part of the polymorphic relationships docs. The `comments` table there has the same shape.')
            ->code(<<<'PHP'
                Schema::create('downloads', function (Blueprint $table) {
                    $table->id();
                    $table->morphs('downloadable');
                    $table->string('title');
                    $table->string('path');
                    $table->timestamps();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'downloads\')`. You see `downloadable_type` and `downloadable_id` next to `title` and `path`.')
            ->commit('Add the Download model and table')
            ->docs('https://laravel.com/docs/migrations#column-method-morphs', 'Laravel docs: The morphs column type')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-table-structure', 'Laravel docs: Polymorphic table structure')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
            ->check(Check::table('downloads', ['downloadable_type', 'downloadable_id', 'title', 'path'])),

        Step::make('Give pages and categories their downloads')
            ->explain(<<<'TEXT'
                `Page` and `Category` both need the same `downloads()` relationship. Do not copy it into each model. Put it in a trait: `app/Models/Concerns/HasDownloads.php`. A trait is a set of methods that a class can `use`, like `HasFactory`. Then each owner needs one line, and a future owner, such as `Program`, needs the same line.

                In the trait, add `downloads(): MorphMany`. It returns `$this->morphMany(Download::class, 'downloadable')`, sorted by title. The second argument is the name that you gave `morphs()` in the migration.

                Then add `use HasDownloads;` to `Page` and `Category`.

                Last, open `app/Providers/AppServiceProvider.php` and add a morph map in `boot()`. Without it, Laravel stores the class name in `downloadable_type`, such as `App\Models\Page`. If a contributor moves or renames the class, every old row points to nothing. With the map, the database stores a short name, such as `page`, and the map connects it to the class.
                TEXT)
            ->think('The morph map lists `page` and `category`. What does the database store for a `Program` owner if a contributor forgets to add it to the map?')
            ->hint('`Relation::morphMap([\'page\' => Page::class, ...])` sets the map. Import `Illuminate\Database\Eloquent\Relations\Relation`.')
            ->hint('Add `@return MorphMany<Download, $this>` above the relationship, so PHPStan knows what it returns.')
            ->code(<<<'PHP'
                trait HasDownloads
                {
                    /**
                     * @return MorphMany<Download, $this>
                     */
                    public function downloads(): MorphMany
                    {
                        return $this->morphMany(Download::class, 'downloadable')->orderBy('title');
                    }
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `App\Models\Category::first()->downloads()->toRawSql()`. You see `where "downloads"."downloadable_type" = \'category\'` in the SQL. The database stores the short name from the map.')
            ->commit('Give pages and categories downloads')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-model-structure', 'Laravel docs: Polymorphic model structure')
            ->docs('https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types', 'Laravel docs: Custom polymorphic types')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
            ->check(Check::all(
                Check::fileContains('app/Models/Concerns/HasDownloads.php', 'morphMany'),
                Check::fileContains('app/Models/Page.php', 'HasDownloads'),
                Check::fileContains('app/Models/Category.php', 'HasDownloads'),
                Check::fileContains('app/Providers/AppServiceProvider.php', 'morphMap'),
            )),

        Step::make('Fill in the Download model')
            ->explain(<<<'TEXT'
                Open `app/Models/Download.php`. Add a docblock with the columns, and `#[Fillable(['title'])]`. Do not make `path` fillable, for the same reason as `cover_path`: only the controller sets it.

                Add four parts:

                1. `downloadable(): MorphTo`, the way back to the owner.
                2. `filename()`, the name that the visitor's computer saves. Build it from the title with `Str::slug()`, such as `dinosaur-party-invitation.pdf`. Never use the name of the uploaded file. It came from a staff computer, so it can be `scan (final) 2.pdf` or something worse. A slug has only letters, digits and dashes.
                3. `isPublic()`. A file on a draft page must stay hidden until the page is published. A category is always public.
                4. A `deleted` event in `booted()` that deletes the file from the uploads disk. Staff can delete a download, a page or a category. All three paths delete the `Download` model, so the event deletes the file in every case.

                Then the trait. In `HasDownloads`, add `bootHasDownloads()`. Laravel calls a method named `boot` plus the trait name when the model starts. Add a `deleted` event there that deletes each download of the model. Delete each one through the model, not with one query, so that each `deleted` event runs and removes its file.

                Last, fill in `DownloadFactory`: a title, a path, and a page as the owner.
                TEXT)
            ->think('`$page->downloads()->delete()` deletes all the rows with one query. Why does it leave the files on the disk?')
            ->hint('`Str::slug(\'Dinosaur "party" invitation / A4\')` gives `dinosaur-party-invitation-a4`. If the title has no letters or digits, the slug is empty. Use `?: \'download\'` for that case.')
            ->hint('`static::deleted(function (Download $download): void { ... })` in `booted()` runs after each delete.')
            ->hint('In `isPublic()`, `$this->downloadable instanceof Page` tells you if the owner is a page. `Page::isPublished()` already exists.')
            ->code(<<<'PHP'
                protected static function booted(): void
                {
                    static::deleted(function (Download $download): void {
                        Storage::disk(config()->string('filesystems.uploads'))->delete($download->path);
                    });
                }

                public function filename(): string
                {
                    return (Str::slug($this->title) ?: 'download').'.pdf';
                }

                public function isPublic(): bool
                {
                    $owner = $this->downloadable;

                    return ! $owner instanceof Page || $owner->isPublished();
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `$d = App\Models\Download::factory()->create(); [$d->downloadable_type, $d->filename()]`. You see `"page"` and a name that ends with `.pdf`. Run `$d->downloadable->delete()` and then `App\Models\Download::count()`. You see `0`.')
            ->commit('Fill in the Download model')
            ->docs('https://laravel.com/docs/eloquent#events-using-closures', 'Laravel docs: Model events with closures')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-retrieving-the-relationship', 'Laravel docs: Retrieving a polymorphic relationship')
            ->docs('https://laravel.com/docs/eloquent-factories#polymorphic-relationships', 'Laravel docs: Polymorphic relationships in factories')
            ->check(Check::all(
                Check::fileContains('app/Models/Download.php', 'function filename'),
                Check::fileContains('app/Models/Download.php', 'static::deleted'),
                Check::fileContains('app/Models/Concerns/HasDownloads.php', 'bootHasDownloads'),
            )),

        Step::make('Validate the upload')
            ->run('php artisan make:request DownloadRequest')
            ->explain(<<<'TEXT'
                The upload form sits on the edit page of a page or a category, under the main form. Two forms on one page share the old input and the errors in the session. The page form has a `title` field. If the upload form also used `title`, a refused upload would put the download title into the page title field. Staff then save the wrong title. So the upload fields are `download_title` and `download_file`.

                In `rules()`, the title is required, at most 255 characters. The file is required, a `file`, `mimes:pdf`, and at most 10 MB (`max:10240`, in kilobytes).

                Why only PDF? A PDF prints the same on every computer, and families can open it. An HTML or SVG file can hold a script. A Word file can hold macros. Allow what the centre needs, and refuse the rest. That is an allowlist again, like Safe HTML.

                The `mimes` rule does not trust the file name. It reads the first bytes of the file to find its real type. A script renamed to `invitation.pdf` is refused.

                Copy `authorize()` from `PageRequest`. Then add `attributes()`, so the errors say "title" and "file", not "download title".
                TEXT)
            ->think('The browser input has `accept="application/pdf"`. Why must the server still check the type?')
            ->hint('`attributes()` returns an array such as `[\'download_file\' => \'file\']`. Laravel uses it in every error message for that field.')
            ->code(<<<'PHP'
                public function rules(): array
                {
                    return [
                        'download_title' => ['required', 'string', 'max:255'],
                        'download_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
                    ];
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `Validator::make([\'download_file\' => Illuminate\Http\UploadedFile::fake()->create(\'a.docx\', 5)], (new App\Http\Requests\DownloadRequest)->rules())->errors()->all()`. You see "The download title field is required." and "The download file field must be a file of type: pdf." `Validator::make()` does not read `attributes()`. On the real form, the messages say "title" and "file".')
            ->commit('Validate download uploads')
            ->docs('https://laravel.com/docs/validation#rule-mimes', 'Laravel docs: The mimes rule')
            ->docs('https://laravel.com/docs/validation#customizing-the-validation-attributes', 'Laravel docs: Custom attribute names')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::fileContains('app/Http/Requests/DownloadRequest.php', 'mimes:pdf')),

        Step::make('Store and delete files for staff')
            ->run('php artisan make:controller Admin/DownloadController')
            ->explain(<<<'TEXT'
                Staff upload a file to a page or to a category. Add three routes in the staff group in `routes/web.php`:

                - `POST pages/{page}/downloads`, named `pages.downloads.store`
                - `POST categories/{category}/downloads`, named `categories.downloads.store`
                - `DELETE downloads/{download}`, named `downloads.destroy`

                The group adds `admin.` to each name. The first two routes bind a different model, so each gets its own method: `storeForPage()` and `storeForCategory()`. Both call one private method, `upload()`, that takes the owner as `Page|Category`.

                In `upload()`, store the file in the `downloads` folder on the uploads disk. Laravel gives it a random name. Then make the download through the owner: `$owner->downloads()->make([...])`. The relationship fills in `downloadable_type` and `downloadable_id`. Set the path, and save. If the disk cannot store the file, `store()` returns `false`. Send staff back with an error then.

                `destroy()` only calls `$download->delete()`. The `deleted` event removes the file. Each method returns `back()` with a status, because staff came from the edit page and want to stay there.
                TEXT)
            ->think('A third owner, such as a program, needs downloads. Which lines change in this controller and in the routes?')
            ->hint('`$request->file(\'download_file\')?->store(\'downloads\', config()->string(\'filesystems.uploads\'))` returns the path, or `false`.')
            ->hint('`$request->validated(\'download_title\')` returns one validated value.')
            ->code(<<<'PHP'
                private function upload(DownloadRequest $request, Page|Category $owner): RedirectResponse
                {
                    $path = $request->file('download_file')?->store('downloads', config()->string('filesystems.uploads'));

                    if (! $path) {
                        return back()->withErrors(['download_file' => 'The file could not be saved. Try again.']);
                    }

                    $download = $owner->downloads()->make(['title' => $request->validated('download_title')]);
                    $download->path = $path;
                    $download->save();

                    return back()->with('status', 'File uploaded.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --path=downloads`. You see the three staff routes.')
            ->commit('Store and delete downloads for staff')
            ->docs('https://laravel.com/docs/filesystem#storing-files', 'Laravel docs: Storing files')
            ->docs('https://laravel.com/docs/eloquent-relationships#inserting-and-updating-related-models', 'Laravel docs: Inserting related models')
            ->docs('https://laravel.com/docs/redirects#redirecting-with-flashed-session-data', 'Laravel docs: Redirecting with flashed data')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('admin.pages.downloads.store', 'admin.categories.downloads.store', 'admin.downloads.destroy')),

        Step::make('Add the upload section to the edit pages')
            ->explain(<<<'TEXT'
                Create `resources/views/admin/downloads/section.blade.php`. The page edit view and the category edit view both include it. It needs two values: `$downloads`, the list, and `$action`, the address of the upload route.

                The section has three parts:

                1. `<x-admin.status />`, so staff see "File uploaded." or "File deleted."
                2. The list of downloads. Each one links to the file and has a delete button. Use `<x-admin.delete-button>` with a description that says visitors lose the file.
                3. A form with `enctype="multipart/form-data"`, a `download_title` field and a `download_file` field with `accept="application/pdf"`.

                The upload form must be its own `<form>`, after the main form. HTML does not allow a form inside a form. The browser ignores the inner `<form>` tag, and the fields go with the outer form.

                Include the section in `resources/views/admin/pages/edit.blade.php` and `resources/views/admin/categories/edit.blade.php`, between the main form and the delete section. Not in `create`: a new page has no ID yet, so it cannot own a file.
                TEXT)
            ->think('Why does the section take `$action` from the view that includes it, and not build the route itself?')
            ->hint('`@include(\'admin.downloads.section\', [\'downloads\' => $page->downloads, \'action\' => route(\'admin.pages.downloads.store\', $page)])` passes both values.')
            ->code(<<<'PHP'
                <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <x-form-field name="download_title" label="Title, such as “Dinosaur party invitation”" required />

                    <x-form-field name="download_file" label="PDF file (up to 10 MB)" type="file" accept="application/pdf" required />

                    <april:button type="submit" variant="outline">Upload file</april:button>
                </form>
                PHP)
            ->tryIt(<<<'TEXT'
                Log in as staff. Open Categories and create a "Parties" category. Edit it. Under the form, you see "Files to download".

                Upload any PDF file with the title "Dinosaur party invitation". The page shows "File uploaded." and the file in the list. Then try a Word file or an image. You see an error under the file field, and the category name keeps its value.
                TEXT)
            ->commit('Add the upload section to the edit pages')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
            ->check(Check::all(
                Check::view('admin.downloads.section'),
                Check::fileContains('resources/views/admin/pages/edit.blade.php', 'admin.downloads.section'),
                Check::fileContains('resources/views/admin/categories/edit.blade.php', 'admin.downloads.section'),
            )),

        Step::make('Send the file with a safe name')
            ->run('php artisan make:controller DownloadController --invokable')
            ->explain(<<<'TEXT'
                Visitors need a route that sends the file. Add `Route::get('/downloads/{download}', DownloadController::class)->name('downloads.show')` near the page routes in `routes/web.php`.

                The route binds a `Download` by its ID. A visitor cannot ask for a path, only for a download that exists. An unknown ID gives a 404 page. This is safer than a route that takes a file name, because a file name such as `../../.env` can climb out of a folder.

                In `__invoke()`, stop with a 404 in two cases: the download is not public, or its file is not on the disk. Then return `Storage::disk(...)->download($path, $name)`. The second argument is the name from `filename()`. The response tells the browser to save the file with that name, and not to open it in the tab.

                Why a route, and not a link to the disk address, like a cover image? The route checks `isPublic()` each time. It gives the file a clear name. And the link stays the same if the centre moves its files to another disk.
                TEXT)
            ->think('The file is on the `public` disk, under a random name. Can a visitor download a file of a draft page without your route? What protects it?')
            ->hint('`abort_unless($download->isPublic() && $disk->exists($download->path), 404)` stops in both cases.')
            ->code(<<<'PHP'
                public function __invoke(Download $download): StreamedResponse
                {
                    $disk = Storage::disk(config()->string('filesystems.uploads'));

                    abort_unless($download->isPublic() && $disk->exists($download->path), 404);

                    return $disk->download($download->path, $download->filename());
                }
                PHP)
            ->tryIt('Run `php artisan serve`. Edit your Parties category in the staff area, and click the invitation in the list of files. The browser saves `dinosaur-party-invitation.pdf`. Then open http://localhost:8000/downloads/999. You see a 404 page.')
            ->commit('Send downloads with a safe name')
            ->docs('https://laravel.com/docs/filesystem#downloading-files', 'Laravel docs: Downloading files')
            ->docs('https://laravel.com/docs/routing#implicit-binding', 'Laravel docs: Implicit binding')
            ->docs('https://laravel.com/docs/http-tests#assert-download', 'Laravel docs: assertDownload')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('downloads.show')),

        Step::make('List the files for visitors')
            ->explain(<<<'TEXT'
                Create `resources/views/downloads/list.blade.php`. It takes `$downloads` and shows nothing when the list is empty. Otherwise it shows a "Downloads" heading and one link for each file, with "(PDF)" after the title. Visitors then know that the link saves a file and does not open a page.

                Include it in `resources/views/categories/show.blade.php`, under the description and above the programs. Include it in `resources/views/pages/show.blade.php`, under the body.

                The view loops over `$category->downloads`. The relationship sorts by title, so staff control the order with the titles. The code never checks the name of a category. Any category or page can have files.
                TEXT)
            ->think('The Parties category page now has a description, invitations and the list of party programs. Which of the three needed code that knows about parties?')
            ->hint('Start the partial with `@if ($downloads->isNotEmpty())`, so a page without files has no empty heading.')
            ->code(<<<'PHP'
                @include('downloads.list', ['downloads' => $category->downloads])
                PHP)
            ->tryIt('Open http://localhost:8000/programs/parties. You see "Downloads" with your invitation. Click it, and the browser saves the PDF. Delete the file in the staff area and reload. The heading is gone.')
            ->commit('List downloads on pages and categories')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->check(Check::all(
                Check::view('downloads.list'),
                Check::fileContains('resources/views/categories/show.blade.php', 'downloads.list'),
                Check::fileContains('resources/views/pages/show.blade.php', 'downloads.list'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify downloads')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan checks the types of the polymorphic relationship. If it reports a problem in `upload()`, look at the type of the owner. `Page|Category` is clear to PHPStan. A `MorphMany` relation passed as an argument is harder, because its owner type is part of its generic type.

                In your pull request, tell reviewers to run `php artisan migrate`. Explain that downloads are general: party invitations are one use. List the morph map names, because they are now stored in the database and must not change.
                TEXT)
            ->think('A contributor renames `page` to `pages` in the morph map. What happens to the downloads that are already in the database?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for file downloads')
            ->check(Check::testPassed('DownloadsTest')),
    ]);
