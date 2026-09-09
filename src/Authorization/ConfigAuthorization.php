<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Authorization;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;

/** Replace this binding to integrate your application's RBAC/ACL provider. */
final class ConfigAuthorization implements AuthorizationInterface
{
    public function __construct(private readonly ConfigInterface $config, private readonly RequestInterface $request)
    {
    }

    public function allows(string $resource, string $ability, mixed $subject = null): bool
    {
        $rule = $this->config->get('crud_generator.authorization.rules.' . $resource . '.' . $ability);
        if (is_callable($rule)) {
            return $rule($this->request, $subject) === true;
        }
        return $this->config->get('crud_generator.authorization.default', 'allow') === 'allow';
    }
}
