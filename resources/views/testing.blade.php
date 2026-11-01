<x-blsc-guide::layout title="How testing works">
    <h1>How testing works</h1>
    <p class="lead">Every feature comes with an acceptance test. The test tells you when the feature is done. This page explains the parts that every test in this project uses.</p>

    <h2>A test has three parts</h2>
    <div class="prose">
        <p>Each test makes the data it needs, does one thing, and checks the result. Read every test in this order: arrange, act, assert.</p>
    </div>
    <pre>test('visitors see only published programs', function () {
    // Arrange: make the data that this test needs
    Program::factory()->create(['name' => 'Junior Robotics Club']);
    Program::factory()->create(['name' => 'Secret Club', 'is_published' => false]);

    // Act and assert: visit the page and check what it shows
    $this->get(route('programs.index'))
        ->assertOk()
        ->assertSee('Junior Robotics Club')
        ->assertDontSee('Secret Club');
});</pre>

    <h2>Tests use their own database</h2>
    <div class="prose">
        <p><code>phpunit.xml</code> sets <code>DB_DATABASE</code> to <code>:memory:</code>. The tests run on an SQLite database in memory, not on your development database. Your sample data is safe.</p>
        <p><code>tests/Pest.php</code> adds <code>RefreshDatabase</code> to every feature test. It runs the migrations once. Then it starts each test in a database transaction and rolls the transaction back at the end. So every test starts with empty tables, and one test cannot change the result of another.</p>
        <p><code>phpunit.xml</code> also sends mail to an array and runs queued jobs at once. A test never sends a real email.</p>
    </div>

    <h2>Factories make test data</h2>
    <div class="prose">
        <p>A factory in <code>database/factories</code> knows how to make one valid model. Its <code>definition()</code> method returns default values. Most values are fake, from Faker.</p>
        <p>In a test, set only the values that the test is about. The factory fills in the rest. Then a reader sees at once what matters.</p>
    </div>
    <pre>Program::factory()->create();                         // one program with fake values
Program::factory()->create(['age_min' => 10]);        // only the age matters here
Program::factory()->count(3)->create();               // three programs
ProgramSession::factory()->for($program)->create();   // a session of this program
Program::factory()->make();                           // a model that is not saved</pre>
    <div class="prose">
        <p>A <strong>state</strong> is a named set of values, such as <code>-&gt;unpublished()</code> or <code>-&gt;staff()</code>. Add a state when many tests need the same values. Then the test reads like a sentence.</p>
        <p>When you add a model, add its factory in the same feature. The next contributor uses it in their tests.</p>
    </div>

    <h2>Seeders fill your development database</h2>
    <div class="prose">
        <p>A seeder in <code>database/seeders</code> adds sample data, so you can click through the site in the browser. <code>DatabaseSeeder</code> calls the other seeders. Seeders often use factories.</p>
        <p>Tests do not use seeders. Each test makes its own data with factories, so the test shows everything that it needs. A seeder is for people. A factory is for tests and for seeders.</p>
    </div>
    <pre>php artisan migrate:fresh --seed   # drop every table, migrate, then run DatabaseSeeder
php artisan db:seed --class=ProgramSeeder   # run one seeder</pre>
    <div class="prose">
        <p>Only <code>migrate:fresh</code> deletes data. Run it on your development database only, never on a real server.</p>
    </div>

    <h2>Fakes stop real side effects</h2>
    <div class="prose">
        <p>Some code talks to the world outside: it sends mail, stores files or calls another server. In a test, replace that part with a fake. The fake records what your code did, and the test checks the record.</p>
    </div>
    <div class="card spaced">
        <table class="table">
            <tr><td><code>Mail::fake()</code></td><td>Records mail. Check it with <code>Mail::assertQueued(...)</code> or <code>Mail::assertSent(...)</code>.</td></tr>
            <tr><td><code>Notification::fake()</code></td><td>Records notifications. Check it with <code>Notification::assertSentTo(...)</code>.</td></tr>
            <tr><td><code>Storage::fake('public')</code></td><td>Uses a temporary disk for uploads. Check it with <code>Storage::disk('public')-&gt;assertExists(...)</code>.</td></tr>
            <tr><td><code>Http::fake([...])</code></td><td>Returns the responses that you give, for example from a payment provider.</td></tr>
            <tr><td><code>$this-&gt;travelTo(...)</code></td><td>Sets the date and time, for rules such as "registration closes on Friday".</td></tr>
        </table>
    </div>

    <h2>Run the tests</h2>
    <pre>php artisan test                                       # every test
php artisan test tests/Feature/ProgramCatalogTest.php  # one file
php artisan test --filter="only published"             # tests whose name matches
composer ci:check                                      # code style, types and tests, like the CI on GitHub</pre>
    <div class="prose">
        <p><code>php artisan guide:start</code> copies the acceptance test of a feature into <code>tests/Feature</code>. At first every rule fails. Each step makes more rules pass. When all of them pass, the feature works. Run <code>composer ci:check</code> before you push.</p>
        <p>Do not change an acceptance test to make it pass. If you think that a rule is wrong, ask in your pull request.</p>
    </div>

    <h2>Learn more</h2>
    <ul class="links">
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/testing">Laravel docs: Testing</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/database-testing">Laravel docs: Database testing and factories</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/seeding">Laravel docs: Seeding</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/mocking">Laravel docs: Mocking and fakes</a></li>
        <li><span class="link-type">Read</span> <a href="https://pestphp.com/docs/writing-tests">Pest docs: Writing tests</a></li>
    </ul>
</x-blsc-guide::layout>
