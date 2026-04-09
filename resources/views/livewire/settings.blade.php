<div>
    <div class="w-full">
        <div class="flex w-full items-center clr-primary ">
            <a href="/dashboard"
                class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('settings') ? 'clr-primary' : '' }} hover-clr-accent">
                <x-icons.back-btn classes="w-6 h-6" />
            </a>
            <span class="group-hover:block text-xl">Account Settings</span>
        </div>
        <hr class="border-2 clr-bg-primary">

        <div class="flex flex-col p-4 bg-white mt-4 rounded-lg">
            <h1 class="text-2xl">Basic Details</h1>
            <hr class="border-2 mt-4">
            <div class="flex flex-row justify-between gap-4 mt-4">
                <div class="flex flex-row gap-4">
                    {{-- Avatar: if image exists, hide initials and remove bg; if image fails, show initials and bg --}}
                    @php
                        $avatarSrc = $photoPreview ?: $profilePicture;
                        $avatarHasImage = !empty($avatarSrc);
                        $initialsTextClass = $avatarBg === '#F0EFEF' ? 'text-gray-800' : 'text-white';
                    @endphp
                    <div data-avatar-wrap data-avatar-bg="{{ $avatarBg }}"
                        class="h-24 w-24 rounded-full overflow-hidden border-2 border-gray-200 flex items-center justify-center"
                        style="background-color: {{ $avatarHasImage ? 'transparent' : $avatarBg }};">
                        {{-- Always render initials as the fallback; hide it only if the image loads --}}
                        <span data-avatar-initials class="font-semibold {{ $initialsTextClass }}"
                            style="{{ $avatarHasImage ? 'display:none;' : '' }}">
                            {{ $initials }}
                        </span>
                        @if (!empty($avatarSrc))
                            <img src="{{ $avatarSrc }}" alt="Profile photo" class="h-full w-full object-cover"
                                onload="var wrap=this.closest('[data-avatar-wrap]'); if(wrap){var s=wrap.querySelector('[data-avatar-initials]'); if(s){s.style.display='none';}}"
                                onerror="var wrap=this.closest('[data-avatar-wrap]'); if(wrap){this.style.display='none'; wrap.style.backgroundColor = wrap.dataset.avatarBg || 'transparent'; var s=wrap.querySelector('[data-avatar-initials]'); if(s){s.style.display='flex';}}" />
                        @endif
                    </div>
                    <div class="flex flex-col">
                        <h1 class="font-semibold text-xl">
                            {{ $fullName ?: trim($firstName . ' ' . $lastName) ?: 'Account' }}</h1>
                        @if (trim((string) $bio) !== '')
                            <span id="bio" class="text-sm text-gray-600">{{ $bio }}</span>
                        @endif
                    </div>
                </div>
                <div class="">
                    {{-- Hidden file input controlled by label button --}}
                    <input id="settings-photo" type="file" class="hidden" accept="image/*" wire:model="photo"
                        wire:key="settings-photo-input-{{ $photo ? 'has' : 'none' }}" />

                    <label for="settings-photo"
                        class="btn w-48 text-base-100 font-normal mt-4 clr-bg-primary rounded-lg p-4 border-2 border-gray-400 hover-clr-primary hover-clr-bg-white">
                        Upload Photo
                    </label>

                    <button type="button" wire:click="removeProfilePicture"
                        class="btn w-48 text-base-100 mt-4 border-2 border-gray rounded-lg p-4 clr-primary hover-clr-bg-primary hover:text-base-100">
                        Remove
                    </button>
                </div>
            </div>

            {{-- Confirmation modal after selecting a new photo --}}
            <dialog class="{{ $showPhotoConfirmModal ? 'modal modal-open' : 'modal' }}" wire:key="photo-confirm-modal"
                wire:ignore.self>
                <div class="modal-box max-w-md">
                    <h3 class="font-normal text-lg">Confirm profile picture</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Are you sure you want to use this photo for your profile?
                    </p>

                    <div class="mt-4 flex flex-col items-center gap-2">
                        @if ($photoPreview)
                            <img src="{{ $photoPreview }}" alt="Selected profile photo"
                                class="h-24 w-24 rounded-full object-cover border border-gray-200" />
                        @else
                            <div class="h-24 w-24 rounded-full border border-gray-200 flex items-center justify-center">
                                <span class="text-sm text-gray-500">No preview</span>
                            </div>
                        @endif
                        @if (!empty($pendingPhotoName))
                            <div class="text-xs text-gray-500 break-all text-center">{{ $pendingPhotoName }}</div>
                        @endif
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" wire:click="cancelPhotoSelection">Cancel</button>
                        <button type="button" class="btn clr-bg-primary text-base-100"
                            wire:click="confirmPhotoSelection">Confirm</button>
                    </div>
                </div>
                <div class="modal-backdrop" wire:click="cancelPhotoSelection"></div>
            </dialog>

            @if ($saveError)
                <div
                    wire:key="settings-error-{{ $saveErrorNonce }}"
                    x-data
                    x-init="setTimeout(() => $wire.clearSaveErrorBanner(), 4000)"
                    class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                    {{ $saveError }}
                </div>
            @endif

            @if ($saveSuccessMessage)
                <div
                    wire:key="settings-success-{{ $saveSuccessNonce }}"
                    x-data
                    x-init="setTimeout(() => $wire.clearSaveSuccessBanner(), 4000)"
                    class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
                    {{ $saveSuccessMessage }}
                </div>
            @endif

            <div class="flex flex-row w-full gap-8 mt-4">
                <div class="flex flex-col flex-1">
                    <span>First Name</span>
                    <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="firstName">
                </div>
                <div class="flex flex-col flex-1">
                    <span>Last Name</span>
                    <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="lastName">
                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Bio/Specialization</span>
                    <input type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="bio">
                    <span class="text-xs text-gray-500">Shows under your name</span>
                </div>
                <div class="flex flex-col flex-1 mt-2 gap-4">
                    <span>Email:</span>
                    <span>{{ $email }}</span>
                </div>
            </div>
            <hr class="border-2 mt-4">
            <h1 class="text-2xl mt-4">Change Password</h1>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Current Password</span>
                    <input type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="currentPassword">
                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>New Password</span>
                    <input type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="newPassword">
                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Confirm Password</span>
                    <input type="password" class="w-full rounded-lg border border-gray-300 px-3 py-2 mt-1"
                        wire:model.defer="confirmPassword">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="button" wire:click="saveChanges"
                    class="btn w-48 text-base-100 mt-4 clr-bg-primary rounded-lg p-4" @disabled($saving)>
                    {{ $saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>
