<?php

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\TreeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminPage implements RequestHandlerInterface
{
    use ViewResponseTrait;

    protected TreeService $treeService;
    protected GendexGeneratorModule $module;

    public function __construct(GendexGeneratorModule $module)
    {
        $this->module = $module;
        $this->treeService = app(TreeService::class);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $title = I18N::translate('Gendex Generator Settings');

        // Get all trees
        $trees = $this->treeService->all();

        // Get current settings
        $enabled_trees = $this->getEnabledTrees();
        $year_only = $this->module->getSetting('year_only', '0') === '1';
        $include_birthplace = $this->module->getSetting('include_birthplace', '1') === '1';
        $include_death = $this->module->getSetting('include_death', '1') === '1';

        return $this->viewResponse($this->module->name() . '::settings', [
            'title' => $title,
            'trees' => $trees,
            'enabled_trees' => $enabled_trees,
            'year_only' => $year_only,
            'include_birthplace' => $include_birthplace,
            'include_death' => $include_death,
        ]);
    }

    private function getEnabledTrees(): array
    {
        $setting = $this->module->getSetting('enabled_trees', '');
        return $setting ? explode(',', $setting) : [];
    }
}