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
 * Last modified: 2020.10.07 at 13:32
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationSpreadSheetWriter
{
    /**
     * Writes the given translation file to the disc.
     *
     * @param   \LaborDigital\T3TU\ImportExport\TranslationSpreadSheetFile  $file
     */
    public function write(TranslationSpreadSheetFile $file): void
    {
        $spreadSheet = $this->makeSpreadSheet($file->rows);
        $writer      = $this->makeConcreteWriter($spreadSheet, $file->filename);
        $writer->save($file->filename);
    }

    /**
     * Converts the given, multi-dimensional row array into a spread sheet instance
     *
     * @param   array  $rows
     *
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    protected function makeSpreadSheet(array $rows): Spreadsheet
    {
        $spreadSheet = GeneralUtility::makeInstance(Spreadsheet::class);
        $spreadSheet->getDefaultStyle()->setQuotePrefix(true);
        $spreadSheet->getActiveSheet()->fromArray($rows);

        return $spreadSheet;
    }

    /**
     * Creates the concrete spread sheet writer instance based on the file extension
     *
     * @param   \PhpOffice\PhpSpreadsheet\Spreadsheet  $sheet
     * @param   string                                 $filename
     *
     * @return \PhpOffice\PhpSpreadsheet\Writer\IWriter
     */
    protected function makeConcreteWriter(Spreadsheet $sheet, string $filename): IWriter
    {
        return IOFactory::createWriter($sheet, ucfirst(pathinfo($filename, PATHINFO_EXTENSION)));
    }
}
