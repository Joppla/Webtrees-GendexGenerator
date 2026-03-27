<?php

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Http\ViewResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function redirect;

class GendexGeneratorModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    public function title(): string
    {
        return I18N::translate('Gendex Generator');
    }

    public function description(): string
    {
        return I18N::translate('Generates a gendex.txt file for your family tree.');
    }

    public function customModuleAuthorName(): string
    {
        return 'Joppla';
    }

    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/Joppla/Webtrees-GendexGenerator';
    }

    public function customTranslations(string $language): array
    {
        $translations = [
            // Engels (standaard)
            'en' => [
              'Gendex Generator' => 'Gendex Generator',
              'Generates a gendex.txt file for your family tree.' => 'Generates a gendex.txt file for your family tree.',
            ],

            // Nederlands
            'nl' => [
                'Gendex Generator' => 'Gendex Generator',
                'Generates a gendex.txt file for your family tree.' => 'Genereert een gendex.txt-bestand voor je stamboom.',
            ],
        ];

        return $translations[$language] ?? $translations['en'];
    }

    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        return response(
            View::make($this->name() . '::admin-page', [
                'title' => $this->title(),
            ])
        );
    }
    
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        // Hier kun je later de configuratie opslaan
        FlashMessages::addMessage(I18N::translate('The preferences for the module “%s” have been updated.', $this->title()), 'success');
    
        return redirect(route('admin-module-' . $this->name()));
    }

    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
/*    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    
        /** @var RouterContainer $router *
        $router = app(RouterContainer::class);
        $map = $router->getMap();
    
        $map->get(AdminPage::class, '/gendex-config', new AdminPage($this))
            ->middleware(AuthAdministrator::class);
    }*/


/*    public function boot(): void
    {
        route('admin-module-' . $this->name(), AdminPage::class)
            ->middleware(AuthAdministrator::class);
    }*/
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }
 
}

return new GendexGeneratorModule();