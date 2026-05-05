<?php

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('login request is forwarded to auth handler', function () {
    $response = $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'secret',
    ]);

    $response->assertStatus(302);
});

test('users can not authenticate with invalid password', function () {
    $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('logout request is handled by auth handler', function () {
    $response = $this->post('/logout');

    $response->assertStatus(302);
});
