<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A request iws should fix: answered 400. */
final class BadRequest extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(400, $message);
    }
}
