<?php

namespace App\Livewire\Auth;

use Livewire\Component;

class LoginForm extends Component
{
    public bool $showPassword = false;

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
