<?php

namespace App\Livewire;

use App\Http\Controllers\Auth\ForgotPasswordOtpController;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $step = 'request'; // request | verify | reset | done

    public string $email = '';
    public string $code = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    public ?string $successMessage = null;

    public function sendOtp(): void
    {
        $this->resetErrorBag();
        $this->successMessage = null;

        $this->validate([
            'email' => ['required', 'string'],
        ], [
            'email.required' => 'Email is required.',
        ]);

        $result = app(ForgotPasswordOtpController::class)->sendResetOtp($this->email);
        if (!($result['ok'] ?? false)) {
            foreach (($result['errors'] ?? []) as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    $this->addError($field, (string) $m);
                }
            }
            return;
        }

        $this->step = 'verify';
        $this->successMessage = 'OTP has been sent. Please check your email.';
    }

    public function verifyOtp(): void
    {
        $this->resetErrorBag();
        $this->successMessage = null;

        $this->validate([
            'email' => ['required', 'string'],
            'code'  => ['required', 'string'],
        ], [
            'email.required' => 'Email is required.',
            'code.required'  => 'OTP code is required.',
        ]);

        $result = app(ForgotPasswordOtpController::class)->verifyOtp($this->email, $this->code);
        if (!($result['ok'] ?? false)) {
            foreach (($result['errors'] ?? []) as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    $this->addError($field, (string) $m);
                }
            }
            return;
        }

        $this->step = 'reset';
        $this->successMessage = 'OTP verified. Please set your new password.';
    }

    public function resetPassword(): mixed
    {
        $this->resetErrorBag();
        $this->successMessage = null;

        $this->validate([
            'email'           => ['required', 'string'],
            'newPassword'     => ['required', 'string', 'min:6'],
            'confirmPassword' => ['required', 'same:newPassword'],
        ], [
            'email.required'           => 'Email is required.',
            'newPassword.required'     => 'New password is required.',
            'newPassword.min'          => 'Password must be at least 6 characters.',
            'confirmPassword.required' => 'Please confirm your new password.',
            'confirmPassword.same'     => 'Passwords do not match.',
        ]);

        $result = app(ForgotPasswordOtpController::class)->resetPassword($this->email, $this->newPassword);
        if (!($result['ok'] ?? false)) {
            foreach (($result['errors'] ?? []) as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    $this->addError($field, (string) $m);
                }
            }
            return null;
        }

        $this->step = 'done';
        $this->successMessage = 'Password reset successful. You can now log in.';

        return null;
    }

    public function backToRequest(): void
    {
        $this->resetErrorBag();
        $this->successMessage = null;
        $this->step = 'request';
        $this->code = '';
        $this->newPassword = '';
        $this->confirmPassword = '';
    }

    public function render()
    {
        return view('livewire.forgot-password');
    }
}

