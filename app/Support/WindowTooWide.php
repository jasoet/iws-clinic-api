<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A window wider than IWS_MAX_WINDOW_DAYS: answered 422. */
final class WindowTooWide extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(422, $message);
    }
}
