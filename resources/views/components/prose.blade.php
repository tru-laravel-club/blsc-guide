@props(['text', 'class' => null])

{{-- Prose escapes the text first, then adds paragraphs, lists, `code` and **bold**. --}}
{!! TruLaravelClub\BlscGuide\Prose::toHtml($text, $class) !!}
