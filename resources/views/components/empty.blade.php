<div {{ $attributes->merge(['class' => 'ui-empty']) }}>
    {{ $slot }}
    @isset($action)
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endisset
</div>
