<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('cover-images')
    ->title('Cover images')
    ->section(Section::Content)
    ->level(2)
    ->needs(['pages-posts'])
    ->summary('Pages and posts get a cover image. Files go on a storage disk, because Laravel Cloud does not keep local files.')
    ->teaches(['File uploads', 'Filesystem disks', 'File validation', 'Storage::fake', 'Alt text'])
    ->test('CoverImagesTest.php')
    ->intro(<<<'TEXT'
        A news post about the slime lab needs a photo of the slime. The April UI editor has no image button today. Custom buttons and blocks are a planned April UI feature. Until then, each page and post gets one cover image.

        The hard part of an upload is not the form. It is where the file lives. On your machine, a file in `storage/app/public` stays there. The site runs on Laravel Cloud, and Laravel Cloud does not keep local files between deployments. Each deployment starts with new servers and a fresh copy of the code. A file that the code wrote to the local disk is gone after the next deployment. When the site runs on two servers, the second server never has the file.

        So the code never writes to a folder. It writes to a disk with a name, with `Storage::disk($name)`. The config decides what the disk is. On your machine, it is the `public` disk, a local folder. On Laravel Cloud, it is a bucket: object storage that keeps files outside the servers. The code is the same in both places. Only one environment value changes.

        The path of a cover image:

        1. Staff choose an image and write alt text in the page form.
        2. `PageRequest` checks the file: an image, at most 2 MB, and alt text with it.
        3. The controller stores the file on the uploads disk and saves its path in `cover_path`. It deletes the old file, if there is one.
        4. `Page::coverUrl()` asks the disk for the address of the file, and the views show it.
        TEXT)
    ->docs('https://laravel.com/docs/filesystem#configuration', 'Laravel docs: Filesystem configuration')
    ->docs('https://laravel.com/docs/filesystem#file-uploads', 'Laravel docs: File uploads')
    ->docs('https://cloud.laravel.com/docs/resources/object-storage', 'Laravel Cloud docs: Object storage')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/36', 'Laravel From Scratch, episode 36: Upload Featured Images To Storage')
    ->reflect([
        'Why must the code never write to the `public` folder on Laravel Cloud? What does a visitor see after the next deployment?',
        'The code stores the new file, saves the page, and only then deletes the old file. What goes wrong with the other order?',
        'The File downloads feature stores PDF files for pages and categories. What can it reuse from this feature?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start cover-images')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/CoverImagesTest.php` into the app.

                Look at `beforeEach()` at the top. `Storage::fake()` replaces the uploads disk with a temporary folder for each test. The test then checks files with `assertExists()` and `assertMissing()`, and your real disk stays clean.

                Look at `fakeCover()`. It makes a tiny PNG from a fixed string. `UploadedFile::fake()->image()` can make images too, but it needs the GD extension of PHP, and not every machine has it.

                Then read "the file goes on the disk named in the config". It changes the config to a disk called `cloud-uploads`, and checks that the `public` disk stays empty. A controller that writes `'public'` in the code fails this rule.
                TEXT)
            ->think('The test never opens the `public` folder or `storage/app`. How can it check that a file was deleted?')
            ->tryIt('Run `php artisan test tests/Feature/CoverImagesTest.php`. Most rules fail, because the pages table has no cover columns yet.')
            ->commit('Add the cover images acceptance test')
            ->docs('https://laravel.com/docs/filesystem#testing', 'Laravel docs: Testing the filesystem')
            ->docs('https://laravel.com/docs/http-tests#testing-file-uploads', 'Laravel docs: Testing file uploads')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/CoverImagesTest.php')),

        Step::make('Name the uploads disk in the config')
            ->explain(<<<'TEXT'
                Open `config/filesystems.php`. It lists the disks: `local`, `public` and `s3`. Each disk has a driver and a place.

                Add one key under `default`: `'uploads' => env('FILESYSTEM_UPLOADS_DISK', 'public')`. It is the name of the disk for every file that staff upload. On your machine, the `public` disk is right. On Laravel Cloud, a maintainer attaches a bucket, and Laravel Cloud adds a disk for it. Then the maintainer sets `FILESYSTEM_UPLOADS_DISK` to the name of that disk. Nobody changes the code.

                Add `FILESYSTEM_UPLOADS_DISK=public` to `.env.example`, under `FILESYSTEM_DISK`. New contributors copy that file, so they see the setting.

                Why not use the `default` disk? It is `local`, a private folder. Files on it have no public address, so visitors cannot see a cover image.
                TEXT)
            ->think('The code reads `config(\'filesystems.uploads\')`, never `env()`. Why? What happens to `env()` after `php artisan config:cache`?')
            ->hint('Copy the comment style of the `default` key. Explain why the value changes on Laravel Cloud.')
            ->code(<<<'PHP'
                'uploads' => env('FILESYSTEM_UPLOADS_DISK', 'public'),
                PHP)
            ->tryIt('Run `php artisan tinker`, then `config(\'filesystems.uploads\')`. You see `"public"`.')
            ->commit('Add the uploads disk setting')
            ->docs('https://laravel.com/docs/filesystem#the-public-disk', 'Laravel docs: The public disk')
            ->docs('https://laravel.com/docs/configuration#accessing-configuration-values', 'Laravel docs: Accessing configuration values')
            ->check(Check::fileContains('config/filesystems.php', "'uploads'")),

        Step::make('Add the cover columns')
            ->run('php artisan make:migration add_cover_to_pages_table')
            ->explain(<<<'TEXT'
                Add two nullable string columns to `pages`: `cover_path` and `cover_alt`.

                `cover_path` stores the path of the file on the disk, such as `covers/x7Kq2.png`. It does not store a full address. The address depends on the disk, and the disk can change. If the database held `http://localhost:8000/storage/...`, every cover would break on the live site.

                `cover_alt` is the alt text. A screen reader reads it to a person who cannot see the image. Search engines read it too.

                Write `down()` too: drop both columns.
                TEXT)
            ->think('The centre moves its files to a new bucket next year. Which is easier to move: a path or a full address? Why?')
            ->hint('`$table->string(\'cover_path\')->nullable()->after(\'body\')` puts the column after `body`.')
            ->code(<<<'PHP'
                Schema::table('pages', function (Blueprint $table) {
                    $table->string('cover_path')->nullable()->after('body');
                    $table->string('cover_alt')->nullable()->after('cover_path');
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in tinker, run `Schema::getColumnListing(\'pages\')`. You see `cover_path` and `cover_alt`.')
            ->commit('Add cover columns to pages')
            ->docs('https://laravel.com/docs/migrations#generating-migrations', 'Laravel docs: Generating migrations')
            ->check(Check::table('pages', ['cover_path', 'cover_alt'])),

        Step::make('Ask the disk for the cover address')
            ->explain(<<<'TEXT'
                Open `app/Models/Page.php`. Add `cover_alt` to `#[Fillable]`. Do not add `cover_path`. Only the controller sets it, after it stores a file. A changed form must not point a page at any file on the disk.

                Add `@property string|null` lines for both columns to the docblock.

                Then add `coverUrl(): ?string`. It returns `null` when there is no cover. Otherwise it asks the uploads disk for the address of the file: `Storage::disk(...)->url($this->cover_path)`. The `public` disk gives a local address. A bucket disk gives the address of the bucket. The view does not know the difference.
                TEXT)
            ->think('Why does `coverUrl()` ask the disk for the address, and not build it with `asset(\'storage/\'.$path)`?')
            ->hint('`config()->string(\'filesystems.uploads\')` returns the value as a `string`, so PHPStan knows its type.')
            ->code(<<<'PHP'
                public function coverUrl(): ?string
                {
                    if ($this->cover_path === null) {
                        return null;
                    }

                    return Storage::disk(config()->string('filesystems.uploads'))->url($this->cover_path);
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `(new App\Models\Page)->forceFill([\'cover_path\' => \'covers/a.png\'])->coverUrl()`. You see an address that ends with `/storage/covers/a.png`.')
            ->commit('Add Page::coverUrl()')
            ->docs('https://laravel.com/docs/filesystem#file-urls', 'Laravel docs: File URLs')
            ->check(Check::fileContains('app/Models/Page.php', 'function coverUrl')),

        Step::make('Validate the image and its alt text')
            ->explain(<<<'TEXT'
                Open `app/Http/Requests/PageRequest.php` and add three rules.

                `cover` is `nullable`, an `image`, and at most 2 MB. The `max` rule counts kilobytes for a file, so 2 MB is `max:2048`. The `image` rule allows photo types, such as JPG, PNG, GIF and WebP. It refuses SVG. An SVG file is code, and it can hold a `<script>` tag, like the HTML in Safe HTML.

                `remove_cover` is a `boolean`. Staff tick it to remove the image.

                `cover_alt` is required when there is an image. That is true in two cases: staff upload a new image, or the page keeps its old image. `required_with:cover` sees only the first case. Use `Rule::requiredIf()` with a closure for both.
                TEXT)
            ->think('Staff tick "remove" and clear the alt text in the same save. Must the alt text be required? What does your closure say?')
            ->hint('`$this->hasFile(\'cover\')` is true when the request has an uploaded file.')
            ->hint('`$page?->cover_path !== null && ! $this->boolean(\'remove_cover\')` is true when the page keeps its image.')
            ->code(<<<'PHP'
                'cover' => ['nullable', 'image', 'max:2048'],
                'remove_cover' => ['boolean'],
                'cover_alt' => [
                    Rule::requiredIf(fn () => $this->hasFile('cover') || ($page?->cover_path !== null && ! $this->boolean('remove_cover'))),
                    'nullable',
                    'string',
                    'max:255',
                ],
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/CoverImagesTest.php --filter=refused`. The rules about large files, PDF files and SVG files pass.')
            ->commit('Validate cover images')
            ->docs('https://laravel.com/docs/validation#validating-files', 'Laravel docs: Validating files')
            ->docs('https://laravel.com/docs/validation#rule-image', 'Laravel docs: The image rule')
            ->check(Check::fileContains('app/Http/Requests/PageRequest.php', "'cover'")),

        Step::make('Store, replace and remove the file')
            ->explain(<<<'TEXT'
                Open `Admin\PageController`. `store()` and `update()` now do the same work, so move it into one private method, `save(PageRequest $request, Page $page)`.

                Do the work in this order:

                1. Remember the old path.
                2. Fill the fields without `cover` and `remove_cover`: `$request->safe()->except([...])`.
                3. If there is a new file, store it with `$request->file('cover')->store('covers', $disk)`. Laravel gives it a random name, so two files called `photo.jpg` do not clash. If staff ticked "remove", set the path to `null`. With no image, clear the alt text too.
                4. Save the page.
                5. If the path changed, delete the old file.

                The order is important. If the save fails, the old file must still be there, because the page still points at it.

                Also change `destroy()`: after it deletes the page, it deletes the cover file. Otherwise the disk keeps files that nothing uses, and the centre pays to store them.
                TEXT)
            ->think('`store()` returns the path, such as `covers/x7Kq2.png`. Why does Laravel choose the file name, and not the staff member?')
            ->hint('`config()->string(\'filesystems.uploads\')` gives the name of the disk. Pass it as the second argument of `store()`.')
            ->hint('`Storage::disk($disk)->delete($oldCover)` deletes one file.')
            ->code(<<<'PHP'
                private function save(PageRequest $request, Page $page): void
                {
                    $disk = config()->string('filesystems.uploads');
                    $oldCover = $page->cover_path;

                    $page->fill($request->safe()->except(['cover', 'remove_cover']));

                    if ($request->hasFile('cover')) {
                        $page->cover_path = $request->file('cover')->store('covers', $disk) ?: null;
                    } elseif ($request->boolean('remove_cover')) {
                        $page->cover_path = null;
                    }

                    if ($page->cover_path === null) {
                        $page->cover_alt = null;
                    }

                    $page->save();

                    if ($oldCover !== null && $oldCover !== $page->cover_path) {
                        Storage::disk($disk)->delete($oldCover);
                    }
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/CoverImagesTest.php --filter="replaces|remove|deleting"`. The three rules pass.')
            ->commit('Store cover images on the uploads disk')
            ->docs('https://laravel.com/docs/filesystem#storing-files', 'Laravel docs: Storing files')
            ->docs('https://laravel.com/docs/filesystem#deleting-files', 'Laravel docs: Deleting files')
            ->docs('https://laravel.com/docs/requests#storing-uploaded-files', 'Laravel docs: Storing uploaded files')
            ->check(Check::fileContains('app/Http/Controllers/Admin/PageController.php', 'filesystems.uploads')),

        Step::make('Add the upload field to the form')
            ->explain(<<<'TEXT'
                A form sends files only with `enctype="multipart/form-data"`. Without it, the browser sends the file name and no file, and the upload fails with no error. Add it to the `<form>` tag in `create.blade.php` and `edit.blade.php`.

                In `form.blade.php`, add a "Cover image" fieldset under the editor:

                - When the page has a cover, show it, and add a "Remove the cover image" checkbox. Put a hidden `remove_cover` input with `0` before it, like `is_published` in the program form.
                - A file input named `cover`. `<x-form-field type="file">` works. Add `accept` with the image types, so the browser shows only images.
                - A text field named `cover_alt`. Tell staff what alt text is: a short description of the image for people who cannot see it.
                TEXT)
            ->think('The `accept` attribute already limits the files that staff can choose. Why does the server still validate the file?')
            ->hint('`accept="image/jpeg,image/png,image/gif,image/webp"` lists the common types that the `image` rule allows. SVG is not in the list.')
            ->code(<<<'PHP'
                <x-form-field name="cover" label="Image (JPG, PNG, GIF or WebP, up to 2 MB)" type="file" accept="image/jpeg,image/png,image/gif,image/webp" />

                <x-form-field name="cover_alt" label="Alt text: describe the image for people who cannot see it" :value="old('cover_alt', $page->cover_alt)" />
                PHP)
            ->tryIt('Log in as staff and edit a post. Choose an image, leave the alt text empty, and save. You see an error on the alt text. Write the alt text and save again.')
            ->commit('Add the cover image fields')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
            ->check(Check::all(
                Check::fileContains('resources/views/admin/pages/edit.blade.php', 'multipart/form-data'),
                Check::fileContains('resources/views/admin/pages/form.blade.php', 'cover_alt'),
            )),

        Step::make('Show the cover to visitors')
            ->run('php artisan storage:link')
            ->explain(<<<'TEXT'
                Files on the `public` disk are in `storage/app/public`. The web server serves only the `public` folder. Run `php artisan storage:link` once. It makes `public/storage` point to `storage/app/public`, so the addresses from `coverUrl()` work on your machine. Git ignores the link, and Laravel Cloud does not need it, because a bucket has its own address.

                In `resources/views/pages/show.blade.php`, show the cover under the header when `$page->coverUrl()` is not `null`. Use `$page->cover_alt` as the `alt`.

                In `resources/views/news/index.blade.php`, show the cover of each post above its title. Here use `alt=""`. The title next to the image already says what the post is. An empty alt tells a screen reader to skip the image, so the person does not hear the same thing twice.
                TEXT)
            ->think('Without `storage:link`, what does the browser show for a cover on your machine? Why does the test still pass?')
            ->code(<<<'PHP'
                @if ($page->coverUrl())
                    <img src="{{ $page->coverUrl() }}" alt="{{ $page->cover_alt }}" class="aspect-[2/1] w-full rounded-lg object-cover">
                @endif
                PHP)
            ->tryIt('Open http://localhost:8000/news. You see the cover of your post. Right-click the image and open it in a new tab. The address starts with `http://localhost:8000/storage/covers/`.')
            ->commit('Show cover images on pages and news')
            ->docs('https://laravel.com/docs/filesystem#the-public-disk', 'Laravel docs: The public disk')
            ->check(Check::fileContains('resources/views/pages/show.blade.php', 'coverUrl()')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify cover-images')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                In your pull request, tell reviewers to run `php artisan migrate` and `php artisan storage:link`. Also tell the maintainers about the new `FILESYSTEM_UPLOADS_DISK` setting. Before the feature goes live, they attach a bucket in Laravel Cloud and set the value to the name of its disk.
                TEXT)
            ->think('A maintainer forgets to set `FILESYSTEM_UPLOADS_DISK` on Laravel Cloud. Uploads work on the day of the release. When do the covers break, and why?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for cover images')
            ->check(Check::testPassed('CoverImagesTest')),
    ]);
