@props(['active' => false])

@php
$classes = $active
    ? 'nav-item nav-item-active'
    : 'nav-item nav-item-idle';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} data-nav-active="nav-item-active" data-nav-idle="nav-item-idle" @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
