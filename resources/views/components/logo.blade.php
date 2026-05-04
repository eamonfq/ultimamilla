@props(['class' => ''])
<div {{ $attributes->merge(['class' => 'inline-flex items-baseline font-bold text-2xl tracking-tight ' . $class]) }}>
    <span>Última Milla</span>
    <span class="ml-0.5 w-1.5 h-1.5 rounded-full bg-indigo-500 inline-block"></span>
</div>
