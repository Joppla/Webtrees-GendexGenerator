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

class MakeGendex
{
    private TreeService $treeService;
    private string $outputDir;
    private string $gendexFilename = 'gendex.txt';
    private string $filteredNamesFilename = 'gendex_filtered_names.txt';
    private string $tmpSuffix = '.tmp';
    private string $bkSuffix = '.bk';
    private int $batchSize = 500;

    public function __construct(?TreeService $treeService = null, ?string $outputDir = null, int $batchSize = 500)
    {
        $this->treeService = $treeService ?? Registry::container()->get(TreeService::class);
        $this->outputDir = rtrim($outputDir ?? Webtrees::ROOT_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->batchSize = max(1, $batchSize);
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

    /**
     * Zoekt in de ##dates tabel de eerste datum die matcht met de gegeven feiten (in volgorde).
     * facts: array of fact types, e.g. ['BIRT','BAPM','CHR']
     * returned format: "D M Y" (bijv. "5 Mar 1870") of '' als geen datum
     */
    private function getDateFromFacts(array $facts, string $xref, Tree $tree): string
    {
        foreach ($facts as $fact) {
            // Gebruik DB::table met prefix; de dates-tabel in webtrees is 'dates' met prefix handling
            $row = DB::table('dates')
                ->select(['d_year', 'd_month', 'd_day'])
                ->where('d_fact', '=', $fact)
                ->where('d_gid', '=', $xref)
                ->where('d_file', '=', $tree->id())
                ->limit(1)
                ->get();

            if (!empty($row) && isset($row[0])) {
                $r = $row[0];
                $day = (!empty($r->d_day) && (int)$r->d_day > 0) ? ((int)$r->d_day . ' ') : '';
                $month = !empty($r->d_month) ? ($r->d_month . ' ') : '';
                $year = (!empty($r->d_year) && (int)$r->d_year > 0) ? (string)((int)$r->d_year) : '';
                $date = trim($day . $month . $year);
                return $date;
            }
        }

        return '';
    }

    /**
     * Converteer een plaats-waarde (string of Place-object of null) naar string.
     */
    private function getPlaceString($place): string
    {
        if ($place === null) {
            return '';
        }
    
        // Als het een object is met __toString of shortName/display methodes
        if (is_object($place)) {
            // Fisharebest\Webtrees\Place heeft waarschijnlijk shortName() of __toString()
            if (method_exists($place, 'shortName')) {
                return (string) $place->shortName();
            }
            if (method_exists($place, 'display')) {
                return (string) $place->display();
            }
            if (method_exists($place, '__toString')) {
                return (string) $place;
            }
            // Fallback: probeer json_encode of get_class
            return (string) get_class($place);
        }
    
        // Anders assume stringable
        return (string) $place;
    }

    private function formatLineFromNameRow(Tree $tree, object $nameRow, Individual $individual): string
    {
        $xref = (string)$nameRow->n_id;
        $reference = $tree->name() . '/individual/' . $xref;

        $given = isset($nameRow->n_givn) ? trim(strip_tags((string)$nameRow->n_givn)) : '';
        $surname = isset($nameRow->n_surname) ? strtoupper(trim(strip_tags((string)$nameRow->n_surname))) : '';
        $fullName = trim($given . ' /' . $surname . '/');
        if ($fullName === '') {
            $fullName = trim(strip_tags($individual->getFullName() ?: ''));
        }

        // Geboortedatum: BIRT, BAPM, CHR (in volgorde)
        $birthDate = $this->getDateFromFacts(['BIRT', 'BAPM', 'CHR'], $xref, $tree);

        // Geboorteplaats: gebruik individual's getBirthPlace() indien beschikbaar; fallback leeg
        $birthPlace = trim(strip_tags($this->getPlaceString($individual->getBirthPlace())));


        // Overlijdensdatum: DEAT, BURI
        $deathDate = $this->getDateFromFacts(['DEAT', 'BURI'], $xref, $tree);

        $deathPlace = trim(strip_tags($this->getPlaceString($individual->getDeathPlace())));


        $columns = [
            $reference,
            $surname,
            $fullName,
            $birthDate,
            $birthPlace,
            $deathDate,
            $deathPlace,
        ];

        $columns = array_map(fn($c) => str_replace(["\r", "\n", '|'], ' ', (string)$c), $columns);

        return implode('|', $columns) . '|';
    }

    private function iterateNames(Tree $tree, callable $callback): void
    {
        $batchSize = $this->batchSize;
        $lastNId = null;

        while (true) {
            $query = DB::table('name')
                ->where('n_file', '=', $tree->id())
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

    public function generateGendexFile(array $selectedTrees): void
    {
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
        $handleFiltered = fopen($tmpFilteredPath, 'w');
        if ($handleFiltered === false) {
            fclose($handleMain);
            throw new Exception("Kan tmp filtered bestand niet openen: {$tmpFilteredPath}");
        }

        fwrite($handleMain, $this->generateGendexHeader() . PHP_EOL);
        fwrite($handleFiltered, "tree|n_id|n_givn|n_surname|reason" . PHP_EOL);

        try {
            foreach ($selectedTrees as $treeId) {
                $tree = $this->treeService->find((int)$treeId);
                if (! $tree) {
                    $this->log("Boom met id {$treeId} niet gevonden, overslaan.");
                    continue;
                }

                $this->iterateNames($tree, function($nameRow) use ($tree, $handleMain, $handleFiltered) {
                    $nId = (string)$nameRow->n_id;

                    // Probeer Individual-object te maken; voorkomt M/N/andere records
                    $individual = Registry::individualFactory()->make($nId, $tree);
                    if (! $individual) {
                        $givn = isset($nameRow->n_givn) ? str_replace(["\r","\n","|"], ' ', (string)$nameRow->n_givn) : '';
                        $surn = isset($nameRow->n_surname) ? str_replace(["\r","\n","|"], ' ', (string)$nameRow->n_surname) : '';
                        $reason = 'no_individual';
                        $filteredLine = implode('|', [$tree->name(), $nId, $givn, $surn, $reason]);
                        fwrite($handleFiltered, $filteredLine . PHP_EOL);
                        return;
                    }

                    if (! $this->canShowNameForIndividual($individual)) {
                        $givn = isset($nameRow->n_givn) ? str_replace(["\r","\n","|"], ' ', (string)$nameRow->n_givn) : '';
                        $surn = isset($nameRow->n_surname) ? str_replace(["\r","\n","|"], ' ', (string)$nameRow->n_surname) : '';
                        $reason = 'privacy_filtered';
                        $filteredLine = implode('|', [$tree->name(), $nId, $givn, $surn, $reason]);
                        fwrite($handleFiltered, $filteredLine . PHP_EOL);
                        return;
                    }

                    $line = $this->formatLineFromNameRow($tree, $nameRow, $individual);
                    fwrite($handleMain, $line . PHP_EOL);
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
