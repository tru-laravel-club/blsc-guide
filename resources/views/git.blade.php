<x-blsc-guide::layout title="Git habits">
    <h1>Git habits</h1>
    <p class="lead">Git keeps the history of the code. Good habits keep your work safe, make reviews easy, and stop conflicts with other contributors. Read this page before your first feature. Come back to it when Git surprises you.</p>

    <h2>Your two remotes</h2>
    <div class="prose">
        <p>A remote is a copy of the repository on GitHub. You work with two remotes:</p>
    </div>
    <ul class="list">
        <li><code>origin</code> is your fork. You push your branches to it.</li>
        <li><code>upstream</code> is <code>tru-laravel-club/blsc-website</code>. You get the latest <code>main</code> from it. You never push to it.</li>
    </ul>
    <div class="box try">
        <span class="box-label">Try it</span>
        <p>Run <code>git remote -v</code>. You see both remotes. If <code>upstream</code> is missing, add it:</p>
        <pre>git remote add upstream https://github.com/tru-laravel-club/blsc-website.git</pre>
    </div>

    <h2>Start every feature from the latest main</h2>
    <div class="prose">
        <p>Other contributors merge work every week. If you start from an old <code>main</code>, you build on old code. Your pull request then has conflicts, and the tests can fail for reasons that are not yours.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git switch main
git pull upstream main
git push origin main</pre>
    </div>
    <p class="meta">The last command updates <code>main</code> on your fork too. Then your fork does not fall behind.</p>

    <h2>One branch for one feature</h2>
    <div class="prose">
        <p>Do all the work for a feature on its own branch, such as <code>feature/program-categories</code>. Never commit to <code>main</code>.</p>
        <p>A branch keeps your work apart. You can stop, start a different feature, and come back later. One pull request then holds one feature, so the review is short.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git switch -c feature/program-categories</pre>
    </div>
    <p class="meta">Run <code>git branch</code> to see which branch you are on. The current branch has a <code>*</code>.</p>

    <h2>Look before you commit</h2>
    <div class="prose">
        <p>Before each commit, check what you are about to save. This finds files that you did not mean to change, debug code that you forgot, and secrets.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git status
git diff</pre>
    </div>
    <div class="prose">
        <p><code>git status</code> lists the changed files. <code>git diff</code> shows each changed line. Press <code>q</code> to close the diff.</p>
    </div>

    <h2>Make small commits with clear messages</h2>
    <div class="prose">
        <p>Commit after each step or two. A small commit is easy to read, easy to review, and easy to undo.</p>
        <p>Write the message as a command, in under 50 characters. Say what the commit does, not what you did.</p>
    </div>
    <div class="card">
        <p><strong>Good</strong></p>
        <pre>Add the Category model
Show the programs of a category on its page
Fix the date order of sessions</pre>
        <p class="spaced"><strong>Not good</strong></p>
        <pre>stuff
fixed it
WIP final final 2</pre>
    </div>

    <h2>Push often</h2>
    <div class="prose">
        <p>Push your branch to your fork at least once a week, and at the end of each session. Your work is then safe if your computer breaks. Maintainers can also see your progress and help you.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git push -u origin feature/program-categories</pre>
    </div>
    <p class="meta"><code>-u</code> connects the branch to your fork. After the first push, <code>git push</code> is enough.</p>

    <h2>Keep your branch up to date</h2>
    <div class="prose">
        <p>Before you open a pull request, bring the latest <code>main</code> into your branch. Then run the checks again.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git fetch upstream
git merge upstream/main
composer ci:check</pre>
    </div>

    <h2>Resolve a merge conflict</h2>
    <div class="prose">
        <p>Conflicts are a normal part of open source work. Many people change the same project at the same time. When you and another contributor change the same lines, Git cannot choose, so it asks you. Nothing is lost.</p>
        <p>In this project, most conflicts happen in a few shared files: <code>routes/web.php</code>, the menus in <code>resources/views/layouts</code>, <code>app/Models/User.php</code> and <code>database/seeders/DatabaseSeeder.php</code>. Every feature adds something to them.</p>
    </div>

    <h3>Read a conflict</h3>
    <div class="prose">
        <p>Run <code>git status</code>. It lists the files with conflicts under "Unmerged paths". Open one. Git marks each conflict like this:</p>
    </div>
    <pre>&lt;&lt;&lt;&lt;&lt;&lt;&lt; HEAD
