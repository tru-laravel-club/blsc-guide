<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('articles-admin')
    ->title('Staff: manage articles')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['science-articles', 'staff-roles', 'programs-admin'])
    ->summary('Staff write, edit, publish and delete science articles. An article without a publish date stays a draft.')
    ->teaches(['Resource controllers', 'Form requests', 'prepareForValidation', 'Gates'])
    ->test('Admin/ArticlesAdminTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start articles-admin')
            ->check(Check::fileExists('tests/Feature/Admin/ArticlesAdminTest.php')),

        Step::make('Create the admin controller')
            ->run('php artisan make:controller Admin/ArticleController --resource --model=Article')
            ->explain('Copy the pattern of app/Http/Controllers/Admin/ProgramController.php. Delete the show() method. The admin list shows drafts too, newest first.')
            ->check(Check::classExists('App\Http\Controllers\Admin\ArticleController')),

        Step::make('Create the form request')
            ->run('php artisan make:request ArticleRequest')
            ->explain('Copy app/Http/Requests/ProgramRequest.php. Fill the slug from the title when it is empty. The slug must be unique. published_at is optional.')
            ->docs('https://laravel.com/docs/validation#form-request-validation')
            ->check(Check::classExists('App\Http\Requests\ArticleRequest')),

        Step::make('Add the routes to the staff group')
            ->code("Route::resource('articles', Admin\\ArticleController::class)->except('show');")
            ->explain('Put it inside the staff group in routes/web.php. The group adds the admin. name prefix and the can:access-admin middleware.')
            ->check(Check::route('admin.articles.index', 'admin.articles.create', 'admin.articles.edit')),

        Step::make('Create the views')
            ->explain('Copy resources/views/admin/programs into resources/views/admin/articles. Use a textarea for the body. Use a datetime-local input for published_at. Show a Draft badge when it is empty.')
            ->check(Check::all(Check::view('admin.articles.index'), Check::view('admin.articles.create'), Check::view('admin.articles.edit'))),

        Step::make('Add a sidebar link')
            ->explain('Add "Articles" to the Staff group in resources/views/layouts/app.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('admin.articles.index')")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify articles-admin')
            ->check(Check::testPassed('ArticlesAdminTest')),
    ]);
