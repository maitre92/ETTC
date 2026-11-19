<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** php artisan site:test-email : vérifie la configuration SMTP en envoyant un message simple. */
class TestEmailCommandTest extends TestCase
{
    public function test_it_sends_a_test_message(): void
    {
        Mail::fake();
        $this->artisan('site:test-email', ['adresse' => 'moi@exemple.ml'])->expectsOutputToContain('E-mail envoyé à moi@exemple.ml')->assertSuccessful();
    }
}
