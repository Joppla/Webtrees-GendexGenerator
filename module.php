<?php
/*
 * Gendex Generator - A Webtrees module to generate gendex.txt files.
 * Copyright (C) 2026 Joppla
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

declare(strict_types=1);

namespace Joppla\Modules\GendexGenerator;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\I18N;

class GendexGeneratorModule extends AbstractModule implements ModuleCustomInterface
{
    use ModuleCustomTrait;

    // Titel van de module (vertaling voor NL/EN)
    public function title(): string
    {
        return I18N::translate('Gendex Generator');
    }

    // Beschrijving (vertaling voor NL/EN)
    public function description(): string
    {
        return I18N::translate('Generates a gendex.txt file for your family tree.');
    }

    // Auteurnaam
    public function customModuleAuthorName(): string
    {
        return 'Joppla';
    }

    // Link naar de GitHub-pagina voor ondersteuning
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/Joppla/Webtrees-GendexGenerator';
    }
    // Moduleversie
    public function customModuleVersion(): string
    {
        return '2.2.5-alpha';
    }

    // Vertalingen laden (voor NL/EN)
    public function customTranslations(string $language): array
    {
        $translations = [
            // Engels (standaard)
            'Gendex Generator' => 'Gendex Generator',
            'Generates a gendex.txt file for your family tree.' => 'Generates a gendex.txt file for your family tree.',

            // Nederlands
            'nl' => [
                'Gendex Generator' => 'Gendex Generator',
                'Generates a gendex.txt file for your family tree.' => 'Genereert een gendex.txt-bestand voor je stamboom.',
            ],
        ];

        return $translations[$language] ?? $translations['en'];
    }
}

// Retourneer een instantie van de module
return new GendexGeneratorModule();