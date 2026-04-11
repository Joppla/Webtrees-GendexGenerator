<?php

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\Webtrees;
use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Factories\RouteFactory;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Localization\Translation;
// use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Joppla\Modules\GendexGenerator\MakeGendex;
use Joppla\Modules\GendexGenerator\AdminDataPage;
use Joppla\Modules\GendexGenerator\AdminPageDto;
use Joppla\Modules\GendexGenerator\AdminFormHandler;
use Joppla\Modules\GendexGenerator\AdminPage;
use Joppla\Modules\GendexGenerator\AdminAction;

/**
 * Hoofdklasse van de module.
 * Verantwoordelijkheden:
 *  - module metadata (title, description, auteur, versie)
 *  - registratie van view namespace bij bootstrap
 *  - aanbieden van admin pagina (GET) en formulierverwerking (POST)
 */
class GendexGeneratorModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    private TreeService $treeService;

    /**
     * Constructor
     *
     * - registreert PSR-4 autoloading voor classes in src/
     * - haalt TreeService uit de container
     * - bouwt AdminPageData (data-provider) met optionele I18N uit container
     */
    public function __construct()
    {
        // Zorg dat onze classes in src/ via PSR-4 geladen worden
        $loader = new ClassLoader();
        $loader->addPsr4('Joppla\\Modules\\GendexGenerator\\', __DIR__ . '/src');
        $loader->register();

        // Haal services uit de dependency container
        $this->treeService = Registry::container()->get(TreeService::class);
     
        // Probeer I18N uit container; fallback naar null als niet aanwezig
        $i18n = Registry::container()->has(I18N::class)
            ? Registry::container()->get(I18N::class)
            : null;
    
        // AdminPageData houdt logic voor het opbouwen van de admin view data
        $this->adminPageData = new AdminPageData(
            $this->treeService,
            Webtrees::ROOT_DIR,
            $i18n
        );
    }    

    /**
     * Titel van de module (getoond in de module-lijst)
     */
    public function title(): string
    {
        return I18N::translate('Gendex Generator');
    }

    /**
     * Korte omschrijving van de module
     */
    public function description(): string
    {
        return I18N::translate('Generates a gendex.txt file for your family tree.');
    }

    /**
     * Auteurnaam die getoond wordt in de module-info
     */
    public function customModuleAuthorName(): string
    {
        return 'Joppla';
    }

    /**
     * Module versie
     */
    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Support / project URL
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/Joppla/Webtrees-GendexGenerator';
    }

    /**
     * Laadt vertalingen voor de module (uit resources/lang/)
     *
     * @param string $language ISO-taalcode, bijv. 'en' of 'nl'
     * @return array associatieve array met vertalingen
     */
    public function customTranslations(string $language): array
    {
        $base = $this->resourcesFolder() . 'lang/' . $language;
        $languageFile = file_exists($base . '.mo') ? $base . '.mo' : ($base . '.po');
        return file_exists($languageFile) ? (new Translation($languageFile))->asArray() : [];
    }

    /**
     * GET: Adminpagina tonen
     *
     * - vraagt AdminPageData op (DTO) op basis van de request
     * - stelt layout en view-variabelen in
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        // Bouw DTO met alle benodigde data voor de view
        $dto = $this->adminPageData->fromRequest($request);
    
        // Gebruik de administratie-layout van webtrees
        $this->layout = 'layouts/administration';
        
        $selectedAddAllNames = 0; // 0 = yes, 1 = no
        $selectedDiacritical  = 0; // 0 = yes, 1 = no

    
        // Render de admin-page view met data uit de DTO
        return $this->viewResponse($this->name() . '::admin-page', [
            'title' => $this->title(),
            'all_trees' => $dto->allTrees,
            'button_text' => $dto->buttonText,
            'module_name' => $this->name(),
            'selected_trees' => $dto->selectedTrees,
            'gendex_exists' => $dto->gendexExists,
            'gendex_url' => $dto->gendexUrl,
            'WT_BASE_URL' => $dto->baseUrl,
            'selected_add_all_names' => $selectedAddAllNames,
            'selected_diacritical'  => $selectedDiacritical,

        ]);
    }

    /**
     * POST: Formulierverwerking van de admin-pagina
     *
     * - delegeren naar AdminFormHandler (schone scheiding van concerns)
     * - geef benodigde services en module-naam door aan de handler
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new AdminFormHandler($this->treeService, $this->adminPageData, Webtrees::ROOT_DIR, $this->name());
        return $handler->handle($request);
    }

    /**
     * (Optionele) helper: directe aanroep van de generator.
     * Deze wrapper wordt momenteel niet direct gebruikt omdat de form-handler de generator aanroept,
     * maar kan handig zijn voor tests of andere entrypoints.
     */
    public function generateGendex(array $selectedTrees): void
    {
        $gendexGenerator = new MakeGendex();
        $gendexGenerator->generateGendexFile($selectedTrees);
    }
    
    /**
     * Bootstrap-fase van de module.
     * Hier registreren we de view namespace zodat views in resources/views/ gevonden worden.
     */
    public function boot(): void
    {
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    
        $container = Registry::container();
        if (! $container->has(\Aura\Router\RouterContainer::class)) {
            return;
        }
    
        /** @var \Aura\Router\RouterContainer $router */
        $router = $container->get(\Aura\Router\RouterContainer::class);
        $map = $router->getMap();
        $module = $this;
    
        $map->attach('', '/admin', static function (\Aura\Router\Map $r) use ($module) {
            // Unieke route IDs to avoid collisions with other modules
            $idPage    = AdminPage::class . '::get';
            $idAction  = AdminAction::class . '::post';
    
            // Admin page (GET)
            try {
                $r->getRoute($idPage);
            } catch (\Aura\Router\Exception\RouteNotFound $e) {
                $r->get($idPage, '/gendex-generator', new AdminPage($module));
            }
    
            // Admin action (POST)
            try {
                $r->getRoute($idAction);
            } catch (\Aura\Router\Exception\RouteNotFound $e) {
                $r->post($idAction, '/gendex-generator/save', new AdminAction($module));
            }
        });
    }


    
    /**
     * Pad naar de resources-map van de module.
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }
 
}

return new GendexGeneratorModule();
