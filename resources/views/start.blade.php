@php($sections = $map->bySection())

<x-blsc-guide::layout title="Start here">
    <h1>Start here</h1>
    <p class="lead">Before you write code, learn why this project exists, what we build, and how we build it. Read this page once. It takes about ten minutes.</p>

    <h2>The science centre</h2>
    <div class="prose">
        <p>The BIG Little Science Centre is a hands-on science centre in Kamloops, BC. Families visit to try experiments. Children join after-school clubs, holiday camps and birthday parties. Grade 8 and 9 students take QUARKS, a program that the centre runs with Thompson Rivers University. Adults come to BIG Night Out evenings.</p>
        <p>A small team of staff runs all of this. They need a website where visitors find what is on, and where parents sign up and pay. They also need simple admin pages, so that they can change programs, dates and prices without a developer.</p>
    </div>

    <h2>Why the club builds it</h2>
    <div class="prose">
        <p>The TRU Laravel Club builds the new website as an open source project. The centre gets a website that fits how it works. You get real experience: a real client, real users, code review and a project that you can show to employers.</p>
        <p>You do not need to know Laravel yet. This guide teaches it one feature at a time. Each feature explains the ideas first, asks you questions, and gives hints before it gives answers.</p>
    </div>

    <h2>What you learn</h2>
    <div class="prose">
        <p>The goal is not only Laravel. The goal is modern backend development: the ideas that every web application needs, in any language. Laravel is our tool because it gives each idea a clean, well-documented form. When you understand an idea here, you will recognise it in Django, Rails, Spring, Express or .NET.</p>
        <p>You learn each idea in a feature that needs it:</p>
    </div>
    <div class="card spaced">
        <table class="table">
            <tr><td><strong>Package managers</strong></td><td>Composer and npm, version constraints, lock files and autoloading. In Safe HTML, you choose a real library, add it, and read what Composer changed.</td></tr>
            <tr><td><strong>Databases</strong></td><td>Schema design, migrations, foreign keys and indexes. Moving existing data in Program categories. Transactions and row locks in Capacity and waitlist.</td></tr>
            <tr><td><strong>ORMs and relationships</strong></td><td>Models, one-to-many and polymorphic relationships, scopes, eager loading and the N+1 problem.</td></tr>
            <tr><td><strong>HTTP and APIs</strong></td><td>Routes, controllers, middleware, validation, resource conventions, signed URLs, and calls to an outside API in Payments.</td></tr>
            <tr><td><strong>Security</strong></td><td>Authentication, authorization with gates and policies, CSRF, cross-site scripting and HTML sanitizing, and secrets in the environment.</td></tr>
            <tr><td><strong>File storage</strong></td><td>Storage disks, uploads, and cloud storage that survives a deployment, in Cover images and File downloads.</td></tr>
            <tr><td><strong>Sessions, cache and config</strong></td><td>The cart in the session, config files, environment variables and config caching.</td></tr>
            <tr><td><strong>Background work</strong></td><td>Queues, email notifications, events and listeners, and scheduled tasks.</td></tr>
            <tr><td><strong>Design</strong></td><td>Interfaces that let you swap a provider, data instead of code, and building the general case.</td></tr>
            <tr><td><strong>Quality</strong></td><td>Automated tests and fakes, static analysis with PHPStan, code style with Pint, and continuous integration.</td></tr>
            <tr><td><strong>Teamwork</strong></td><td>Git, branches, merge conflicts, pull requests and code review in an open source project.</td></tr>
        </table>
    </div>

    <h2>What we build</h2>
    <div class="prose">
        <p>We build the website in six phases. Each phase is a set of features. Some features need others first, so the guide shows which features you can start now.</p>
    </div>
    <ul class="list">
        <li><strong>Foundation.</strong> The base that every other feature uses: the layout, accounts, staff access, programs and child profiles.</li>
        <li><strong>Catalog.</strong> What is on: program categories, sessions, and how a visitor signs up for each program.</li>
        <li><strong>Content.</strong> Pages and posts that staff write in a visual editor, made safe on the server.</li>
        <li><strong>Forms.</strong> A form builder, so staff can ask new questions without a developer. Parents type their details once.</li>
        <li><strong>Sign-ups and payments.</strong> Registrations, applications, requests, tickets, memberships, a cart and online payment.</li>
        <li><strong>Staff tools.</strong> Class lists, check-in and member lookup.</li>
    </ul>
    <p class="meta spaced">The guide lists {{ count($map->all()) }} features in {{ count($sections) }} parts. <a href="{{ route('blsc-guide.index') }}">See all features</a>.</p>

    <h2>How we build it</h2>
    <ol class="list">
        <li>You pick a feature with the status <strong>Ready</strong> and ask for its issue on GitHub.</li>
        <li>You fork the repository, make a branch, and copy in the acceptance test of the feature. The test says what "done" means.</li>
        <li>You follow the guide. It checks your code and ticks off each step.</li>
        <li>You open a pull request. Another contributor and a maintainer review it, and a maintainer merges it.</li>
        <li>The features that needed yours are now unlocked for the next contributor.</li>
    </ol>

    <h2>Build for change</h2>
    <div class="prose">
        <p>The centre will change. It will add a new kind of event, a new membership, a new price or a new payment provider. Good code lets staff make most of these changes from an admin page. The rest need a small change in one place. Bad code needs a developer to rewrite a feature.</p>
        <p>So before you build, ask: "What else is like this? What will the centre want next year?" Then build the general idea, not only today's case. For example, do not build a page only for Saturday events. Make each event a session of a program, and let a category page show the sessions. Then Sunday events need no new code. The same idea applies to forms: staff will change their questions every season, so the questions are data, not code.</p>
        <p>Each feature in the guide has questions about change. Take them seriously. This skill matters more than any Laravel method.</p>
    </div>

    <h2>Our rules</h2>
    <ul class="list">
        <li>Many users are children. Collect only the personal data that the centre needs.</li>
        <li>Store money in cents, such as <code>2500</code> for $25.00.</li>
        <li>Never commit passwords or API keys.</li>
        <li>Keep pull requests small: one feature in one pull request.</li>
        <li>Be kind in reviews. Comment on the code, not on the person.</li>
    </ul>

    <h2>Your first hour</h2>
    <ol class="list">
        <li>Read <a href="{{ route('blsc-guide.git') }}">Git habits</a>.</li>
        <li>Skim <a href="{{ route('blsc-guide.reactive') }}">The reactive way</a>. Most features use controllers. This page explains when a feature uses Livewire instead, and why.</li>
        <li>Read the features that are already done, such as <a href="{{ route('blsc-guide.show', 'layout') }}">Site layout and menus</a> and <a href="{{ route('blsc-guide.show', 'programs') }}">Programs and sessions</a>. They show the patterns that you will copy.</li>
        <li>Pick a Level 1 feature that is <strong>Ready</strong>, and ask for its issue.</li>
    </ol>
</x-blsc-guide::layout>
