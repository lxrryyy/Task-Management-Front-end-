@props([
    'placeholder' => 'Search',
    'inputClass' => 'w-64 bg-transparent text-gray-900 placeholder:text-gray-500 focus:outline-none rounded-lg',
    'containerClass' => 'input focus-within:outline-none bg-white text-gray-900 border-gray-300 focus-within:border-gray-400 rounded-lg flex-1',
])

<label {{ $attributes->only('id')->class($containerClass) }}>
    <input
        type="search"
        placeholder="{{ $placeholder }}"
        class="{{ $inputClass }}"
        {{ $attributes->except(['id', 'class']) }}
    />
</label>
