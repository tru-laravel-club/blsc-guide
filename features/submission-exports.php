<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('submission-exports')
    ->title('Exports and notifications')
    ->section(Section::Forms)
    ->level(2)
    ->needs(['submissions'])
    ->summary('Staff export submissions to CSV and get an email for each new one. Sensitive fields stay out of exports.')
    ->teaches(['Streamed downloads', 'CSV files', 'Lazy collections', 'Queued notifications', 'On-demand notifications', 'Notification::fake'])
    ->test('SubmissionExportsTest.php')
    ->intro(<<<'TEXT'
        Staff read submissions one at a time on the staff pages. But they also plan with them: how many children need a T-shirt, which schools the children come from. For that, they want a spreadsheet. And they want to know when a new submission arrives, without opening the site every hour.

        In this feature, you build both. A "Download CSV" button on the submissions page sends a CSV file. A queued email goes to the form's `notify_email` for each new submission.

        The export has one hard question: which columns? A form changes over time. Version 1 asked about shoe sizes. Version 2 removed that question and added "Can your child swim?". A spreadsheet of the whole season must show both. So the columns are **every key from every version**. Each column uses the **newest label** for its key, because staff read the export today. A submission that did not see a question gets an empty cell.

        Two rules protect the children. Sensitive fields, such as allergies and medical notes, never go into the export. A CSV file gets copied, emailed and forgotten on laptops. Staff who need allergies read them on the site, where a login protects them. And the email says only that a submission arrived, with a link. It does not hold the answers, for the same reason.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
    ->docs('https://laravel.com/docs/responses#streamed-downloads', 'Laravel docs: Streamed downloads')
    ->docs('https://laravel.com/docs/notifications#on-demand-notifications', 'Laravel docs: On-demand notifications')
    ->reflect([
        'Why does the export use keys from every version, not only the latest?',
        'A staff member asks for allergies in the export "just this once". What do you answer? Where can they read the allergies instead?',
        'Why does the email hold a link and not the answers?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start submission-exports')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/SubmissionExportsTest.php` into the app.

                The helper `exportRows()` downloads the export as staff and turns the CSV text into rows. Read the rule "the columns are the keys of every version, with the newest labels". It publishes two versions with different questions, sends one submission to each, and then checks every cell.

                The notification rules use `Notification::fake()`. Laravel then records each notification and sends nothing. The test checks what would have been sent, and to whom.
                TEXT)
            ->think('In the two-version rule, why is the "Shoe size" column last, after a question that came later?')
            ->tryIt('Run `php artisan test tests/Feature/SubmissionExportsTest.php`. All the rules fail, because the export route does not exist yet.')
            ->commit('Add the submission exports acceptance test')
            ->docs('https://laravel.com/docs/notifications#testing', 'Laravel docs: Testing notifications')
            ->check(Check::fileExists('tests/Feature/SubmissionExportsTest.php')),

        Step::make('Find the columns from every version')
            ->explain(<<<'TEXT'
                Add `exportColumns(): array` to `Form`. It returns the field definitions for the columns, keyed by field key.

                Go through the versions from the newest to the oldest. For each field, keep the first definition that you see for its key. Because you start with the newest version, that is the newest label. A key that is only in an old version comes later, with the label from the last version that asked it.

                Then leave out every key that was sensitive in any version. If staff marked a field as sensitive only last month, its old answers are still sensitive.
                TEXT)
            ->think('A field was not sensitive in version 1 and is sensitive in version 2. Why leave it out of the whole export, and not only for version 2?')
            ->hint('`$columns[$field[\'key\']] ??= $field;` sets the value only when the key is not there yet.')
            ->hint('`array_diff_key($columns, $sensitive)` removes the keys that are in `$sensitive`.')
            ->code(<<<'PHP'
                /**
                 * The columns of the export: every key from every version, with its newest definition.
                 * Sensitive fields are left out.
                 *
                 * @return array<string, Definition>
                 */
                public function exportColumns(): array
                {
                    $columns = [];
                    $sensitive = [];

                    foreach ($this->versions()->get()->reverse() as $version) {
                        foreach ($version->fields as $field) {
                            $columns[$field['key']] ??= $field;

                            if ($field['sensitive'] ?? false) {
                                $sensitive[$field['key']] = true;
                            }
                        }
                    }

                    return array_diff_key($columns, $sensitive);
                }
                PHP)
            ->tryIt('In tinker, run `array_keys(App\Models\Form::firstWhere(\'slug\', \'contact\')->exportColumns())`. You see the keys of the contact form.')
            ->commit('Find the export columns of a form')
            ->check(Check::fileContains('app/Models/Form.php', 'function exportColumns')),

        Step::make('Stream the CSV file')
            ->run('php artisan make:controller Admin/SubmissionExportController --invokable')
            ->explain(<<<'TEXT'
                Add `Route::get('forms/{form}/export', Admin\SubmissionExportController::class)->name('forms.export')` in the staff group. The controller has one job, so it is **invokable**: one `__invoke()` method.

                A busy form can have thousands of submissions. Do not build the whole file in memory. `response()->streamDownload()` takes a function that writes to `php://output`, and Laravel sends each line to the browser as it is written. Read the submissions with `lazy()`, which loads them in small groups.

                Write the header row first: "Submitted at", "Version", and the label of each column. Then one row for each submission: the date, the version number, and `AnswerText::for()` for each column. A question that the version did not ask gives `null`, and `AnswerText` turns that into an empty cell.

                One more danger. A spreadsheet runs a cell that starts with `=`, `+`, `-` or `@` as a formula. A visitor can type `=HYPERLINK(...)` into a public form. Put a `'` in front of such a value, so the spreadsheet shows it as text.
                TEXT)
            ->think('A parent types "-" as an answer, or a phone number starts with "+1". The export shows `\'-` and `\'+1`. Is that a problem for staff? Is it worth it?')
            ->hint('`fputcsv($file, [...])` writes one row, with quotes where a value needs them.')
            ->hint('`lazy()` needs a sort order. Through `hasManyThrough`, name the table: `orderBy(\'submissions.id\')`.')
            ->code(<<<'PHP'
                public function __invoke(Form $form): StreamedResponse
                {
                    $columns = $form->exportColumns();

                    return response()->streamDownload(function () use ($form, $columns) {
                        $file = fopen('php://output', 'w');

                        if ($file === false) {
                            return;
                        }

                        fputcsv($file, ['Submitted at', 'Version', ...array_column($columns, 'label')]);

                        foreach ($form->submissions()->with('version')->orderBy('submissions.id')->lazy() as $submission) {
                            fputcsv($file, [
                                $submission->submitted_at->toDateTimeString(),
                                $submission->version->number,
                                ...array_map(
                                    fn (array $field) => $this->cell(AnswerText::for($field, $submission->answer($field['key']))),
                                    array_values($columns),
                                ),
                            ]);
                        }

                        fclose($file);
                    }, "{$form->slug}-submissions.csv", ['Content-Type' => 'text/csv']);
                }

                /**
                 * A spreadsheet runs a cell that starts with = + - or @ as a formula. Make it plain text.
                 */
                private function cell(string $value): string
                {
                    return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
                }
                PHP)
            ->tryIt('Log in as staff, open Forms, and open the submissions of the contact form. You add the button in the next step, so open http://localhost:8000/admin/forms/1/export directly. Your browser downloads `contact-submissions.csv`. Open it in a spreadsheet.')
            ->commit('Export submissions as CSV')
            ->docs('https://laravel.com/docs/responses#streamed-downloads', 'Laravel docs: Streamed downloads')
            ->docs('https://laravel.com/docs/eloquent#chunking-using-lazy-collections', 'Laravel docs: Lazy collections')
            ->docs('https://laravel.com/docs/controllers#single-action-controllers', 'Laravel docs: Invokable controllers')
            ->check(Check::route('admin.forms.export')),

        Step::make('Add the download button')
            ->explain(<<<'TEXT'
                On `resources/views/admin/submissions/index.blade.php`, add a "Download CSV" button next to "All forms". It links to `admin.forms.export` for the form.

                Staff will look for allergies in the file and not find them. Add one sentence under the button that says why: sensitive answers are only on the site.
                TEXT)
            ->think('Should the button show when the form has no submissions? What does the file hold then?')
            ->code(<<<'PHP'
                <april:button-link href="{{ route('admin.forms.export', $form) }}">Download CSV</april:button-link>
                PHP)
            ->tryIt('Open the submissions of the contact form as staff and click Download CSV.')
            ->commit('Add the CSV download button')
            ->check(Check::fileContains('resources/views/admin/submissions/index.blade.php', 'admin.forms.export')),

        Step::make('Queue an email for each new submission')
            ->run('php artisan make:notification SubmissionReceived')
            ->explain(<<<'TEXT'
                `SubmissionReceived` takes the submission in its constructor. `toMail()` builds a short message: the name of the form, and a button that opens the submission on the staff pages. No answers.

                Implement `ShouldQueue`. Then Laravel puts the email in the queue, and the visitor does not wait for the mail server. The design asks for this: email never slows down a request.

                Who gets it? The address is not a user in the database. It is the `notify_email` of the form, such as `camps@blscs.org`. An **on-demand notification** sends to an address without a model: `Notification::route('mail', $address)->notify(...)`.

                In the public `FormController::store()`, keep the new submission in a variable. If the form has a `notify_email`, send the notification.
                TEXT)
            ->think('The mail server is down for ten minutes. What happens to a queued email? What would happen without the queue?')
            ->hint('The test reads the mail with `$notification->toMail($notifiable)->render()`. Use `route(\'admin.submissions.show\', $this->submission)` for the button.')
            ->code(<<<'PHP'
                // app/Notifications/SubmissionReceived.php
                class SubmissionReceived extends Notification implements ShouldQueue
                {
                    use Queueable;

                    public function __construct(public Submission $submission) {}

                    public function via(object $notifiable): array
                    {
                        return ['mail'];
                    }

                    public function toMail(object $notifiable): MailMessage
                    {
                        $form = $this->submission->version->form;

                        return (new MailMessage)
                            ->subject("New submission: {$form->name}")
                            ->line("Someone sent the form \"{$form->name}\".")
                            ->action('Open the submission', route('admin.submissions.show', $this->submission))
                            ->line('Log in as staff to read the answers.');
                    }
                }

                // app/Http/Controllers/FormController.php, in store()
                if ($form->notify_email) {
                    Notification::route('mail', $form->notify_email)->notify(new SubmissionReceived($submission));
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Send the contact form at http://localhost:8000/forms/contact. Then run `php artisan queue:work --once`. You see `SubmissionReceived ... DONE`.

                The app writes email to the log on your machine. Open `storage/logs/laravel.log` and search for "Open the submission". The link points to the new submission. The answers are not in the email.
                TEXT)
            ->commit('Email staff about new submissions')
            ->docs('https://laravel.com/docs/notifications#queueing-notifications', 'Laravel docs: Queueing notifications')
            ->docs('https://laravel.com/docs/notifications#on-demand-notifications', 'Laravel docs: On-demand notifications')
            ->docs('https://laravel.com/docs/notifications#formatting-mail-messages', 'Laravel docs: Formatting mail messages')
            ->docs('https://laravel.com/docs/queues#running-the-queue-worker', 'Laravel docs: Running the queue worker')
            ->check(Check::classExists('App\Notifications\SubmissionReceived')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify submission-exports')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`.

                Look at the other tests that send forms, such as `SubmissionsTest.php`. Their forms have no `notify_email`, so they send nothing and need no fake. If a test sends a real email by mistake, the log on your machine shows it.

                In your pull request, attach an export of the seeded contact form.
                TEXT)
            ->think('Why does the test check that the email does not contain the answer, and not only that it was sent?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for exports')
            ->check(Check::testPassed('SubmissionExportsTest')),
    ]);
