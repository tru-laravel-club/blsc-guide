@props(['links'])

@if ($links !== [])
    <ul class="links">
        @foreach ($links as $link)
            <li>
                <span class="link-type">{{ $link['type'] === 'video' ? 'Watch' : 'Read' }}</span>
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['title'] }}</a>
            </li>
        @endforeach
    </ul>
@endif
