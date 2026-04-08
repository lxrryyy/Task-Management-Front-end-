@props([
    'buttonClass' => 'btn border-2 border-gray rounded-lg clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100',
    'panelClass' => 'dropdown-content bg-base-100 rounded-box z-50 w-80 p-4 shadow-lg mt-1 border border-gray-200',
    'clearAction' => null,
])

<div class="dropdown dropdown-end">
    <button tabindex="0" type="button" class="{{ $buttonClass }}">
        <x-icons.sort class="w-4 h-4 inline-block" /> Filter
    </button>

    <div tabindex="-1" class="{{ $panelClass }}">
        <div class="grid grid-cols-1 gap-3 text-sm">
            {{ $slot }}

            @if ($clearAction)
                <div class="flex justify-end">
                    <button type="button" class="btn btn-sm border border-gray-300 bg-white text-gray-700 p-4"
                        wire:click="{{ $clearAction }}">
                        Clear
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
