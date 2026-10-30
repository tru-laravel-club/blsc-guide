<x-blsc-guide::layout title="The reactive way">
    <h1>The reactive way</h1>
    <p class="lead">Most features in this guide use routes, controllers, form requests and views. This page explains a second way to build a page, when to use it, and how it maps to what you already know.</p>

    <h2>Two ways to build a page</h2>
    <div class="prose">
        <p><strong>The traditional way.</strong> The browser asks for a URL. A route sends the request to a controller. The controller checks the input, talks to the database, and returns a complete HTML page. Every link and every form loads a new page. This is how the web works at its base, and every backend framework works this way.</p>
        <p><strong>The reactive way.</strong> The page stays open. When the user types or clicks, the browser sends a small request, and the server sends back only the part of the page that changed. The state of the page lives on the server. The browser only shows it.</p>
        <p>Many modern backends now work like this: Hotwire in Ruby on Rails, LiveView in Phoenix, htmx with any backend, and Livewire in Laravel. It is different from a single-page app in React or Vue. There, the state lives in JavaScript in the browser, and the server only sends JSON. In the reactive way, you keep your logic on the server, in the language you already use.</p>
        <p>We use Livewire because it fits Laravel well. The idea is what matters. When you understand it here, you will recognise it in Hotwire or LiveView.</p>
    </div>

    <h2>A Livewire component is a controller that stays on the page</h2>
    <div class="prose">
        <p>A Livewire component has the same jobs as a controller. It only does them in small steps, while the page stays open.</p>
    </div>
    <div class="card spaced">
        <table class="table">
            <tr><td><strong>Controller</strong></td><td><strong>Livewire component</strong></td></tr>
            <tr><td>A route sends a request to a method.</td><td>A tag such as <code>&lt;livewire:admin.form-editor /&gt;</code> puts the component on a page. A button calls a public method, such as <code>wire:click="publish"</code>.</td></tr>
            <tr><td>The input comes in the request.</td><td>The input lives in public properties. <code>wire:model</code> connects a property to a form field.</td></tr>
            <tr><td>A form request validates the input.</td><td><code>$this->validate()</code> with the same Laravel rules.</td></tr>
            <tr><td><code>authorize()</code> or a policy checks the user.</td><td><code>$this->authorize()</code> or a gate check in each method.</td></tr>
            <tr><td>The method returns a view.</td><td><code>render()</code> returns the view. Livewire sends back only the HTML that changed.</td></tr>
            <tr><td>A redirect sends the user to a new page.</td><td><code>$this->redirect()</code> does the same.</td></tr>
        </table>
    </div>

    <h2>One backend, two front doors</h2>
    <div class="prose">
        <p>A component does not call a controller. Both are front doors: they receive the user's input and send back a response. This project has one rule for both: <strong>controllers and components do only HTTP work. Services do the business logic.</strong></p>
        <ul class="list">
            <li><strong>The controller or component</strong> reads the input, checks the user, calls a service, and returns a page, a redirect or new HTML. Nothing more.</li>
            <li><strong>The service</strong> is a plain class in <code>app/Services</code>. It holds the business rules, such as "a ticket can be used only once". It does not know about requests, sessions or Livewire.</li>
            <li><strong>The form request</strong> keeps the validation rules. The controller uses it as usual. The component calls the same <code>rules()</code>, so the rules live in one place.</li>
            <li><strong>The policy or gate</strong> checks the user. Both doors call it.</li>
        </ul>
    </div>
    <pre>// app/Services/TicketCheckIn.php: the business rules, in one place
class TicketCheckIn
{
    public function checkIn(string $code): Ticket { ... }
}

// The controller: HTTP only
public function store(CheckInRequest $request, TicketCheckIn $tickets)
{
    $ticket = $tickets->checkIn($request->validated('code'));

    return back()->with('status', "Checked in {$ticket->code}.");
}

