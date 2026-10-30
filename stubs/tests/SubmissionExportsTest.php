<?php

use App\Models\Field;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
| Acceptance test for the "Exports and notifications" feature.
|
| Staff download the submissions of a form as a CSV file. The columns come
| from every version, with the newest labels. Sensitive fields stay out.
| Each new submission queues an email to the form's notify_email.
*/

/**
 * Make a form with these fields, in this order. No field is required.
 *
 * @param  array<int, Field>  $fields
 */
function formForExport(array $fields, ?string $notifyEmail = null): Form
{
    $form = Form::factory()->create(['slug' => 'camp-form', 'name' => 'Camp form', 'notify_email' => $notifyEmail]);

    foreach (array_values($fields) as $position => $field) {
        $form->fields()->attach($field, ['position' => $position, 'required' => false]);
    }

    return $form;
}

/**
 * Download the export of the form as staff, and read the CSV into rows.
 *
 * @return list<list<string>>
 */
function exportRows(Form $form): array
{
    $response = test()->actingAs(User::factory()->staff()->create())
        ->get(route('admin.forms.export', $form))
        ->assertOk()
        ->assertDownload();

    $lines = array_filter(explode("\n", trim($response->streamedContent())));

    return array_values(array_map(str_getcsv(...), $lines));
}

test('only staff can export submissions', function () {
    $form = formForExport([]);

    $this->get(route('admin.forms.export', $form))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.forms.export', $form))
        ->assertForbidden();
});

test('the columns are the keys of every version, with the newest labels', function () {
    $school = Field::factory()->create(['key' => 'school', 'label' => 'School']);
    $shoes = Field::factory()->create(['key' => 'shoe_size', 'label' => 'Shoe size']);
    $form = formForExport([$school, $shoes]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary', 'shoe_size' => '4']);

    $school->update(['label' => 'Name of your school']);
    $form->fields()->detach($shoes);
    $form->fields()->attach(Field::factory()->create(['key' => 'swims', 'label' => 'Can your child swim?']), ['position' => 1, 'required' => false]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Brock Middle', 'swims' => 'Yes']);

    $rows = exportRows($form);

    expect($rows[0])->toBe(['Submitted at', 'Version', 'Name of your school', 'Can your child swim?', 'Shoe size'])
        ->and(array_slice($rows[1], 1))->toBe(['1', 'Westsyde Elementary', '', '4'])
        ->and(array_slice($rows[2], 1))->toBe(['2', 'Brock Middle', 'Yes', '']);
});

test('sensitive fields are not in the export', function () {
    $form = formForExport([
        Field::factory()->create(['key' => 'school', 'label' => 'School']),
        Field::factory()->sensitive()->create(['key' => 'allergies', 'label' => 'Allergies']),
    ]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary', 'allergies' => 'Peanuts']);

    $rows = exportRows($form);

    expect($rows[0])->not->toContain('Allergies')
        ->and($rows[1])->not->toContain('Peanuts')
        ->and($rows[1])->toContain('Westsyde Elementary');
});

test('the export shows the label of a choice, not its value', function () {
    $form = formForExport([Field::factory()->choice(['grade_1' => 'Grade 1', 'grade_2' => 'Grade 2'])->create(['key' => 'grade'])]);
    $form->publish();
    $this->post(route('forms.store', $form), ['grade' => 'grade_2']);

    expect(exportRows($form)[1])->toContain('Grade 2');
});

test('a spreadsheet does not run a formula from an answer', function () {
    $form = formForExport([Field::factory()->create(['key' => 'school'])]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => '=HYPERLINK("https://example.com")']);

    expect(exportRows($form)[1][2])->toStartWith("'=");
});

test('a new submission queues an email to the form address, without the answers', function () {
    Notification::fake();
    $form = formForExport([Field::factory()->create(['key' => 'school'])], 'camps@example.com');
    $form->publish();

    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        function (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable) {
            $mail = (string) $notification->toMail($notifiable)->render();

            return $notifiable->routes['mail'] === 'camps@example.com'
                && str_contains($mail, 'Camp form')
                && str_contains($mail, route('admin.submissions.show', Submission::sole()))
                && ! str_contains($mail, 'Westsyde Elementary');
        },
    );

    expect(new SubmissionReceived(Submission::sole()))->toBeInstanceOf(ShouldQueue::class);
});

test('a form without an email address sends no email', function () {
    Notification::fake();
    $form = formForExport([Field::factory()->create(['key' => 'school'])]);
    $form->publish();

    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);

    Notification::assertNothingSent();
});
