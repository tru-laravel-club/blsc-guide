<?php

use App\Cart;
use App\Enums\FieldRole;
use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Enums\RegistrationStatus;
use App\Models\Child;
use App\Models\Field;
use App\Models\Form;
use App\Models\Order;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Registrations" feature.
|
| A parent chooses a session of a program with the register mode, chooses one
| of their children, and answers the program's form. The registration goes into
| the cart. The parent pays through the cart, and the payment confirms it.
| The app checks the registration dates, the age on the first day, a free spot,
| and that a child is not registered twice.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));
});

/**
 * Make a published program with the register mode, for ages 6 to 9, and a published form
 * with a required child question and an optional family question.
 *
 * @param  array<string, mixed>  $attributes
 */
function programTakingRegistrations(array $attributes = []): Program
{
    $allergies = Field::firstWhere('key', 'allergies') ?? Field::factory()->create([
        'key' => 'allergies',
        'label' => 'Allergies',
        'type' => FieldType::LongText,
        'scope' => FieldScope::Child,
        'role' => FieldRole::Allergies,
    ]);
    $phone = Field::firstWhere('key', 'emergency_phone') ?? Field::factory()->type(FieldType::Phone)->create([
        'key' => 'emergency_phone',
        'label' => 'Emergency phone',
        'scope' => FieldScope::Family,
        'role' => FieldRole::EmergencyPhone,
    ]);

    $form = Form::factory()->create(['name' => 'Club registration']);
    $form->fields()->attach($allergies, ['position' => 0, 'required' => true]);
    $form->fields()->attach($phone, ['position' => 1, 'required' => false]);
    $form->publish();

    return Program::factory()->create([
        'name' => 'Junior Robotics Club',
        'signup_mode' => 'register',
        'form_id' => $form->id,
        'age_min' => 6,
        'age_max' => 9,
        ...$attributes,
    ]);
}

/**
 * Make a session that starts on December 1, 2026, with open registration and 12 spots.
 *
 * @param  array<string, mixed>  $attributes
 */
function registrationSession(?Program $program = null, array $attributes = []): ProgramSession
{
    return ProgramSession::factory()->for($program ?? programTakingRegistrations())->create([
        'label' => 'Winter 2026',
        'starts_on' => '2026-12-01',
        'ends_on' => '2027-02-02',
        'price_cents' => 15000,
        'member_price_cents' => null,
        'capacity' => 12,
        'registration_opens_at' => '2026-11-01 09:00',
        'registration_closes_at' => null,
        ...$attributes,
    ]);
}

/**
 * The URL parameters of the registration page of a session.
 *
 * @return array{category: mixed, program: Program, session: ProgramSession}
 */
function registerParameters(ProgramSession $session, array $query = []): array
{
    return ['category' => $session->program->category, 'program' => $session->program, 'session' => $session, ...$query];
}

/**
 * A child of this parent who is 7 years old on December 1, 2026.
 */
function sevenYearOld(User $parent, string $name = 'Sam'): Child
{
    return Child::factory()->for($parent)->create(['name' => $name, 'birthdate' => '2019-06-15']);
}

test('a register program with a published form shows a Register button for each open session', function () {
    $program = programTakingRegistrations();
    $open = registrationSession($program, ['label' => 'Winter 2026']);
    $closed = registrationSession($program, ['label' => 'Fall 2026', 'registration_closes_at' => '2026-11-01 09:00']);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertOk()
        ->assertSee('Registration is open')
        ->assertSee(route('registrations.create', registerParameters($open)))
        ->assertDontSee(route('registrations.create', registerParameters($closed)))
        ->assertDontSee('Registration opens soon');
});

test('a register program without a published form still says that registration opens soon', function () {
    $program = Program::factory()->create(['signup_mode' => 'register', 'form_id' => Form::factory()->create()->id]);
    $session = registrationSession($program);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertSee('Registration opens soon')
        ->assertDontSee(route('registrations.create', registerParameters($session)));
});

