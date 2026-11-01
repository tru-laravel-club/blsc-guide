<x-blsc-guide::layout title="Controllers and services">
    <h1>Controllers and services</h1>
    <p class="lead">This project has one rule for where code goes: controllers do HTTP, services do the work. Every feature that saves data follows it. This page explains the rule once, so each feature can point here.</p>

    <h2>The rule</h2>
    <div class="prose">
        <ul class="list">
            <li><strong>A controller does only HTTP work.</strong> It gets valid input from a form request, checks the user with a policy or gate, calls a service, and returns a view or a redirect.</li>
            <li><strong>A service does the work.</strong> It is a plain class in <code>app/Services</code>. Every write goes through a service, even a simple create, update or delete. A controller never calls <code>create()</code>, <code>update()</code>, <code>delete()</code> or <code>save()</code> on a model.</li>
            <li><strong>A model holds data.</strong> It keeps relationships, casts, scopes and small read-only helpers, such as <code>isPublished()</code>. A method that changes data belongs in a service.</li>
        </ul>
    </div>

    <h2>Why not put it all in the controller?</h2>
    <div class="prose">
        <p>A controller is one front door to your code. The same work often has more doors: a staff page, a Livewire component, an artisan command, a queued job or a test. If the work is in the controller, every other door must copy it. Copies drift apart, and then one door has a bug that the others do not.</p>
        <p>A service has no request, no session and no redirect. It takes plain data and models, and it returns a result. So every door can call it, and you can test it alone. When the work grows, for example "a deleted program also cancels its open orders", you change one method, and every door gets the change.</p>
    </div>

    <h2>What it looks like</h2>
    <pre>// app/Services/ProgramService.php: the work, in one place
class ProgramService
{
    public function create(array $data): Program
    {
        return Program::create($data);
    }
}

// app/Http/Controllers/Admin/ProgramController.php: HTTP only
public function store(ProgramRequest $request, ProgramService $programs): RedirectResponse
{
    $program = $programs->create($request->validated());

    return to_route('admin.programs.edit', $program)->with('status', 'Program created.');
}</pre>
    <div class="prose">
        <p>The controller method asks for <code>ProgramService $programs</code> as a parameter. Laravel sees the type, makes the service and passes it in. This is <strong>dependency injection</strong>. You never write <code>new ProgramService</code>.</p>
        <p>At first a service method can be one line. That is fine. The method gives the work a name and a home, so the next rule has a place to go.</p>
    </div>

    <h2>When a rule says no</h2>
    <div class="prose">
        <p>Some work must refuse a request, for example "a category that still has programs cannot be deleted". The service checks the rule and throws a named exception. The exception carries the message. The controller catches it and shows the message as a form error. The service never builds a response.</p>
    </div>
    <pre>// app/Services/CategoryService.php
public function delete(Category $category): void
{
    if ($category->programs()->exists()) {
        throw new CategoryHasPrograms;
    }

    $category->delete();
}

// The controller
try {
    $categories->delete($category);
} catch (CategoryHasPrograms $exception) {
    return back()->withErrors(['category' => $exception->getMessage()]);
}

// app/Exceptions/CategoryHasPrograms.php: the message lives with the rule
class CategoryHasPrograms extends Exception
{
    public function __construct()
    {
        parent::__construct('Move its programs to another category first.');
    }
}</pre>

    <h2>What stays outside services</h2>
    <div class="prose">
        <ul class="list">
            <li><strong>Validation</strong> stays in form requests. A Livewire component calls the same <code>rules()</code>.</li>
            <li><strong>Permission checks</strong> stay in policies and gates. The controller or component calls them before it calls the service.</li>
            <li><strong>Reads</strong> for one page, such as a list with a filter, can stay in the controller. Use a model scope when more than one place needs the same query.</li>
            <li><strong>Model events</strong> are only for clean-up that must happen however a row is deleted, such as deleting a stored file. Say why in a comment.</li>
            <li><strong>The starter kit code</strong>, such as the login and settings pages, stays as Laravel ships it.</li>
        </ul>
    </div>

    <h2>Name your service</h2>
    <div class="prose">
        <p>Name a service after the thing that it manages, such as <code>ProgramService</code>, <code>OrderService</code> or <code>CategoryService</code>. Name its methods after the work, such as <code>create()</code>, <code>publish()</code> or <code>cancel()</code>. Later features call these names, so the feature plans list them. Use the names in the plan.</p>
        <p>A Livewire component calls the same services. <a href="{{ route('blsc-guide.reactive') }}">The reactive way</a> shows one service with two front doors.</p>
    </div>

    <h2>Learn more</h2>
    <ul class="links">
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/container#automatic-injection">Laravel docs: Automatic injection</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/controllers#dependency-injection-and-controllers">Laravel docs: Dependency injection in controllers</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/validation#form-request-validation">Laravel docs: Form requests</a></li>
        <li><span class="link-type">Read</span> <a href="https://laravel.com/docs/authorization">Laravel docs: Authorization</a></li>
    </ul>
</x-blsc-guide::layout>
