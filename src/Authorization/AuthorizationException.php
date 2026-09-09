<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Authorization;

final class AuthorizationException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Forbidden', 403);
    }
}
