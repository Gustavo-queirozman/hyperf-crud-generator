<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Authorization;

interface AuthorizationInterface
{
    public function allows(string $resource, string $ability, mixed $subject = null): bool;
}
