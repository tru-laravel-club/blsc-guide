<?php

use App\Cart;
use App\Enums\FieldScope;
use App\Enums\RegistrationStatus;
use App\Models\Child;
use App\Models\Field;
use App\Models\Form;
use App\Models\Order;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\SpotOffered;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Capacity and waitlist" feature.
|
| A session has a number of spots. A paid registration takes a spot, and the
| count happens under a lock, so two parents cannot both get the last spot.
| When the session is full, a child goes on the waitlist. When a spot opens,
| the first child on the waitlist gets an offer for 48 hours. A scheduled
| command moves an expired offer on to the next child.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));
});

/**
 * Make a session with this many spots. Its program takes registrations for any age,
 * with a form that asks one optional question. The session starts on December 1, 2026.
 */
function sessionWithSpots(int $capacity): ProgramSession
{
    $form = Form::factory()->create();
    $form->fields()->attach(Field::factory()->create(['key' => 'school', 'scope' => FieldScope::Child]), ['position' => 0, 'required' => false]);
    $form->publish();

    $program = Program::factory()->create(['signup_mode' => 'register', 'form_id' => $form->id, 'age_min' => null, 'age_max' => null]);

    return ProgramSession::factory()->for($program)->create([
        'starts_on' => '2026-12-01',
        'capacity' => $capacity,
        'price_cents' => 15000,
        'member_price_cents' => null,
        'registration_opens_at' => '2026-11-01 09:00',
    ]);
}

/**
 * Send the registration form for this child, as the child's parent.
 */
function registerChild(Child $child, ProgramSession $session): void
{
    test()->actingAs($child->user)->post(route('registrations.store', [
        'category' => $session->program->category,
        'program' => $session->program,
        'session' => $session,
        'child' => $child->id,
    ]), ['school' => 'Westsyde Elementary']);
}

/**
 * Make a registration with this status in the session. Each one is newer than the last.
 */
function registrationIn(ProgramSession $session, RegistrationStatus $status, string $childName = 'Sam'): Registration
{
    return Registration::factory()
        ->for($session, 'session')
        ->for(Child::factory()->create(['name' => $childName]))
        ->create(['status' => $status]);
}

test('the last spot goes to one parent only', function () {
    $session = sessionWithSpots(1);
    $ada = Child::factory()->create(['name' => 'Ada']);
    $ben = Child::factory()->create(['name' => 'Ben']);

    registerChild($ada, $session);
    $this->actingAs($ada->user)->post(route('checkout.store'));
    $adaOrder = Order::latest('id')->first();

    registerChild($ben, $session);
    $this->actingAs($ben->user)->post(route('checkout.store'));
    $benOrder = Order::latest('id')->first();

    $this->actingAs($ada->user)->post(URL::signedRoute('payments.complete', $adaOrder->payment), ['fake_result' => 'approve']);
    $this->actingAs($ben->user)->post(URL::signedRoute('payments.complete', $benOrder->payment), ['fake_result' => 'approve']);

    expect($ada->registrations()->sole()->status)->toBe(RegistrationStatus::Confirmed)
        ->and($ben->registrations()->sole()->status)->not->toBe(RegistrationStatus::Confirmed)
        ->and($session->registrations()->where('status', 'confirmed')->count())->toBe(1);

    $this->actingAs($ben->user)->get($benOrder->url())->assertSee('refund');
});

test('a full session puts the child on the waitlist, with nothing to pay', function () {
    $session = sessionWithSpots(1);
    registrationIn($session, RegistrationStatus::Confirmed);
    $child = Child::factory()->create(['name' => 'Ada']);

    registerChild($child, $session);

    expect($child->registrations()->sole()->status)->toBe(RegistrationStatus::Waitlisted)
        ->and(app(Cart::class)->count())->toBe(0);

    $this->actingAs($child->user)->get(route('dashboard'))->assertSee('Ada')->assertSee('Waitlist');
});

test('the program page offers the waitlist for a full session', function () {
    $session = sessionWithSpots(1);
    registrationIn($session, RegistrationStatus::Confirmed);

    $this->get(route('programs.show', ['category' => $session->program->category, 'program' => $session->program]))
        ->assertSee('Join the waitlist')
        ->assertSee(route('registrations.create', ['category' => $session->program->category, 'program' => $session->program, 'session' => $session]));
});

test('a child on the waitlist cannot join it twice', function () {
    $session = sessionWithSpots(1);
    registrationIn($session, RegistrationStatus::Confirmed);
    $child = registrationIn($session, RegistrationStatus::Waitlisted, 'Ada')->child;

    $this->actingAs($child->user)->post(route('registrations.store', [
        'category' => $session->program->category,
        'program' => $session->program,
        'session' => $session,
        'child' => $child->id,
    ]), ['school' => 'Westsyde Elementary'])->assertSessionHasErrors('child');

    expect($child->registrations()->count())->toBe(1);
});

