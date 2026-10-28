<?php

use App\Providers\AppServiceProvider;

it('keeps call arguments out of exception traces whatever the server sets', function () {
    ini_set('zend.exception_ignore_args', '0');
    ini_set('zend.exception_string_param_max_len', '15');
    app()->getProvider(AppServiceProvider::class)->register();

    $exception = (fn (string $email): RuntimeException => new RuntimeException('Failed'))('asha@example.com');

    expect($exception->getTraceAsString())->not->toContain('asha@');
});
