<?php

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\Http\Middleware\AuthAdministrator;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminPage implements RequestHandlerInterface
{
    private GendexGeneratorModule $module;

    public function __construct(GendexGeneratorModule $module)
    {
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return response(
            View::make($this->module->name() . '::admin-page', [
                'title' => I18N::translate('Gendex Generator - Configuratie'),
            ])
        );
    }
}