test('staff see the registrations and the waitlist of a session in order', function () {
    $session = sessionWithSpots(1);
    registrationIn($session, RegistrationStatus::Confirmed, 'Cleo');
    registrationIn($session, RegistrationStatus::Waitlisted, 'Ada');
    registrationIn($session, RegistrationStatus::Waitlisted, 'Ben');

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.edit', $session))
        ->assertOk()
        ->assertSeeInOrder(['Cleo', 'Confirmed', 'Ada', 'Waitlisted', 'Ben', 'Waitlisted']);
});

test('only staff can cancel a registration', function () {
    $registration = registrationIn(sessionWithSpots(1), RegistrationStatus::Confirmed);

    $this->actingAs($registration->child->user)
        ->post(route('admin.registrations.cancel', $registration))
        ->assertForbidden();

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Confirmed);
});

test('a cancellation offers the spot to the first child on the waitlist', function () {
    Notification::fake();
    $session = sessionWithSpots(1);
    $cancelled = registrationIn($session, RegistrationStatus::Confirmed, 'Cleo');
    $first = registrationIn($session, RegistrationStatus::Waitlisted, 'Ada');
    $second = registrationIn($session, RegistrationStatus::Waitlisted, 'Ben');

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.registrations.cancel', $cancelled))
        ->assertRedirect();

    $first->refresh();

    expect($cancelled->fresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($first->status)->toBe(RegistrationStatus::Offered)
        ->and($first->offer_expires_at->toDateTimeString())->toBe('2026-11-17 10:00:00')
        ->and($second->fresh()->status)->toBe(RegistrationStatus::Waitlisted)
        ->and(new SpotOffered($first))->toBeInstanceOf(ShouldQueue::class);

    Notification::assertSentTo($first->child->user, SpotOffered::class);
    Notification::assertNotSentTo($second->child->user, SpotOffered::class);
});

test('an offered spot is held for its child', function () {
    $session = sessionWithSpots(1);
    registrationIn($session, RegistrationStatus::Offered, 'Ada')->forceFill(['offer_expires_at' => now()->addDay()])->save();
    $child = Child::factory()->create(['name' => 'Ben']);

    registerChild($child, $session);

    expect($child->registrations()->sole()->status)->toBe(RegistrationStatus::Waitlisted);
});

test('the parent accepts the offer, pays, and the child is registered', function () {
    $session = sessionWithSpots(1);
    $offer = registrationIn($session, RegistrationStatus::Offered, 'Ada');
    $offer->forceFill(['offer_expires_at' => now()->addDay()])->save();
    $parent = $offer->child->user;

    $this->actingAs($parent)->get(route('dashboard'))->assertSee(route('registrations.accept', $offer));

    $this->actingAs($parent)->post(route('registrations.accept', $offer))->assertRedirect(route('cart.show'));
    $this->actingAs($parent)->post(route('checkout.store'));
    $this->post(URL::signedRoute('payments.complete', Order::sole()->payment), ['fake_result' => 'approve']);

    expect($offer->fresh()->status)->toBe(RegistrationStatus::Confirmed);
});

test('only the parent can accept an offer, and only while it is open', function () {
    $session = sessionWithSpots(1);
    $offer = registrationIn($session, RegistrationStatus::Offered, 'Ada');
    $offer->forceFill(['offer_expires_at' => now()->addDay()])->save();
    $waiting = registrationIn($session, RegistrationStatus::Waitlisted, 'Ben');

    $this->actingAs(User::factory()->create())->post(route('registrations.accept', $offer))->assertNotFound();
    $this->actingAs($waiting->child->user)->post(route('registrations.accept', $waiting))->assertSessionHasErrors('offer');

    $this->travel(2)->days();
    $this->actingAs($offer->child->user)->post(route('registrations.accept', $offer))->assertSessionHasErrors('offer');

    expect(app(Cart::class)->count())->toBe(0);
});

test('an expired offer moves on to the next child', function () {
    Notification::fake();
    $session = sessionWithSpots(1);
    $offer = registrationIn($session, RegistrationStatus::Offered, 'Ada');
    $offer->forceFill(['offer_expires_at' => now()->addHours(48)])->save();
    $next = registrationIn($session, RegistrationStatus::Waitlisted, 'Ben');

    $this->artisan('registrations:expire-offers')->assertSuccessful();

    expect($offer->fresh()->status)->toBe(RegistrationStatus::Offered);

    $this->travel(49)->hours();
    $this->artisan('registrations:expire-offers')->assertSuccessful();

    expect($offer->fresh()->status)->toBe(RegistrationStatus::Cancelled)
        ->and($next->fresh()->status)->toBe(RegistrationStatus::Offered);

    Notification::assertSentTo($next->child->user, SpotOffered::class);
});

test('the scheduler runs the expiry command', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

    expect($commands)->toContain('registrations:expire-offers');
});
