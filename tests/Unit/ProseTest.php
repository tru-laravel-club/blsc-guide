<?php

use TruLaravelClub\BlscGuide\Prose;

test('prose escapes the text and marks up code and bold', function () {
    expect(Prose::toHtml('Run `a <b>` and **stop**. <script>'))
        ->toBe('<p>Run <code>a &lt;b&gt;</code> and <strong>stop</strong>. &lt;script&gt;</p>');
});

test('bold is not applied inside code', function () {
    expect(Prose::toHtml('`**x**` and **y**'))->toBe('<p><code>**x**</code> and <strong>y</strong></p>');
});

test('blank lines make paragraphs and single line breaks join lines', function () {
    expect(Prose::toHtml("One\ntwo\n\nThree", 'inline'))->toBe('<p class="inline">One two</p><p class="inline">Three</p>');
});

test('lines that start with a dash or a number become lists', function () {
    expect(Prose::toHtml("Check:\n- the **name**\n- the `slug`\n\n1. First\n2. Second"))
        ->toBe('<p>Check:</p><ul class="list"><li>the <strong>name</strong></li><li>the <code>slug</code></li></ul><ol class="list"><li>First</li><li>Second</li></ol>');
});
