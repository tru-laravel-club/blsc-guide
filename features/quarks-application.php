<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('quarks-application')
    ->title('QUARKS application')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['quarks-info', 'accounts', 'staff-roles', 'programs-admin'])
    ->summary('Students in Grade 8 or 9 apply online. Staff review each application and accept or decline it. The students are children, so collect only what BLSC needs.')
    ->teaches(['Enums', 'Enum casts', 'Default attribute values', 'Form requests', 'Two controllers for one model'])
    ->test('QuarksApplicationTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start quarks-application')
            ->check(Check::fileExists('tests/Feature/QuarksApplicationTest.php')),

        Step::make('Create the status enum')
            ->run('php artisan make:enum QuarksApplicationStatus --string')
            ->explain('Add three cases: Pending (pending), Accepted (accepted) and Declined (declined). Add a label() method, as ProgramType has.')
            ->check(Check::classExists('App\Enums\QuarksApplicationStatus')),

        Step::make('Create the QuarksApplication model, migration and factory')
            ->run('php artisan make:model QuarksApplication -mf')
            ->check(Check::classExists('App\Models\QuarksApplication')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->code("\$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n\$table->string('student_name');\n\$table->string('school');\n\$table->unsignedTinyInteger('grade');\n\$table->text('reason');\n\$table->string('status')->default('pending');")
            ->explain('Cast status to QuarksApplicationStatus on the model. Do not put status in #[Fillable], so an applicant cannot accept their own application.')
            ->check(Check::table('quarks_applications', ['user_id', 'student_name', 'school', 'grade', 'reason', 'status'])),

        Step::make('Fill in the factory')
            ->code("'user_id' => User::factory(),\n'student_name' => fake()->name(),\n'school' => fake()->company().' Secondary',\n'grade' => fake()->randomElement([8, 9]),\n'reason' => fake()->paragraph(),")
            ->explain('The tests use the factory to make applications for staff to review.')
            ->check(Check::fileContains('database/factories/QuarksApplicationFactory.php', "'student_name'")),

        Step::make('Connect users and applications')
            ->explain('Add quarksApplications(): HasMany on User and user(): BelongsTo on QuarksApplication.')
            ->check(Check::fileContains('app/Models/User.php', 'function quarksApplications')),

        Step::make('Create the application form')
            ->run("php artisan make:controller QuarksApplicationController\nphp artisan make:request QuarksApplicationRequest")
            ->explain('Add create() and store() methods. store() saves the application through $request->user()->quarksApplications(). The grade must be 8 or 9.')
            ->check(Check::all(Check::classExists('App\Http\Controllers\QuarksApplicationController'), Check::classExists('App\Http\Requests\QuarksApplicationRequest'))),

        Step::make('Add the routes for logged-in users')
            ->code("Route::get('/quarks/apply', [QuarksApplicationController::class, 'create'])->name('quarks.apply');\nRoute::post('/quarks/apply', [QuarksApplicationController::class, 'store'])->name('quarks.apply.store');")
            ->explain('Put them inside the auth and verified group. Add an "Apply now" button to resources/views/programs/intros/quarks.blade.php.')
            ->check(Check::route('quarks.apply', 'quarks.apply.store')),

        Step::make('Create the staff review pages')
            ->run('php artisan make:controller Admin/QuarksApplicationController')
            ->code("Route::resource('quarks-applications', Admin\\QuarksApplicationController::class)\n    ->only(['index', 'show', 'update']);")
            ->explain('index() lists the applications, pending first. show() shows one application. update() changes the status only. Put the route in the staff group.')
            ->check(Check::route('admin.quarks-applications.index', 'admin.quarks-applications.show', 'admin.quarks-applications.update')),

        Step::make('Add a sidebar link')
            ->explain('Add "QUARKS applications" to the Staff group in resources/views/layouts/app.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('admin.quarks-applications.index')")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify quarks-application')
            ->check(Check::testPassed('QuarksApplicationTest')),
    ]);
