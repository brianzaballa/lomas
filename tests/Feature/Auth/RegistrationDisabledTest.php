<?php

use App\Models\User;
use Laravel\Fortify\Features;

it('has no public registration screen', function () {
    $this->get('/register')->assertNotFound();
});

it('does not create an account through the disabled registration endpoint', function () {
    $this->post('/register', [
        'name' => 'Should Not Exist',
        'email' => 'ghost@lomas.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertNotFound();

    expect(User::query()->where('email', 'ghost@lomas.test')->exists())->toBeFalse();
});

it('has no password reset screen when the feature is disabled', function () {
    if (Features::enabled(Features::resetPasswords())) {
        $this->markTestSkipped('Password resets are enabled in this environment.');
    }

    $this->get('/forgot-password')->assertNotFound();
});

it('redirects a guest to the login screen', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('redirects the root path to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
