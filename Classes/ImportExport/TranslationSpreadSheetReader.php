<?php
/*
 * Copyright 2020 LABOR.digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2020.10.07 at 13:02
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\ImportExport;


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationSpreadSheetReader
{
    /**
     * Reads any kind of spread sheet (.xls, .xlsx, .ods or .csv) into a TranslationSpreadSheetFile representation
     *
     * @param   string  $filename
     *
     * @return \LaborDigital\T3tu\ImportExport\TranslationSpreadSheetFile
     */
    public function readFile(string $filename): TranslationSpreadSheetFile
    {
        $reader = $this->getConcreteReader(pathinfo($filename, PATHINFO_EXTENSION));
        $rows   = $this->readRows($reader->load($filename));

        return $this->makeCsvFile($filename, $rows);
    }

    /**
     * Creates the concrete reader instance based on the files extension
     *
     * @param   string  $extension
     *
     * @return \PhpOffice\PhpSpreadsheet\Reader\IReader
     */
    protected function getConcreteReader(string $extension): IReader
    {
        return IOFactory::createReader(ucfirst($extension));
    }

    /**
     * Uses the spread sheet imported by the reader to extract the contents as an array
     *
     * @param   \PhpOffice\PhpSpreadsheet\Spreadsheet  $spreadsheet
     *
     * @return array
     */
    protected function readRows(Spreadsheet $spreadsheet): array
    {
        $workSheet = $spreadsheet->getActiveSheet();
        $rows      = [];
        foreach ($workSheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $r = [];
            foreach ($cellIterator as $cell) {
                $r[] = (string)$cell->getValue();
            }
            if (empty(array_filter($r))) {
                continue;
            }
            $rows[] = $r;
        }

        return $rows;
    }

    /**
     * Factory to create a new "csv" file, from any input source that is supported by the spread sheet implementation
     *
     * @param   string  $filename
     * @param   array   $rows
     *
     * @return \LaborDigital\T3tu\ImportExport\TranslationSpreadSheetFile
     */
    protected function makeCsvFile(string $filename, array $rows): TranslationSpreadSheetFile
    {
        $file           = GeneralUtility::makeInstance(TranslationSpreadSheetFile::class);
        $file->filename = preg_replace('~\.[^.]*?$~', '.csv', $filename);
        $file->rows     = $rows;

        return $file;
    }
}
