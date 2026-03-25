<?php

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminAction implements RequestHandlerInterface
{
    protected GendexGeneratorModule $module;

    public function __construct(GendexGeneratorModule $module)
    {
        $this->module = $module;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array)$request->getParsedBody();

        // Save enabled trees
        if (isset($params['enabled_trees']) && is_array($params['enabled_trees'])) {
            $this->module->setSetting('enabled_trees', implode(',', $params['enabled_trees']));
        } else {
            $this->module->setSetting('enabled_trees', '');
        }

        // Save other settings
        $this->module->setSetting('year_only', isset($params['year_only']) ? '1' : '0');
        $this->module->setSetting('include_birthplace', isset($params['include_birthplace']) ? '1' : '0');
        $this->module->setSetting('include_death', isset($params['include_death']) ? '1' : '0');

        return redirect(route(AdminPage::class));
    }
}