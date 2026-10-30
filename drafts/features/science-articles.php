<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('science-articles')
    ->title('Science articles')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('A list of science articles, newest first, and a page for each article. Drafts and articles with a future date stay hidden.')
    ->teaches(['Resource controllers', 'Route model binding by slug', 'Query scopes', 'Pagination'])
    ->test('ScienceArticlesTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start science-articles')
            ->check(Check::fileExists('tests/Feature/ScienceArticlesTest.php')),

        Step::make('Create the Article model, migration and factory')
            ->run('php artisan make:model Article -mf')
            ->check(Check::classExists('App\Models\Article')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->code("\$table->string('title');\n\$table->string('slug')->unique();\n\$table->string('author');\n\$table->string('excerpt');\n\$table->text('body');\n\$table->dateTime('published_at')->nullable();")
            ->explain('An empty published_at means the article is a draft.')
            ->check(Check::table('articles', ['title', 'slug', 'author', 'excerpt', 'body', 'published_at'])),

        Step::make('Fill in the factory')
            ->explain('Make the slug from the title with Str::slug(). Give published_at a date in the past.')
            ->check(Check::fileContains('database/factories/ArticleFactory.php', "'slug'")),

        Step::make('Add a published scope')
            ->explain('Keep articles whose published_at is set and is not in the future.')
            ->check(Check::fileContains('app/Models/Article.php', 'function published')),

        Step::make('Create the controller')
            ->run('php artisan make:controller ArticleController')
            ->explain('Add index() and show(Article $article) methods. In show(), return a 404 for drafts.')
            ->check(Check::classExists('App\Http\Controllers\ArticleController')),

        Step::make('Add the routes')
            ->code("Route::get('/science-articles', [ArticleController::class, 'index'])->name('articles.index');\nRoute::get('/science-articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');")
            ->explain('{article:slug} finds the article by its slug instead of its id.')
            ->docs('https://laravel.com/docs/routing#customizing-the-key')
            ->check(Check::route('articles.index', 'articles.show')),

        Step::make('Create the two views')
            ->explain('Create resources/views/articles/index.blade.php and resources/views/articles/show.blade.php. Show the author and date on both.')
            ->check(Check::all(Check::view('articles.index'), Check::view('articles.show'))),

        Step::make('Add the page to the Explore menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'articles.index'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify science-articles')
            ->check(Check::testPassed('ScienceArticlesTest')),
    ]);
