<?php
namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\TreeService;

/**
 * Data Transfer Object voor de admin-pagina
 */
class AdminPageDto
{
    public function __construct(
        public array $allTrees,
        public string $buttonText,
        public array $selectedTrees,
        public bool $gendexExists,
        public string $gendexUrl,
        public string $baseUrl
    ) {}
}

/**
 * Provider die de data voor de admin-pagina opbouwt
 */
class AdminPageData
{
    private TreeService $treeService;
    private string $rootDir;
    // private ?I18N $i18n;

    /**
     * @param TreeService $treeService  Service om stambomen op te halen
     * @param string $rootDir           Webtrees root directory (Webtrees::ROOT_DIR)
     * @param I18N|null $i18n           Optionele I18N instance
     */
    public function __construct(TreeService $treeService, string $rootDir)
    {
        $this->treeService = $treeService;
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        /*$this->i18n = $i18n;*/
    }

    /**
     * Bouwt een AdminPageDto uit de inkomende request
     */
    public function fromRequest(ServerRequestInterface $request): AdminPageDto
    {
        $baseUrl = $this->buildBaseUrlFromRequest($request);
        $gendexPath = $this->rootDir . 'gendex.txt';
        $gendexExists = is_readable($gendexPath) && file_exists($gendexPath);
        $gendexUrl = rtrim($baseUrl, '/') . '/gendex.txt';
    
        return new AdminPageDto(
            $this->buildAllTrees(),
            $this->buildButtonText(), // blijft werken omdat buildButtonText gebruikt $this->rootDir
            $this->getSelectedTrees($request),
            $gendexExists,
            $gendexUrl,
            $baseUrl
        );
    }

    /**
     * Haalt alle stambomen op en zet ze om naar [id => "name - title"]
     */
    private function buildAllTrees(): array
    {
        return $this->treeService
            ->all()
            ->mapWithKeys(fn($t) => [$t->id() => $t->name() . ' - ' . $t->title()])
            ->toArray();
    }

    /**
     * Bepaalt de knoptekst op basis van of gendex.txt bestaat
     */
    // In module.php: niet meer nodig
// In AdminPageData: altijd I18N::translate() gebruiken
private function buildButtonText(): string
{
    $path = $this->rootDir . 'gendex.txt';
    $exists = file_exists($path);
    return $exists
        ? I18N::translate('Replace GENDEX text file')
        : I18N::translate('Create GENDEX text file');
}

    /**
     * Haalt geselecteerde stambomen uit de query-parameters (fallback leeg array)
     */
    private function getSelectedTrees(ServerRequestInterface $request): array
    {
        $params = $request->getQueryParams();
        return $params['selected_trees'] ?? [];
    }
    
    private function buildBaseUrlFromRequest(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();
        
        $authority = $host . ($port && !in_array($port, [80, 443]) ? ':' . $port : '');
        return $scheme . '://' . $authority;
    }

}
