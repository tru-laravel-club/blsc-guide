@props(['text', 'class' => null])

{{-- Escape the text first. Then turn `code` into <code> and blank lines into paragraphs. --}}
@foreach (preg_split('/\n\s*\n/', trim($text)) as $paragraph)
    <p @class([$class])>{!! preg_replace('/`([^`]+)`/', '<code>$1</code>', e($paragraph)) !!}</p>
@endforeach
