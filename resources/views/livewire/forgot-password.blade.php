<div class="w-full max-w-md mx-auto">
    <div class="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold text-gray-900">Forgot password</h1>
                <p class="text-sm text-gray-600 mt-1">
                    @if($step === 'request')
                        Enter your email to receive a one-time password (OTP).
                    @elseif($step === 'verify')
                        Enter the OTP sent to your email.
                    @elseif($step === 'reset')
                        Create your new password.
                    @else
                        You’re all set.
                    @endif
                </p>
            </div>
            @if($step !== 'request' && $step !== 'done')
                <button type="button" class="btn btn-ghost btn-sm" wire:click="backToRequest">Change email</button>
            @endif
        </div>

        @if($successMessage)
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ $successMessage }}
            </div>
        @endif

        @if($errors->has('api_error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first('api_error') }}
            </div>
        @endif

        @if($step === 'request')
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Email</label>
                    <input type="email"
                           wire:model.defer="email"
                           class="input input-bordered w-full bg-white text-gray-900"
                           placeholder="Enter your email" />
                    @error('email') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <button type="button"
                        class="btn clr-bg-primary text-base-100 w-full"
                        wire:click="sendOtp">
                    Send OTP
                </button>

                <div class="text-sm text-gray-600 text-center">
                    <a href="{{ route('login') }}" class="underline font-medium">Back to login</a>
                </div>
            </div>
        @elseif($step === 'verify')
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Email</label>
                    <input type="email"
                           wire:model.defer="email"
                           class="input input-bordered w-full bg-white text-gray-900"
                           placeholder="Enter your email" />
                    @error('email') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">OTP code</label>
                    <input type="text"
                           wire:model.defer="code"
                           class="input input-bordered w-full bg-white text-gray-900 tracking-widest"
                           placeholder="Enter OTP" />
                    @error('code') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-row gap-2">
                    <button type="button"
                            class="btn btn-outline w-full"
                            wire:click="sendOtp">
                        Resend OTP
                    </button>
                    <button type="button"
                            class="btn clr-bg-primary text-base-100 w-full"
                            wire:click="verifyOtp">
                        Verify
                    </button>
                </div>
            </div>
        @elseif($step === 'reset')
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Email</label>
                    <input type="email"
                           wire:model.defer="email"
                           class="input input-bordered w-full bg-white text-gray-900"
                           placeholder="Enter your email" />
                    @error('email') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">New password</label>
                    <input type="password"
                           wire:model.defer="newPassword"
                           class="input input-bordered w-full bg-white text-gray-900"
                           placeholder="Enter new password" />
                    @error('newPassword') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Confirm password</label>
                    <input type="password"
                           wire:model.defer="confirmPassword"
                           class="input input-bordered w-full bg-white text-gray-900"
                           placeholder="Confirm new password" />
                    @error('confirmPassword') <p class="text-xs text-red-600 font-medium">{{ $message }}</p> @enderror
                </div>

                <button type="button"
                        class="btn clr-bg-primary text-base-100 w-full"
                        wire:click="resetPassword">
                    Reset password
                </button>
            </div>
        @else
            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    {{ $successMessage ?? 'Password reset complete.' }}
                </div>
                <a class="btn clr-bg-primary text-base-100 w-full" href="{{ route('login') }}">
                    Back to login
                </a>
            </div>
        @endif
    </div>
</div>

