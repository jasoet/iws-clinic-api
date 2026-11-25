<?php

it('refuses a request without a key', function () {
    withKey();
    $this->getJson('/api/v1/health')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 401);
});

it('refuses a wrong key', function () {
    withKey();
    $this->withToken('nope')->getJson('/api/v1/health')->assertStatus(401);
});

it('refuses everything when no keys are configured', function () {
    config(['iws.api_keys' => []]);
    $this->withToken('')->getJson('/api/v1/health')->assertStatus(401);
});

it('accepts any configured key', function () {
    config(['iws.api_keys' => ['change-me-a', 'change-me-b']]);
    $this->withToken('change-me-b')->getJson('/api/v1/health')->assertOk();
});

it('refuses an IP outside the allow-list', function () {
    $headers = withKey();
    config(['iws.allowed_ips' => ['10.0.0.1']]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/v1/health', $headers)
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('accepts an IP on the allow-list', function () {
    $headers = withKey();
    config(['iws.allowed_ips' => ['10.0.0.1']]);
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/v1/health', $headers)
        ->assertOk();
});

it('answers unknown paths with problem json', function () {
    $this->getJson('/api/v1/nope', withKey())
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 404);
});
