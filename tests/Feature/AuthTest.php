<?php

use App\Models\User;

it('returns a token for valid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('validates required login fields', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('throttles login attempts', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

it('returns the authenticated user', function () {
    $user = User::factory()->manager()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'manager');
});

it('rejects unauthenticated requests with json', function () {
    $this->get('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('revokes the token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
