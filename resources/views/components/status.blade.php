@props(['status'])

<span class="status status-{{ $status->value }}">{{ $status->symbol() }} {{ $status->label() }}</span>
