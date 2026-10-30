<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('program-catalog')
    ->title('Camps and clubs pages')
    ->section(Section::Foundation)
    ->level(1)
    ->needs(['layout', 'programs'])
    ->summary('Public lists of camps and clubs, and a detail page for each program with its upcoming dates.')
    ->teaches(['Controllers', 'Route defaults', 'Eager loading', 'Query scopes'])
    ->intro(<<<'TEXT'
        This feature is already in `main`. The BIG Night Out and QUARKS pages reuse it, so read it before you build them.

        The camps page and the clubs page are the same page with a different program type. So one controller method serves both. Each route gives the method a different type.

        Each program card shows its next session. To get the sessions of every program in one query, and not one query for each program, the controller loads them up front. This is called eager loading.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->docs('https://laravel.com/docs/eloquent-relationships#eager-loading', 'Laravel docs: Eager loading')
    ->steps([
        Step::make('Read the camps and clubs lists')
            ->explain(<<<'TEXT'
                Open `routes/web.php`. The `camps.index` and `clubs.index` routes both call `ProgramController@index`. Each route uses `->defaults('type', ...)` to give the method its type.

                Then open `app/Http/Controllers/ProgramController.php`. In `index()`, find `with('upcomingSessions')`. It loads the sessions of all programs in one extra query.
                TEXT)
            ->think('There are 20 clubs. Without `with()`, how many database queries does the page make?')
            ->tryIt('Open http://localhost:8000/camps and http://localhost:8000/clubs. They use the same view, `resources/views/programs/index.blade.php`.')
            ->check(Check::route('camps.index', 'clubs.index')),

        Step::make('Read the program detail page')
            ->explain(<<<'TEXT'
                The route is `/programs/{program:slug}`. Laravel finds the program by its slug, not by its id, so the URL reads well, such as `/programs/junior-robotics-coding-club`.

                In `show()`, `abort_unless($program->is_published, 404)` hides draft programs. A visitor who guesses the slug of a draft sees "Not found".
                TEXT)
            ->think('Why does `show()` check `is_published`? The list already hides drafts.')
            ->tryIt('Open http://localhost:8000/clubs, and click a club. Look at the slug in the URL.')
            ->docs('https://laravel.com/docs/routing#implicit-binding', 'Laravel docs: Implicit binding')
            ->check(Check::route('programs.show')),
    ]);