test('a guest must log in to register', function () {
    $session = registrationSession();

    $this->get(route('registrations.create', registerParameters($session)))->assertRedirect(route('login'));
    $this->post(route('registrations.store', registerParameters($session)))->assertRedirect(route('login'));

    expect(Registration::count())->toBe(0);
});

test('a parent with no children is asked to add a child first', function () {
    $session = registrationSession();

    $this->actingAs(User::factory()->create())
        ->get(route('registrations.create', registerParameters($session)))
        ->assertOk()
        ->assertSee('Winter 2026')
        ->assertSee(route('children.create'));
});

test('the parent chooses a child and sees the form with the saved answers filled in', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $sam = sevenYearOld($parent);
    sevenYearOld($parent, 'Alex');
    $sam->profileValues()->create(['field_id' => Field::firstWhere('key', 'allergies')->id, 'value' => 'Peanuts', 'confirmed_at' => now()]);

    $this->actingAs($parent)
        ->get(route('registrations.create', registerParameters($session)))
        ->assertOk()
        ->assertSee('Sam')
        ->assertSee('Alex')
        ->assertDontSee('Peanuts');

    $this->actingAs($parent)
        ->get(route('registrations.create', registerParameters($session, ['child' => $sam->id])))
        ->assertOk()
        ->assertSee('Allergies')
        ->assertSee('Peanuts');
});

test('a parent registers a child, and the registration goes into the cart at the price from the server', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $sam = sevenYearOld($parent);

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => $sam->id])), [
            'allergies' => 'Bee stings',
            'emergency_phone' => '250-555-0100',
            'price_cents' => 1,
        ])
        ->assertRedirect(route('cart.show'));

    $registration = Registration::sole();

    expect($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->child->is($sam))->toBeTrue()
        ->and($registration->session->is($session))->toBeTrue()
        ->and($registration->submission->answer('allergies'))->toBe('Bee stings')
        ->and($registration->submission->user_id)->toBe($parent->id)
        ->and($sam->profileValues()->sole()->value)->toBe('Bee stings')
        ->and($parent->profileValues()->sole()->value)->toBe('250-555-0100');

    $this->actingAs($parent)
        ->get(route('cart.show'))
        ->assertSee('Junior Robotics Club')
        ->assertSee('Sam')
        ->assertSee('$150.00');
});

test('the answers follow the rules of the form', function () {
    $session = registrationSession();
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => sevenYearOld($parent)->id])), ['allergies' => ''])
        ->assertSessionHasErrors('allergies');

    expect(Registration::count())->toBe(0)
        ->and(app(Cart::class)->count())->toBe(0);
});

test('paying for the order confirms the registration', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $sam = sevenYearOld($parent);

    $this->actingAs($parent)->post(route('registrations.store', registerParameters($session, ['child' => $sam->id])), ['allergies' => 'None']);
    $this->actingAs($parent)->post(route('checkout.store'));
    $order = Order::sole();

    expect(Registration::sole()->status)->toBe(RegistrationStatus::Pending)
        ->and($order->total_cents)->toBe(15000);

    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    expect(Registration::sole()->status)->toBe(RegistrationStatus::Confirmed);

    $this->actingAs($parent)->get($order->url())->assertSee('Sam is registered');
});

test('a failed payment leaves the registration pending', function () {
    $session = registrationSession();
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('registrations.store', registerParameters($session, ['child' => sevenYearOld($parent)->id])), ['allergies' => 'None']);
    $this->actingAs($parent)->post(route('checkout.store'));
    $this->post(URL::signedRoute('payments.complete', Order::sole()->payment), ['fake_result' => 'decline']);

    expect(Registration::sole()->status)->toBe(RegistrationStatus::Pending);
});

test('a child must be in the age range on the first day of the session', function (string $birthdate) {
    $session = registrationSession();
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create(['birthdate' => $birthdate]);

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => $child->id])), ['allergies' => 'None'])
        ->assertSessionHasErrors('child');

    expect(Registration::count())->toBe(0);
})->with([
    'five years old' => ['2020-12-02'],
    'ten years old' => ['2016-12-01'],
]);

