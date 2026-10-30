<?php

use App\Enums\FieldType;
use App\Enums\InquiryStatus;
use App\Models\Field;
use App\Models\Form;
use App\Models\Inquiry;
use App\Models\Program;
use App\Models\User;
use App\Notifications\SubmissionReceived;
use Illuminate\Support\Facades\Notification;

/*
| Acceptance test for the "Requests" feature.
|
| A program with the request mode takes requests, such as a birthday party or a
| school booking. A visitor does not need an account: a guest gives a name and an
| email. Staff read each request, reply by email, and close it.
|
| The model is called Inquiry, because Laravel already has a Request class.
*/

/**
 * Make a published program with the request mode and a published form that asks one required question.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $form
 */
function programTakingRequests(array $attributes = [], array $form = []): Program
{
    $question = Field::firstWhere('key', 'party_date')
        ?? Field::factory()->type(FieldType::Date)->create(['key' => 'party_date', 'label' => 'Preferred party date']);

    $requestForm = Form::factory()->create(['name' => 'Party request', ...$form]);
    $requestForm->fields()->attach($question, ['position' => 0, 'required' => true]);
    $requestForm->publish();

    return Program::factory()->create(['name' => 'Birthday Parties', 'signup_mode' => 'request', 'form_id' => $requestForm->id, ...$attributes]);
}

/**
 * The URL parameters of a program page and its sign-up pages.
 *
 * @return array{category: mixed, program: Program}
 */
function requestParameters(Program $program): array
{
    return ['category' => $program->category, 'program' => $program];
}

test('a program with the request mode and a published form shows a request button', function () {
    $program = programTakingRequests();

    $this->get(route('programs.show', requestParameters($program)))
        ->assertOk()
        ->assertSee('Send a request')
        ->assertSee(route('inquiries.create', requestParameters($program)))
        ->assertDontSee('Requests open soon');
});

test('a guest opens the request form without an account', function () {
    $program = programTakingRequests();

    $this->get(route('inquiries.create', requestParameters($program)))
        ->assertOk()
        ->assertSee('Birthday Parties')
        ->assertSee('Your name')
        ->assertSee('Your email address')
        ->assertSee('Preferred party date');
});

test('a guest sends a request with a name and an email', function () {
    $program = programTakingRequests();

    $this->post(route('inquiries.store', requestParameters($program)), [
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'party_date' => '2026-12-05',
    ])->assertRedirect(route('programs.show', requestParameters($program)));

    $inquiry = Inquiry::sole();

    expect($inquiry->program->is($program))->toBeTrue()
        ->and($inquiry->user_id)->toBeNull()
        ->and($inquiry->name)->toBe('Sam Rivera')
        ->and($inquiry->email)->toBe('sam@example.com')
        ->and($inquiry->status)->toBe(InquiryStatus::Open)
        ->and($inquiry->submission->answer('party_date'))->toBe('2026-12-05')
        ->and($inquiry->submission->user_id)->toBeNull();
});

test('a guest must give a name, an email and the answers, and sees every error at once', function () {
    $program = programTakingRequests();

    $this->post(route('inquiries.store', requestParameters($program)), ['email' => 'not an email'])
        ->assertSessionHasErrors(['name', 'email', 'party_date']);

    expect(Inquiry::count())->toBe(0);
});

test('a logged-in visitor sends a request with the name and email of the account', function () {
    $program = programTakingRequests();
    $user = User::factory()->create(['name' => 'Alex Chen', 'email' => 'alex@example.com']);

    $this->actingAs($user)
        ->get(route('inquiries.create', requestParameters($program)))
        ->assertOk()
        ->assertDontSee('Your email address')
        ->assertSee('alex@example.com');

    $this->actingAs($user)
        ->post(route('inquiries.store', requestParameters($program)), ['party_date' => '2026-12-05'])
        ->assertRedirect();

    expect(Inquiry::sole())
        ->user_id->toBe($user->id)
        ->name->toBe('Alex Chen')
        ->email->toBe('alex@example.com');
});

test('a request to a program with another mode is refused', function (array $attributes) {
    $program = programTakingRequests($attributes);

    $this->get(route('inquiries.create', requestParameters($program)))->assertNotFound();
    $this->post(route('inquiries.store', requestParameters($program)), [
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'party_date' => '2026-12-05',
    ])->assertNotFound();

    expect(Inquiry::count())->toBe(0);
})->with([
    'apply mode' => [['signup_mode' => 'apply']],
    'register mode' => [['signup_mode' => 'register']],
    'no sign-up' => [['signup_mode' => 'none']],
    'unpublished program' => [['is_published' => false]],
]);

test('staff get the notice of the request form', function () {
    Notification::fake();
    $program = programTakingRequests(form: ['notify_email' => 'parties@example.com']);

    $this->post(route('inquiries.store', requestParameters($program)), [
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'party_date' => '2026-12-05',
    ]);

    Notification::assertSentOnDemand(SubmissionReceived::class);
});

test('only staff can see the requests', function () {
    $inquiry = Inquiry::factory()->for(programTakingRequests())->create();

    $this->actingAs(User::factory()->create())->get(route('admin.programs.inquiries.index', $inquiry->program))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.inquiries.show', $inquiry))->assertForbidden();
    $this->actingAs(User::factory()->create())->patch(route('admin.inquiries.update', $inquiry), ['status' => 'closed'])->assertForbidden();
});

test('staff list the requests of one program', function () {
    $program = programTakingRequests();
    Inquiry::factory()->for($program)->create(['name' => 'Sam Rivera']);
    Inquiry::factory()->for(programTakingRequests(['name' => 'School bookings']))->create(['name' => 'Westsyde Elementary']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.inquiries.index', $program))
        ->assertOk()
        ->assertSee('Sam Rivera')
        ->assertSee('Open')
        ->assertDontSee('Westsyde Elementary');
});

test('staff read one request and can reply by email', function () {
    $program = programTakingRequests();

    $this->post(route('inquiries.store', requestParameters($program)), [
        'name' => 'Sam Rivera',
        'email' => 'sam@example.com',
        'party_date' => '2026-12-05',
    ]);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.inquiries.show', Inquiry::sole()))
        ->assertOk()
        ->assertSee('Sam Rivera')
        ->assertSee('Preferred party date')
        ->assertSee('2026-12-05')
        ->assertSee('mailto:sam@example.com', false);
});

test('staff mark a request as replied, close it and open it again', function () {
    $inquiry = Inquiry::factory()->for(programTakingRequests())->create();
    $staff = User::factory()->staff()->create();

    foreach (['replied', 'closed', 'open'] as $status) {
        $this->actingAs($staff)
            ->patch(route('admin.inquiries.update', $inquiry), ['status' => $status])
            ->assertRedirect(route('admin.inquiries.show', $inquiry));

        expect($inquiry->fresh()->status)->toBe(InquiryStatus::from($status));
    }
});

test('an unknown status is refused', function () {
    $inquiry = Inquiry::factory()->for(programTakingRequests())->create();

    $this->actingAs(User::factory()->staff()->create())
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'accepted'])
        ->assertSessionHasErrors('status');

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Open);
});
