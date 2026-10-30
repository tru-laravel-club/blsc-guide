<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('registration-lists')
    ->title('Registration lists')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['registrations', 'submission-exports'])
    ->summary('Class lists, allergy lists and pickup lists for each session, made from the data.')
    ->teaches(['Field roles', 'Blade includes', 'Print views', 'Streamed CSV downloads', 'Sharing a helper class', 'Sensitive data'])
    ->test('RegistrationListsTest.php')
    ->intro(<<<'TEXT'
        On the first day of a camp, the instructor needs one sheet of paper: the children in the room, their allergies, the emergency contact, and the people who may pick each child up. Today that information is in the app, but it is spread over many submissions. In this feature, staff open one list for each session, print it, or download it as a CSV.

        The hard part is not the table. The hard part is to find the right answer in each submission. Staff change the form every season. They rename "Allergies" to "Food allergies". They archive a field and add a new one. A submission from January answered one version of the form, and a submission from March answered another. If the list looked for the label "Allergies", it would break at the first rename. If it looked for the key `allergies`, it would break when staff replace the field.

        So the list asks for a role. The Forms and fields feature gave the important fields a `FieldRole`, such as `Allergies` or `EmergencyPhone`. Each form version keeps a copy of its fields, with their roles. `Submission::answerForRole()` finds the field with the role in the version that the parent answered. The list never names a key or a label.

        Where do the answers come from: the submission of the registration, or the saved profile of the child? This feature uses the submission. It is what the parent told the centre for this session, and the parent confirmed sensitive answers when they registered. The profile can change later, or the parent can delete an answer. A list from the submissions shows what the centre was told, and every row has an answer from the same moment.

        The list holds health and contact details of children. The form export leaves sensitive fields out, because anybody on the staff can download it for any purpose. This list has one purpose: the safety of the children on the day. So it shows four roles and nothing more. It does not show the school, the medical notes or the photo consent.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
    ->docs('https://laravel.com/docs/responses#streamed-downloads', 'Laravel docs: Streamed downloads')
    ->reflect([
        'Why does the list ask for roles, not for field keys or labels? Describe the change by staff that breaks each of the other two.',
        'A parent updates the allergies of their child in the profile after they register. The list does not show the new answer. Is that a bug? What would you tell staff?',
        'The form export leaves sensitive fields out, but this list shows allergies. Why is that a fair decision? What else protects the data?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start registration-lists')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/RegistrationListsTest.php` into the app.

                Read `beforeEach()` first. The form has five fields. Four have a role, and `school` has none. Then read the rule "the list keeps every answer after staff rename and replace fields". Sam answered the first version. Then staff rename the phone field, archive `allergies`, and add a new field `food_allergies` with the role `Allergies`. Ada answers the new version. The list must show both allergies in the same column.

                The test also fixes the route names: `admin.sessions.registrations.index`, `.print` and `.export`.
                TEXT)
            ->think('The column headers in the test are "Emergency contact name" and "People who can pick up". Where do these words come from, if not from the field labels?')
            ->tryIt('Run `php artisan test tests/Feature/RegistrationListsTest.php`. All the rules fail, because the routes do not exist yet.')
            ->commit('Add the registration lists acceptance test')
            ->docs('https://laravel.com/docs/http-tests#session-and-authentication', 'Laravel docs: Session and authentication in tests')
            ->check(Check::fileExists('tests/Feature/RegistrationListsTest.php')),

        Step::make('Find an answer by its role, as text')
            ->explain(<<<'TEXT'
                Open `app/Models/Submission.php`. `answerForRole()` gives the raw answer, such as `grade_1` for a choice. A list for people needs the text, such as "Grade 1". `AnswerText::for()` already turns a raw answer into text, but it needs the field definition.

                Both methods must find the same field. So move the search into a private method, `fieldForRole(FieldRole $role): ?array`. It returns the field with the role in the version that the person answered. Then:

                - `answerForRole()` calls `fieldForRole()` and returns the raw answer, as before.
                - A new `answerTextForRole(FieldRole $role): string` calls `fieldForRole()` and returns `AnswerText::for($field, ...)`. When the version did not ask for the role, it returns an empty string.

                Other features use `answerForRole()`. Its behaviour must not change.
                TEXT)
            ->think('Why does the search use `$this->version->fields`, and not the fields of the form today?')
            ->hint('`collect($this->version->fields)->firstWhere(\'role\', $role->value)` finds the first field with the role, or `null`.')
            ->hint('Add `@phpstan-import-type Definition from FormRules` to the class docblock, so PHPStan knows the type of the array.')
            ->code(<<<'PHP'
                /**
                 * The answer to the field with this role, as text for people. Empty when the version did not ask it.
                 */
                public function answerTextForRole(FieldRole $role): string
                {
                    $field = $this->fieldForRole($role);

                    return $field === null ? '' : AnswerText::for($field, $this->answer($field['key']));
                }

                /**
                 * @return Definition|null
                 */
                private function fieldForRole(FieldRole $role): ?array
                {
                    return collect($this->version->fields)->firstWhere('role', $role->value);
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SubmissionsTest.php`. All the rules still pass, so `answerForRole()` works as before.')
            ->commit('Find submission answers as text by role')
            ->docs('https://laravel.com/docs/collections#method-first-where', 'Laravel docs: The firstWhere method')
            ->check(Check::fileContains('app/Models/Submission.php', 'function answerTextForRole')),

        Step::make('Share the CSV safety rule')
            ->explain(<<<'TEXT'
                Open `app/Http/Controllers/Admin/SubmissionExportController.php`. Its private `cell()` method stops a spreadsheet formula: a cell that starts with `=`, `+`, `-` or `@` gets a `'` in front. A parent could type `=HYPERLINK(...)` as an allergy. Your CSV needs the same protection.

                Do not copy the method. Move it into a small class, `app/Support/Csv.php`, as a static method `Csv::cell(string $value): string`. Then change the export controller to call `Csv::cell()`, and delete its private method.

                Run `SubmissionExportsTest`. It must still pass.
                TEXT)
            ->think('Next year, a security review finds one more dangerous first character. With one class, how many files change? With two copies?')
            ->hint('`array_map(Csv::cell(...), $row)` runs the method on each cell of a row.')
            ->code(<<<'PHP'
                namespace App\Support;

                final class Csv
                {
                    /**
                     * A spreadsheet runs a cell that starts with = + - or @ as a formula. Make it plain text.
                     */
                    public static function cell(string $value): string
                    {
                        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
                    }
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SubmissionExportsTest.php`. All the rules pass, as before your change.')
            ->commit('Share the CSV cell rule')
            ->docs('https://owasp.org/www-community/attacks/CSV_Injection', 'OWASP: CSV injection')
            ->check(Check::all(
                Check::fileExists('app/Support/Csv.php'),
                Check::fileContains('app/Http/Controllers/Admin/SubmissionExportController.php', 'Csv::cell'),
            )),

        Step::make('Add the routes and the controller')
            ->run('php artisan make:controller Admin/RegistrationListController')
            ->explain(<<<'TEXT'
                Add three `GET` routes in the staff group in `routes/web.php`:

                - `sessions/{session}/registrations`, named `sessions.registrations.index`: the page.
                - `sessions/{session}/registrations/print`, named `sessions.registrations.print`: the print view.
                - `sessions/{session}/registrations/export`, named `sessions.registrations.export`: the CSV.

                The group adds the `admin.` prefix to each name and checks that the user is staff.

                The three methods need the same data. Write it once, in a private method `list(ProgramSession $session): array`. It returns the session with its program, the list of roles, and the registrations:

                - only `Confirmed` registrations. A pending registration is not paid, and a cancelled child does not come.
                - with `child.user` and `submission.version`, so the page does not run a query for each row.
                - sorted by the name of the child, as a class list is.

                Put the roles in a constant on the controller: `Allergies`, `EmergencyName`, `EmergencyPhone` and `PickupPeople`. The page, the print view and the CSV all read it. To add a column next year, staff give a field a role, and a developer adds one case to this list.
                TEXT)
            ->think('Why does the list not include `MedicalNotes`, even though the role exists? Who decides what goes on a sheet of paper in a classroom?')
            ->hint('`->get()->sortBy(\'child.name\')->values()` sorts the loaded registrations by the name of the child and numbers them again from 0.')
            ->code(<<<'PHP'
                /**
                 * The roles that the list shows. The list asks for roles, never for field keys or labels.
                 *
                 * @var list<FieldRole>
                 */
                private const ROLES = [FieldRole::Allergies, FieldRole::EmergencyName, FieldRole::EmergencyPhone, FieldRole::PickupPeople];

                private function list(ProgramSession $session): array
                {
                    return [
                        'session' => $session->load('program'),
                        'roles' => self::ROLES,
                        'registrations' => $session->registrations()
                            ->where('status', RegistrationStatus::Confirmed)
                            ->with(['child.user', 'submission.version'])
                            ->get()
                            ->sortBy('child.name')
                            ->values(),
                    ];
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=sessions.registrations -v`. You see three routes. Under each one, the list of middleware has `Authorize:access-admin`.')
            ->commit('Add the registration list routes')
            ->docs('https://laravel.com/docs/routing#route-group-middleware', 'Laravel docs: Route group middleware')
            ->docs('https://laravel.com/docs/eloquent-relationships#nested-eager-loading', 'Laravel docs: Nested eager loading')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('admin.sessions.registrations.index', 'admin.sessions.registrations.print', 'admin.sessions.registrations.export')),

        Step::make('Build the list page and the print view')
            ->explain(<<<'TEXT'
                The page and the print view show the same table. Write the table once, in `resources/views/admin/registration-lists/table.blade.php`, and include it in both views with `@include`.

                The table has one row for each child: the name, the age on the first day of the session, the parent, and one column for each role. The column header is `$role->label()`, never the label of a field. Show "—" for an empty answer, so staff see that the parent left it empty. Use `white-space: pre-line` on the answers, so a list of pickup people keeps its lines.

                `index.blade.php` uses the staff layout. It shows the dates, the number of confirmed children, and two buttons: **Print** (opens the print view in a new tab) and **Download CSV**. Add one sentence that asks staff to shred the paper after the session.

                `print.blade.php` is a plain HTML page, without the staff menu and without Vite. A few lines of CSS in a `<style>` tag are enough. Add a **Print** button that runs `window.print()`, and hide it on paper with `@media print`.
                TEXT)
            ->think('Why is the print view a separate page, and not the staff page with a print style sheet?')
            ->hint('`@include(\'admin.registration-lists.table\')` gives the included view all the variables of the parent view.')
            ->code(<<<'PHP'
                @foreach ($registrations as $registration)
                    <tr>
                        <td>{{ $registration->child->name }}</td>
                        <td>{{ $registration->child->ageOn($session->starts_on) }}</td>
                        <td>{{ $registration->child->user->name }}</td>
                        @foreach ($roles as $role)
                            <td class="answer">{{ $registration->submission->answerTextForRole($role) ?: '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                PHP)
            ->tryIt('Register a child for a club and pay. Log in as `staff@example.com` and open http://localhost:8000/admin/sessions/2/registrations. You see your child with the answers. Click Print. A plain page opens, and the print dialog shows one clean table.')
            ->commit('Add the registration list page and print view')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::all(
                Check::view('admin.registration-lists.index'),
                Check::view('admin.registration-lists.print'),
            )),

        Step::make('Download the list as a CSV')
            ->explain(<<<'TEXT'
                Copy the pattern of `SubmissionExportController`: `response()->streamDownload()` writes the rows one at a time with `fputcsv()`.

                The first row is the header: "Child", "Age on the first day", "Parent", "Parent email", and the label of each role. Then one row for each registration. Pass every cell through `Csv::cell()`.

                Add one more header: `Cache-Control: no-store, private`. It asks the browser and any proxy not to keep a copy of the file. The list holds children's health details, so it must not stay in a cache.
                TEXT)
            ->think('The CSV has a "Parent email" column, but the page does not. Who uses the CSV, and for what?')
            ->hint('The third argument of `streamDownload()` is the file name, and the fourth is an array of headers.')
            ->code(<<<'PHP'
                return response()->streamDownload(function () use ($list) {
                    $file = fopen('php://output', 'w');

                    if ($file === false) {
                        return;
                    }

                    fputcsv($file, ['Child', 'Age on the first day', 'Parent', 'Parent email', ...array_map(fn (FieldRole $role) => $role->label(), $list['roles'])]);

                    foreach ($list['registrations'] as $registration) {
                        fputcsv($file, array_map(Csv::cell(...), [
                            $registration->child->name,
                            (string) $registration->child->ageOn($list['session']->starts_on),
                            $registration->child->user->name,
                            $registration->child->user->email,
                            ...array_map(fn (FieldRole $role) => $registration->submission->answerTextForRole($role), $list['roles']),
                        ]));
                    }

                    fclose($file);
                }, "registrations-{$session->id}.csv", ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
                PHP)
            ->tryIt('On the list page, click Download CSV. Open the file in a spreadsheet. You see one column for each role, and one row for your child.')
            ->commit('Download registration lists as CSV')
            ->docs('https://laravel.com/docs/responses#streamed-downloads', 'Laravel docs: Streamed downloads')
            ->check(Check::fileContains('app/Http/Controllers/Admin/RegistrationListController.php', 'streamDownload')),

        Step::make('Link each session to its list')
            ->explain(<<<'TEXT'
                Staff must find the list without a URL. Open `resources/views/admin/programs/edit.blade.php`. The sessions table has a row for each session. Add a column "Registrations" with a link **List** to `admin.sessions.registrations.index`.

                Change the `colspan` of the empty row too, so it covers the new column.
                TEXT)
            ->think('A program with the `apply` mode also has sessions. Should its rows show the link? What does the list show for it?')
            ->hint('`route(\'admin.sessions.registrations.index\', $session)` builds the link.')
            ->tryIt('As staff, open Programs and a club. Each session has a List link. Click it, and you see the list of that session.')
            ->commit('Link sessions to their registration lists')
            ->check(Check::fileContains('resources/views/admin/programs/edit.blade.php', 'admin.sessions.registrations.index')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify registration-lists')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                You changed `Submission` and `SubmissionExportController`. `SubmissionsTest` and `SubmissionExportsTest` prove that they still work.

                In your pull request, add a screenshot of the print view with test data, never with real children.
                TEXT)
            ->think('PHPStan says that the `list()` method returns an array with no value type. How do you describe its three keys in the docblock?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for registration lists')
            ->check(Check::testPassed('RegistrationListsTest')),
    ]);
