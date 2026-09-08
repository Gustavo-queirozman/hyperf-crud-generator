<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Http;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

final class DocumentationController
{
    public function __construct(private readonly ConfigInterface $config, private readonly ResponseInterface $response)
    {
    }

    public function index(): PsrResponseInterface
    {
        $urls = [];
        foreach (glob($this->directory() . '/*.json') ?: [] as $file) {
            $name = basename($file, '.json');
            $urls[] = ['name' => $name, 'url' => '/docs/openapi/' . rawurlencode($name) . '.json'];
        }
        $json = json_encode($urls, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return $this->response->html(<<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>API documentation</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css"></head>
<body><div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>SwaggerUIBundle({urls: {$json}, dom_id: '#swagger-ui', deepLinking: true, validatorUrl: null});</script>
</body></html>
HTML);
    }

    public function spec(string $name): PsrResponseInterface
    {
        if (! preg_match('/^[a-z0-9_]+$/D', $name)) {
            return $this->response->json(['message' => 'Specification not found'])->withStatus(404);
        }
        $path = $this->directory() . '/' . $name . '.json';
        if (! is_file($path)) {
            return $this->response->json(['message' => 'Specification not found'])->withStatus(404);
        }
        return $this->response->raw((string) file_get_contents($path))->withHeader('Content-Type', 'application/json');
    }

    private function directory(): string
    {
        return rtrim((string) $this->config->get('crud_generator.openapi_path', BASE_PATH . '/docs/openapi'), '/\\');
    }
}