use App\Http\Controllers\CartController;
=======
use App\Http\Controllers\ApplicationController;
&gt;&gt;&gt;&gt;&gt;&gt;&gt; upstream/main</pre>
    <div class="prose spaced">
        <p>The lines above <code>=======</code> are yours (<code>HEAD</code>). The lines below are from <code>main</code>. Your job is to write the result that is correct for both features, and to delete the three marker lines.</p>
    </div>

    <h3>The usual cases</h3>
    <div class="card">
        <table class="table">
            <tr><td><strong>Both sides added a line</strong>, such as a <code>use</code> line or a route.</td><td>Keep both lines. Run <code>vendor/bin/pint</code> to sort the imports.</td></tr>
            <tr><td><strong>Both sides added an item to a list</strong>, such as a menu item or a seeder.</td><td>Keep both items. Check that every HTML tag that you open is also closed.</td></tr>
            <tr><td><strong>Both sides changed the same line</strong>, such as <code>#[Fillable([...])]</code>.</td><td>Write one line that has both changes.</td></tr>
            <tr><td><strong>One side moved code, and the other side changed it.</strong></td><td>Read both features. Write the code by hand so that it does both jobs. Then run the tests of both features.</td></tr>
            <tr><td><strong><code>composer.lock</code></strong></td><td>Do not edit it by hand. Take the version from main with <code>git checkout upstream/main -- composer.lock</code>. Then run <code>composer require</code> again for the package that you added.</td></tr>
        </table>
    </div>

    <h3>Finish the merge</h3>
    <ol class="list spaced">
        <li>Search the project for <code>&lt;&lt;&lt;&lt;&lt;&lt;&lt;</code>. No marker may stay behind.</li>
        <li>Run <code>git add</code> for each file that you fixed, then <code>git commit</code>. Git writes the merge message for you.</li>
        <li>Run <code>php artisan migrate</code>, because main can bring new migrations.</li>
        <li>Run <code>composer ci:check</code>. The tests of the other features must pass too. They prove that you kept their code.</li>
    </ol>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git status
git add routes/web.php
git commit
php artisan migrate
composer ci:check</pre>
    </div>
    <p class="meta spaced">If you are not sure, run <code>git merge --abort</code>. Your branch goes back to how it was before the merge. Then ask for help on the issue. A maintainer will resolve the conflict with you.</p>

    <h2>Never commit secrets</h2>
    <div class="prose">
        <p>Git keeps everything forever, even after you delete a file. A password or API key in a commit stays in the history, and anyone with the repository can read it.</p>
        <p>Keep secrets in <code>.env</code>. Git ignores <code>.env</code>, so it never goes to GitHub. Put new settings in <code>.env.example</code> with empty values, so other contributors know that the setting exists.</p>
        <p>If you commit a secret by accident, tell a maintainer at once. The secret must be changed.</p>
    </div>

    <h2>Fix a mistake</h2>
    <table class="card table">
        <tbody>
            <tr><td>Undo your changes to one file</td><td><code>git restore path/to/file</code></td></tr>
            <tr><td>Change the last commit, before you push</td><td><code>git commit --amend</code></td></tr>
            <tr><td>Undo a commit that you already pushed</td><td><code>git revert &lt;commit&gt;</code></td></tr>
            <tr><td>See the recent commits</td><td><code>git log --oneline</code></td></tr>
        </tbody>
    </table>
    <p class="meta spaced">Do not use <code>git push --force</code> on a branch that has an open pull request, unless a maintainer asks you to.</p>

    <h2>After your pull request merges</h2>
    <div class="prose">
        <p>Get the merged work, and delete the branch. The maintainer squashed your commits into one commit on <code>main</code>. So Git does not see your branch as merged, and you must use <code>-D</code>.</p>
    </div>
    <div class="box git">
        <span class="box-label">Git</span>
        <pre>git switch main
git pull upstream main
git push origin main
git branch -D feature/program-categories</pre>
    </div>

    <h2>Learn more</h2>
    <ul class="links">
        <li><span class="link-type">Read</span><a href="https://git-scm.com/book/en/v2" target="_blank" rel="noopener">Pro Git, the free Git book</a></li>
        <li><span class="link-type">Read</span><a href="https://docs.github.com/en/get-started/exploring-projects-on-github/contributing-to-a-project" target="_blank" rel="noopener">GitHub docs: Contributing to a project</a></li>
        <li><span class="link-type">Read</span><a href="https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/addressing-merge-conflicts/resolving-a-merge-conflict-using-the-command-line" target="_blank" rel="noopener">GitHub docs: Resolving a merge conflict</a></li>
    </ul>
</x-blsc-guide::layout>
