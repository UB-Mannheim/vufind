<?php

/**
 * GVIDefault record driver unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\RecordDriver;

use VuFind\RecordDriver\GVIDefault;

/**
 * GVIDefault record driver unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GVIDefaultTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Create a GVIDefault record driver with the given raw data.
     *
     * @param array $data Raw Solr data
     *
     * @return GVIDefault
     */
    protected function createRecord(array $data): GVIDefault
    {
        $record = new GVIDefault();
        $record->setRawData($data);
        return $record;
    }

    /**
     * Test that GVI material content types are mapped to standard format
     * names.
     *
     * @return void
     */
    public function testGetFormats(): void
    {
        $tests = [
            'Book' => ['Book'],
            'Large Print' => ['Book'],
            'EBook' => ['eBook'],
            'EJournal' => ['Journal'],
            'Journal/Magazine' => ['Journal'],
            'Journal/Magazine|Looseleaf' => ['Journal'],
            'Journal/Magazine|Newspaper' => ['Newspaper'],
            'Journal/Magazine|Periodical' => ['Serial'],
            'Newspaper' => ['Newspaper'],
            'Map' => ['Map'],
            'Map|Atlas' => ['Map'],
            'Map|Globe' => ['Globe'],
            'Map|Globe|Physical Object' => ['Globe'],
            'Manuscript/Archive' => ['Text'],
            'Musical Score' => ['Musical Score'],
            'Sound Recording' => ['Sound Recording'],
            'Streaming Audio' => ['Sound Recording'],
            'CD' => ['Sound Recording'],
            'Video' => ['Video'],
            'Film' => ['Video'],
            'DVD' => ['Video'],
            'Thesis/Dissertation' => ['Dissertation'],
            'Government Document' => ['Government Document'],
            'Microform' => ['Microfilm'],
            'Braille' => ['Braille'],
            'Kit' => ['Kit'],
            'Equipment' => ['Physical Object'],
            'Computer Resource' => ['Software'],
            'Online|Computer Resource' => ['Electronic'],
            'Visual Materials' => ['Photo'],
        ];
        foreach ($tests as $gviFormat => $expected) {
            $record = $this->createRecord(['material_content_type' => [$gviFormat]]);
            $this->assertSame($expected, $record->getFormats(), $gviFormat);
        }
    }

    /**
     * Test that unmapped GVI content types yield an empty format list.
     *
     * @return void
     */
    public function testGetFormatsUnknown(): void
    {
        $record = $this->createRecord(['material_content_type' => ['Unknown']]);
        $this->assertSame([], $record->getFormats());
    }

    /**
     * Test that records without a content type yield an empty format list.
     *
     * @return void
     */
    public function testGetFormatsMissing(): void
    {
        $record = $this->createRecord([]);
        $this->assertSame([], $record->getFormats());
    }

    /**
     * Test that string (non-array) field values and duplicates are handled.
     *
     * @return void
     */
    public function testGetFormatsStringAndDuplicates(): void
    {
        $record = $this->createRecord(
            ['material_content_type' => 'Journal/Magazine|Periodical']
        );
        $this->assertSame(['Serial'], $record->getFormats());

        $record = $this->createRecord(
            ['material_content_type' => ['Book', 'Book', 'EBook']]
        );
        $this->assertSame(['Book', 'eBook'], $record->getFormats());
    }
}
