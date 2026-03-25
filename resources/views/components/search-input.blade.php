@props([
    'placeholder' => 'Search',
    'inputClass' => 'w-40 bg-transparent focus:outline-none rounded-lg',
    'containerClass' => 'input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1',
])

<label {{ $attributes->only('id')->class($containerClass) }}>
    <input
        type="search"
        placeholder="{{ $placeholder }}"
        class="{{ $inputClass }}"
        {{ $attributes->except(['id', 'class']) }}
    />
</label>
