<?php

it('reports ok with the database up', function () {
    $this->getJson('/api/v1/health', withKey())
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'ok', 'version' => '1.0.0', 'contract' => 'v1']);
});

it('reports 503 when the database is down', function () {
    config(['database.connections.sqlite.database' => '/nonexistent/dir/missing.sqlite']);
    app('db')->purge('sqlite');

    $this->getJson('/api/v1/health', withKey())
        ->assertStatus(503)
        ->assertJsonPath('database', 'down');
});
