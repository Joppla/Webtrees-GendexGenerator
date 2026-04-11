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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            return $this->module->postAdminAction($request);
        }
        return $this->module->getAdminAction($request);
    }
}
