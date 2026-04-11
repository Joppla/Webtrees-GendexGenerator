<?php
declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class AdminPage
{
    private GendexGeneratorModule $module;

    public function __construct(GendexGeneratorModule $module)
    {
        $this->module = $module;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->module->getAdminAction($request);
    }
}
