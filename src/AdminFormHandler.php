<?php
namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Services\TreeService;

class AdminFormHandler
{
    private TreeService $treeService;
    private AdminPageData $adminPageData;
    private string $rootDir;
    private string $moduleName;

    /**
     * Constructor
     *
     * @param TreeService $treeService  Service om stambomen op te halen
     * @param AdminPageData $adminPageData  Data-provider voor admin-pagina
     * @param string $rootDir  Webtrees root directory (Webtrees::ROOT_DIR)
     * @param string $moduleName  Naam/slug van de module (gebruik $this->name() in module)
     */
    public function __construct(TreeService $treeService, AdminPageData $adminPageData, string $rootDir, string $moduleName)
    {
        $this->treeService = $treeService;
        $this->adminPageData = $adminPageData;
        // Zorg dat $rootDir eindigt met DIRECTORY_SEPARATOR
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->moduleName = $moduleName;
    }

    /**
     * Hoofdhandler voor het formulier (vervangt postAdminAction).
     * - valideert input
     * - toont flash-berichten
     * - roept de generator aan
     * - redirect terug naar de admin-pagina met query-parameters
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Haal en valideer de geselecteerde stamboom-id's uit het formulier
        $selected = $this->validateSelectedTrees($request->getParsedBody()['selected_trees'] ?? []);

        if (empty($selected)) {
            // Geen selectie: toon een foutmelding (danger)
            $this->addFlash('No family trees selected.', 'danger');
        } else {
            // Toon welke stambomen geselecteerd zijn en genereer het bestand
            $this->notifySelectedTrees($selected);
            $this->generateGendex($selected);
            $this->addFlash('The GENDEX file has been generated successfully.', 'success');
        }

        // Redirect terug naar de admin-pagina, met de geselecteerde stambomen in de query
        return $this->buildRedirectResponse($selected);
    }

    /**
     * Valideert en normaliseert de input-array met geselecteerde stamboom-id's.
     * - filtert non-integer waarden eruit
     * - cast naar int, verwijdert duplicates en behoudt volgorde
     */
    private function validateSelectedTrees(array $input): array
    {
        $ids = [];
        foreach ($input as $id) {
            // FILTER_VALIDATE_INT retourneert false bij ongeldige integers
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id !== false) {
                $ids[] = (int)$id;
            }
        }
        // Verwijder dubbele id's en herindexeer de array
        return array_values(array_unique($ids));
    }

    /**
     * Bouwt en toont een HTML-lijst van geselecteerde stambomen via FlashMessages.
     * - haalt titels op via TreeService
     * - encodeert output met helper e() om XSS te voorkomen
     */
    private function notifySelectedTrees(array $selected): void
    {
        $items = [];
        foreach ($selected as $id) {
            // Haal de boom op; TreeService->find geeft null terug als het niet bestaat
            $tree = $this->treeService->find($id);
            if ($tree) {
                // e() is een helper in webtrees die HTML-escape doet
                $items[] = '<li>' . e($tree->title()) . '</li>';
            }
        }

        if (!empty($items)) {
            $html = '<ul style="list-style-type: disc; padding-left: 1em;">' 
                . implode('', $items) 
                . '</ul>';
            // Gebruik I18N::translate voor vertalingen; de vertaalstring bevat %s voor de lijst
            $this->addFlash(
                I18N::translate(
                    'The following family trees have been selected: %s', 
                    $html
                ), 
                'success'
            );
        }
    }

    /**
     * Wrapper die de bestaande MakeGendex aanroept om het bestand te genereren.
     * - hier kun je later error handling of logging toevoegen
     */
    private function generateGendex(array $selected): void
    {
        $generator = new MakeGendex();
        $generator->generateGendexFile($selected);
    }

    /**
     * Helper om flash-berichten toe te voegen.
     * - vertaalt het bericht met I18N voordat het aan FlashMessages wordt doorgegeven
     */
    private function addFlash(string $message, string $type = 'info'): void
    {
        FlashMessages::addMessage(I18N::translate($message), $type);
    }

    /**
     * Bouwt de redirect-response terug naar de module-adminpagina.
     * - maakt een query-string met selected_trees
     * - gebruikt route('module', ['module' => $this->moduleName, 'action' => 'Admin'])
     */
    private function buildRedirectResponse(array $selected): ResponseInterface
    {
        $query = http_build_query(['selected_trees' => $selected]);

        // redirect() en route() zijn globale helperfuncties van webtrees
        return redirect(
            route(
                'module', ['module' => $this->moduleName, 'action' => 'Admin']
            ) 
            . '?' 
            . $query
        );
    }
}