// The Livewire component: the same service, without a page load
public function check(TicketCheckIn $tickets)
{
    $this->authorize('access-admin');
    $this->validate((new CheckInRequest)->rules());

    $this->ticket = $tickets->checkIn($this->code);
}</pre>
    <div class="prose spaced">
        <p>Tests get simpler too. You test the service once, and each front door only needs a short test. When the centre wants a phone app later, an API controller calls the same service.</p>
    </div>

    <h2>What happens underneath</h2>
    <div class="prose">
        <p>A call to a Livewire method is still an HTTP request. When you click a button, the browser sends a POST request with the state of the component and the name of the method. Laravel runs the middleware, the component runs the method, and the response holds the new HTML. Livewire then changes only the parts of the page that are different.</p>
        <p>So everything that you learned about requests still applies: middleware, CSRF protection, validation and authorization.</p>
    </div>
    <div class="box try">
        <span class="box-label">Try it</span>
        <p>Log in as staff and open a form in the form editor. Open the developer tools of your browser, and choose the Network tab. Click a button in the editor. You see one POST request to a URL that starts with <code>/livewire-</code>. Open it, and look at the request and the response.</p>
    </div>

    <h2>The security rule</h2>
    <div class="prose">
        <p>Public properties travel to the browser and back. A user can change them with the developer tools. So treat every public property like form input:</p>
    </div>
    <ul class="list spaced">
        <li>Check the user in every method that a button calls, not only when the page loads.</li>
        <li>Validate every value before you save it.</li>
        <li>Mark an ID that must not change with <code>#[Locked]</code>.</li>
        <li>Never keep a secret in a public property.</li>
    </ul>

    <h2>When to choose which</h2>
    <div class="card">
        <table class="table">
            <tr><td><strong>Use a controller</strong></td><td>A page that the user opens, reads or submits. A create or edit form. Anything that works well with a new page load. The result has its own URL, so the user can share it, and an HTTP test can check it.</td></tr>
            <tr><td><strong>Use a Livewire component</strong></td><td>The user must see a change from the server without leaving the page: results while they type, reordering a list, a price that changes when they choose an option, or a status that changes in place.</td></tr>
            <tr><td><strong>Use <code>wire:navigate</code></strong></td><td>Normal links between controller pages. The browser swaps the page without a full reload, so the site feels fast. The controllers do not change.</td></tr>
        </table>
    </div>
    <div class="prose spaced">
        <p>Start with a controller. Add a component only when the user needs it. A component is more code to test and to secure, so it must earn its place.</p>
    </div>

    <h2>In this project</h2>
    <ul class="list">
        <li><a href="{{ route('blsc-guide.show', 'form-editor') }}">Form editor for staff</a> is the worked example. Staff add, reorder and remove fields without a page load.</li>
        <li>The account settings pages came with the Laravel starter kit. They are Livewire components too. Read them in <code>resources/views/pages/settings</code>.</li>
        <li>The staff sidebar uses <code>wire:navigate</code>, so the staff pages change without a full reload.</li>
    </ul>

    <h2>Learn more</h2>
    <ul class="links">
        <li><span class="link-type">Read</span> <a href="https://livewire.laravel.com/docs/4.x/quickstart">Livewire docs: Quickstart</a></li>
        <li><span class="link-type">Read</span> <a href="https://livewire.laravel.com/docs/4.x/actions">Livewire docs: Calling methods from the page</a></li>
        <li><span class="link-type">Read</span> <a href="https://livewire.laravel.com/docs/4.x/actions#dependency-injection">Livewire docs: Dependency injection in methods</a></li>
        <li><span class="link-type">Read</span> <a href="https://livewire.laravel.com/docs/4.x/security">Livewire docs: Security</a></li>
        <li><span class="link-type">Read</span> <a href="https://livewire.laravel.com/docs/4.x/navigate">Livewire docs: Navigate</a></li>
        <li><span class="link-type">Read</span> <a href="https://hotwired.dev">Hotwire, the same idea in Ruby on Rails</a></li>
        <li><span class="link-type">Read</span> <a href="https://htmx.org">htmx, the same idea for any backend</a></li>
        <li><span class="link-type">Read</span> <a href="https://hexdocs.pm/phoenix_live_view/welcome.html">Phoenix LiveView, the same idea in Elixir</a></li>
        <li><span class="link-type">Read</span> <a href="https://inertiajs.com">Inertia, a different idea: controllers stay, and the pages are React, Vue or Svelte components</a></li>
    </ul>
</x-blsc-guide::layout>