test('a child who turns six on the first day may register', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create(['birthdate' => '2020-12-01']);

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => $child->id])), ['allergies' => 'None'])
        ->assertRedirect(route('cart.show'));

    expect(Registration::count())->toBe(1);
});

test('a session that is not open for registration is refused', function (array $dates) {
    $session = registrationSession(attributes: $dates);
    $parent = User::factory()->create();

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => sevenYearOld($parent)->id])), ['allergies' => 'None'])
        ->assertSessionHasErrors('child');

    expect(Registration::count())->toBe(0);
})->with([
    'opens later' => [['registration_opens_at' => '2026-11-20 09:00']],
    'closed' => [['registration_closes_at' => '2026-11-10 17:00']],
]);

test('a full session never puts a child in the cart', function () {
    $session = registrationSession(attributes: ['capacity' => 1]);
    Registration::factory()->for($session, 'session')->confirmed()->create();
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('registrations.store', registerParameters($session, ['child' => sevenYearOld($parent)->id])), ['allergies' => 'None']);

    expect(app(Cart::class)->count())->toBe(0)
        ->and(Registration::where('status', 'pending')->count())->toBe(0);
});

test('the same child cannot register twice for one session', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $sam = sevenYearOld($parent);
    Registration::factory()->for($session, 'session')->for($sam)->confirmed()->create();

    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => $sam->id])), ['allergies' => 'None'])
        ->assertSessionHasErrors('child');

    expect(Registration::count())->toBe(1)
        ->and(app(Cart::class)->count())->toBe(0);
});

test('a second try before payment keeps one registration in the cart', function () {
    $session = registrationSession();
    $parent = User::factory()->create();
    $sam = sevenYearOld($parent);

    $this->actingAs($parent)->post(route('registrations.store', registerParameters($session, ['child' => $sam->id])), ['allergies' => 'None']);
    $this->actingAs($parent)->post(route('registrations.store', registerParameters($session, ['child' => $sam->id])), ['allergies' => 'Peanuts']);

    expect(Registration::count())->toBe(1)
        ->and(Registration::sole()->submission->answer('allergies'))->toBe('Peanuts')
        ->and(app(Cart::class)->count())->toBe(1);
});

test('a parent cannot register another family\'s child', function () {
    $session = registrationSession();
    $otherChild = sevenYearOld(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->post(route('registrations.store', registerParameters($session, ['child' => $otherChild->id])), ['allergies' => 'None'])
        ->assertNotFound();

    expect(Registration::count())->toBe(0);
});

test('only register programs take registrations', function (array $attributes) {
    $session = registrationSession(programTakingRegistrations($attributes));
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('registrations.create', registerParameters($session)))->assertNotFound();
    $this->actingAs($parent)
        ->post(route('registrations.store', registerParameters($session, ['child' => sevenYearOld($parent)->id])), ['allergies' => 'None'])
        ->assertNotFound();

    expect(Registration::count())->toBe(0);
})->with([
    'apply mode' => [['signup_mode' => 'apply']],
    'request mode' => [['signup_mode' => 'request']],
    'no sign-up' => [['signup_mode' => 'none']],
    'unpublished program' => [['is_published' => false]],
]);

test('a session of another program is not found', function () {
    $program = programTakingRegistrations();
    $otherSession = registrationSession();

    $this->actingAs(User::factory()->create())
        ->get(route('registrations.create', ['category' => $program->category, 'program' => $program, 'session' => $otherSession]))
        ->assertNotFound();
});

test('the parent sees the registrations of their children on the dashboard', function () {
    $parent = User::factory()->create();
    $session = registrationSession();
    Registration::factory()->for($session, 'session')->for(sevenYearOld($parent))->confirmed()->create();
    Registration::factory()->for($session, 'session')->for(sevenYearOld(User::factory()->create(), 'Robin'))->confirmed()->create();

    $this->actingAs($parent)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My registrations')
        ->assertSee('Sam')
        ->assertSee('Junior Robotics Club')
        ->assertSee('Confirmed')
        ->assertDontSee('Robin');
});
