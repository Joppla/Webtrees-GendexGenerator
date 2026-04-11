<?php
declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminAction implements RequestHandlerInterface
{
    private GendexGeneratorModule $module;

    public function __construct(GendexGeneratorModule $module)
    {
        $this->module = $module;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatch($request);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatch($request);
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'POST') {
            return $this->module->postAdminAction($request);
        }

        return $this->module->getAdminAction($request);
    }
}
