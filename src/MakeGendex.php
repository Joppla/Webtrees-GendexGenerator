<?php

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use Exception;
// use Symfony\Polyfill\Intl\Normalizer;

class MakeGendex
{
    private TreeService $treeService;
    private string $outputDir;
    private string $gendexFilename = 'gendex.txt';
    private string $filteredNamesFilename = 'gendex_filtered_names.txt';
    private string $tmpSuffix = '.tmp';
    private string $bkSuffix = '.bk';
    private int $batchSize = 500;
    private bool $addAllNames = false;
    private bool $diacritical = false;
    private bool $chooseDateFormat = false;

    public function __construct(
        ?TreeService $treeService = null, 
        ?string $outputDir = null, 
        int $batchSize = 500,
        bool $addAllNames = false,
        bool $diacritical = false,
        bool $chooseDateFormat = false
    ) {
        $this->treeService = $treeService ?? Registry::container()->get(TreeService::class);
        $this->outputDir = rtrim($outputDir ?? Webtrees::ROOT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->batchSize = max(1, $batchSize);
        $this->addAllNames = $addAllNames;
        $this->diacritical = $diacritical;
        $this->chooseDateFormat = $chooseDateFormat;
    }

    private function log(string $message, string $fileName = 'gendex_log.txt'): void
    {
        $path = $this->outputDir . $fileName;
        $entry = "\n\n--- " . date('Y-m-d H:i:s') . " ---\n" . $message . "\n";
        $existing = file_exists($path) ? file_get_contents($path) : '';
        file_put_contents($path, $entry . $existing);
    }

    public function generateGendexHeader(): string
    {
        return ';;Generated with webtrees ' . Webtrees::VERSION . ' on ' . date('d m Y - H:i:s') . ' UTC |';
    }

    private function canShowNameForIndividual(Individual $individual): bool
    {
        return $individual->canShowName(Auth::PRIV_PRIVATE);
    }

    private function sanitizeFieldForOutput(string $value): string
    {
        return str_replace(["\r", "\n", "|"], ' ', $value);
    }

    /**
     * Controleert of een string diacritische tekens bevat
     */
    private function hasDiacriticalMarks(string $text): bool
    {
        // Vergelijk originele tekst met versie zonder diacriticals
        return $text !== $this->removeDiacriticalMarks($text);
    }
    
    /**
     * Verwijdert diacritische tekens van een string
     */
    private function removeDiacriticalMarks(string $text): string
    {
        // Gebruik iconv combining marks te verwijderen
        return iconv("UTF-8", "ASCII//TRANSLIT", $text);
    }


    /**
     * Zoekt in de ##dates tabel de eerste datum die matcht met de gegeven feiten (in volgorde).
     * facts: array of fact types, e.g. ['BIRT','BAPM','CHR']
     * returned format: "D M Y" (bijv. "5 Mar 1870") of '' als geen datum
     */
    private function getDateFromFacts(array $facts, string $xref, Tree $tree): string
    {
        // Bepaal welke velden we selecteren
        $select = $this->chooseDateFormat ? ['d_year'] : ['d_year', 'd_month', 'd_day'];
        
        foreach ($facts as $fact) {
            $row = DB::table('dates')
                ->select($select)
                ->where('d_fact', '=', $fact)
                ->where('d_gid', '=', $xref)
                ->where('d_file', '=', $tree->id())
                ->limit(1)
                ->get();
    
            if (!empty($row) && isset($row[0])) {
                return $this->formatDate($row[0]);  // <- Hier roep je de helper aan
            }
        }
    
        return '';
    }
    
    private function formatDate(object $row): string
    {
        $parts = [];
        if (!empty($row->d_day) && (int)$row->d_day > 0) {
            $parts[] = (int)$row->d_day;
        }
        if (!empty($row->d_month)) {
            $parts[] = $row->d_month;
        }
        if (!empty($row->d_year) && (int)$row->d_year > 0) {
            $parts[] = (int)$row->d_year;
        }
        return implode(' ', $parts);
    }


    /**
     * Converteer een plaats-waarde (string of Place-object of null) naar string.
     */
    private function getPlaceString($place): string
    {
        if ($place === null) {
            return '';
        }
        
        if (is_object($place) && method_exists($place, 'shortName')) {
            return (string) $place->shortName();
        }
        
        return (string) $place;
    }


    private function formatLineFromNameRow(
        Tree $tree, 
        object $nameRow, 
        Individual $individual
    ): array
    {
        $xref = (string)$nameRow->n_id;
        $given = isset($nameRow->n_givn) ? 
            trim(strip_tags((string)$nameRow->n_givn)) : '';
        $surname = isset($nameRow->n_surname) ? 
            mb_strtoupper(trim(strip_tags((string)$nameRow->n_surname)), 'UTF-8') : '';
        $fullName = trim($given . ' /' . $surname . '/');
        
        if ($fullName === '') {
            $fullName = trim(strip_tags($individual->getFullName() ?: ''));
        }
    
        // Geboortedatum: BIRT, BAPM, CHR (in volgorde)
        $birthDate = $this->getDateFromFacts(['BIRT', 'BAPM', 'CHR'], $xref, $tree);
    
        // Geboorteplaats
        $birthPlace = 
            trim(strip_tags($this->getPlaceString($individual->getBirthPlace())));
    
        // Overlijdensdatum: DEAT, BURI
        $deathDate = $this->getDateFromFacts(['DEAT', 'BURI'], $xref, $tree);
    
        // Overlijdensplaats
        $deathPlace = 
            trim(strip_tags($this->getPlaceString($individual->getDeathPlace())));
    
        // Build base reference
        $baseReference = $tree->name() . '/individual/' . $xref;
        
        // Bereken het volgnummer voor de reference
        if ($this->addAllNames || $this->diacritical) {
            $sequenceNum = '00';
            $sequenceNum = str_pad((string)$nameRow->n_num, 2, '0', STR_PAD_LEFT);
        }
    
        // Array voor de terug te geven regels
        $lines = [];
    
        // Controleer op diacriticals
        $hasDiacriticals = false;
        if($this->diacritical) {
            $hasDiacriticals = $this->hasDiacriticalMarks($fullName . $surname);
        }
    
        if ($this->diacritical && $hasDiacriticals) {
            // JA: converteer diacriticals
            // Regel 1: Originele naam met diacriticals
            $reference1 = $baseReference . '?' . $sequenceNum . '00';
            $columns1 = [
                $reference1,
                $surname,
                $fullName,
                $birthDate,
                $birthPlace,
                $deathDate,
                $deathPlace,
            ];
            $columns1 = array_map(
                fn($c) => $this->sanitizeFieldForOutput((string)$c), 
                $columns1
            );
            $lines[] = implode('|', $columns1) . '|';
    
            // Regel 2: Geconverteerde naam zonder diacriticals
            $reference2 = $baseReference . '?' . $sequenceNum . '01';
            $fullNameConverted = $this->removeDiacriticalMarks($fullName);
            $surnameConverted = $this->removeDiacriticalMarks($surname);
            $columns2 = [
                $reference2,
                $surnameConverted,
                $fullNameConverted,
                $birthDate,
                $birthPlace,
                $deathDate,
                $deathPlace,
            ];
            $columns2 = array_map(
                fn($c) => $this->sanitizeFieldForOutput((string)$c), 
                $columns2
            );
            $lines[] = implode('|', $columns2) . '|';
        } else {
            // NEE: geen conversie nodig, standaard regel
            $reference = $baseReference;
            
            if ($this->addAllNames || $this->diacritical) {
                $reference .= '?' . $sequenceNum . '00';
            }
            
            $columns = [
                $reference,
                $surname,
                $fullName,
                $birthDate,
                $birthPlace,
                $deathDate,
                $deathPlace,
            ];
            $columns = array_map(
                fn($c) => $this->sanitizeFieldForOutput((string)$c), 
                $columns
            );
            $lines[] = implode('|', $columns) . '|';
        }
    
        return $lines;
    }


    private function iterateNames(Tree $tree, callable $callback): void
    {
        $batchSize = $this->batchSize;
        $lastNId = null;

        while (true) {
            $query = DB::table('name')
                ->where('n_file', '=', $tree->id())
                ->where('n_type', '=', 'NAME')
                ->when(!$this->addAllNames, function($q) {
                    return $q->where('n_num', '=', 0);
                })
                ->orderBy('n_id')
                ->limit($batchSize);

            if ($lastNId !== null) {
                $query->where('n_id', '>', $lastNId);
            }

            $rows = $query->get();
            if (count($rows) === 0) {
                break;
            }

            foreach ($rows as $row) {
                $lastNId = $row->n_id;
                $callback($row);
            }

            if (count($rows) < $batchSize) {
                break;
            }
        }
    }

    public function generateGendexFile(array $selectedTrees, bool $addAllNames = false, bool $diacritical = false, $chooseDateFormat = false): void
    {
        // Stel de opties in als ze zijn doorgegeven
        $this->addAllNames = $addAllNames;
        $this->diacritical = $diacritical;
        $this->chooseDateFormat = $chooseDateFormat;
        
        $tmpPath = $this->outputDir . $this->gendexFilename . $this->tmpSuffix;
        $finalPath = $this->outputDir . $this->gendexFilename;
        $bkPath = $finalPath . $this->bkSuffix;

        $tmpFilteredPath = $this->outputDir . $this->filteredNamesFilename . $this->tmpSuffix;
        $finalFilteredPath = $this->outputDir . $this->filteredNamesFilename;
        $bkFilteredPath = $finalFilteredPath . $this->bkSuffix;

        $handleMain = fopen($tmpPath, 'w');
        if ($handleMain === false) {
            throw new Exception("Kan tmp bestand niet openen: {$tmpPath}");
        }
        // BOM (Byte Order Mark) toevoegen voor UTF-8
        fwrite($handleMain, "\xEF\xBB\xBF");
        
        $handleFiltered = fopen($tmpFilteredPath, 'w');
        if ($handleFiltered === false) {
            fclose($handleMain);
            throw new Exception("Kan tmp filtered bestand niet openen: {$tmpFilteredPath}");
        }
        fwrite($handleFiltered, "\xEF\xBB\xBF");
        
        fwrite($handleMain, $this->generateGendexHeader() . PHP_EOL);
        fwrite($handleFiltered, "tree|n_id|n_givn|n_surname|reason" . PHP_EOL);

        try {
            foreach ($selectedTrees as $treeId) {
                $tree = $this->treeService->find((int)$treeId);
                if (! $tree) {
                    $this->log("Boom met id {$treeId} niet gevonden, overslaan.");
                    continue;
                }
                    
                // Probeer Individual-object te maken; voorkomt M/N/andere records
                $this->iterateNames($tree, function($nameRow) use ($tree, $handleMain, $handleFiltered) {
                    $nId = (string)$nameRow->n_id;
                    
                    $individual = Registry::individualFactory()->make($nId, $tree);
                    if (!$individual) {
                        $givn = isset($nameRow->n_givn) ? 
                            $this->sanitizeFieldForOutput((string)$nameRow->n_givn) : '';
                        $surn = isset($nameRow->n_surname) ? 
                            $this->sanitizeFieldForOutput((string)$nameRow->n_surname) : '';
                        $reason = 'no_individual';
                        $filteredLine = implode('|', [$tree->name(), $nId, $givn, $surn, $reason]);
                        fwrite($handleFiltered, $filteredLine . PHP_EOL);
                        return;
                    }
                
                    if (!$this->canShowNameForIndividual($individual)) {
                        $givn = isset($nameRow->n_givn) ? 
                            $this->sanitizeFieldForOutput((string)$nameRow->n_givn) : '';
                        $surn = isset($nameRow->n_surname) ? 
                            $this->sanitizeFieldForOutput((string)$nameRow->n_surname) : '';
                        $reason = 'privacy_filtered';
                        $filteredLine = implode('|', [$tree->name(), $nId, $givn, $surn, $reason]);
                        fwrite($handleFiltered, $filteredLine . PHP_EOL);
                        return;
                    }
                
                    // formatLineFromNameRow retourneert nu een ARRAY van regels
                    $lines = $this->formatLineFromNameRow($tree, $nameRow, $individual);
                    foreach ($lines as $line) {
                        fwrite($handleMain, $line . PHP_EOL);
                    }
                });
            }

            fflush($handleMain);
            fflush($handleFiltered);
            fclose($handleMain);
            fclose($handleFiltered);
            $handleMain = $handleFiltered = null;

            // Backup & replace main file
            if (file_exists($finalPath)) {
                if (!@rename($finalPath, $bkPath)) {
                    if (!@copy($finalPath, $bkPath) || !@unlink($finalPath)) {
                        throw new Exception("Kon backup niet aanmaken van {$finalPath} naar {$bkPath}");
                    }
                }
            }
            if (!@rename($tmpPath, $finalPath)) {
                if (!@copy($tmpPath, $finalPath) || !@unlink($tmpPath)) {
                    throw new Exception("Kon gendex bestand niet verplaatsen naar {$finalPath}");
                }
            }

            // Backup & replace filtered file
            if (file_exists($finalFilteredPath)) {
                if (!@rename($finalFilteredPath, $bkFilteredPath)) {
                    if (!@copy($finalFilteredPath, $bkFilteredPath) || !@unlink($finalFilteredPath)) {
                        throw new Exception("Kon backup niet aanmaken van {$finalFilteredPath} naar {$bkFilteredPath}");
                    }
                }
            }
            if (!@rename($tmpFilteredPath, $finalFilteredPath)) {
                if (!@copy($tmpFilteredPath, $finalFilteredPath) || !@unlink($tmpFilteredPath)) {
                    throw new Exception("Kon filtered bestand niet verplaatsen naar {$finalFilteredPath}");
                }
            }

            $this->log("GENDEX en filtered bestanden succesvol gegenereerd.");
 
        } catch (Exception $e) {
            if (isset($handleMain) && is_resource($handleMain)) {
                fclose($handleMain);
            }
            if (isset($handleFiltered) && is_resource($handleFiltered)) {
                fclose($handleFiltered);
            }
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
            if (file_exists($tmpFilteredPath)) {
                @unlink($tmpFilteredPath);
            }
            $this->log("Fout tijdens generatie: " . $e->getMessage());
            throw $e;
        }
    }
}
