<?php

it('executes the concurrency simulation command and validates single-slot reservation integrity', function () {
    $this->artisan('clinic:simulate-concurrency', ['--attempts' => 3])
        ->expectsOutputToContain('Klinika Concurrency & Race-Condition Demonstrator')
        ->expectsOutputToContain('INTEGRITY PASS: Exactly 1 transaction acquired the slot reservation.')
        ->assertSuccessful();
});

