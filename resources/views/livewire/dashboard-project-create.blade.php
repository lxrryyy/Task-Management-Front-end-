<div>
    @if($showAddModal)
        <dialog class="modal modal-open">
            <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                <div class="modal-action">
                    <button type="button" wire:click="close" class="btn">X</button>
                </div>
                <h3 class="font-bold text-lg">New Project</h3>

                <form method="POST" action="{{ route('projects.store') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="redirect_to" value="dashboard" />
                    @include('livewire.partials.project-form-fields', ['formContext' => 'add'])
                    <div class="modal-action">
                        <button type="submit" class="btn clr-bg-primary text-base-100 px-2">Add Project</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="button" wire:click="close">close</button>
            </form>
        </dialog>
    @endif
</div>

