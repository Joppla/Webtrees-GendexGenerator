<?php
namespace Joppla\Modules\GendexGenerator;

use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Services\TreeService;

/**
 * Data Transfer Object voor de admin-pagina
 */
class AdminPageDto
{
    public function __construct(
        public array $allTrees,
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

    /**
     * @param TreeService $treeService  Service om stambomen op te halen
     * @param string $rootDir           Webtrees root directory (Webtrees::ROOT_DIR)
     */
    public function __construct(TreeService $treeService, string $rootDir)
    {
        $this->treeService = $treeService;
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
