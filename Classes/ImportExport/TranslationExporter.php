<?php
/**
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
 * Last modified: 2020.07.22 at 17:37
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use LaborDigital\T3TU\File\TranslationFileGroup;
use LaborDigital\T3TU\Util\TranslationUtilTrait;
use Neunerlei\PathUtil\Path;

class TranslationExporter
{
    use TranslationUtilTrait;

    /**
     * Exports the translations of a single extension into csv files. One csv is created for each translation file,
     * where the columns represent the existing language variants
     *
     * @param   string  $extKey
     * @param   string  $outputFormat
     */
    public function export(string $extKey, string $outputFormat = 'csv'): void
    {
        // Handle all sets in order
        $set = $this->getSet($extKey);
        foreach ($set->getGroups() as $group) {
            $this->exportGroup($group, $outputFormat);
        }
    }

    /**
     * Exports a single translation group (source and target files) into a csv file
     *
     * @param   \LaborDigital\T3TU\File\TranslationFileGroup  $group
     *
     * @throws \LaborDigital\T3TU\ImportExport\SourceTargetMismatchException
     */
    protected function exportGroup(TranslationFileGroup $group, string $outputFormat): void
    {
        $rows      = [];
        $languages = ['', $group->getSourceFile()->sourceLang];

        // Collect all rows on the source file
        foreach ($group->getSourceFile()->units as $unit) {
            if ($unit->isNote) {
                $rows[$unit->id] = ['@NOTE@' . $unit->id, $unit->note];
                continue;
            }
            $rows[$unit->id] = [$unit->id, $unit->source];
        }

        // Collect the rows of the target files
        foreach ($group->getTargetFiles() as $language => $targetFile) {
            $languages[] = $language;

            // Check if we have all messages and don't miss out on something
            foreach ($targetFile->units as $unit) {
                if (! isset($rows[$unit->id])) {
                    throw new SourceTargetMismatchException(
                        'Missing translation unit: ' . $unit->id . ' found in: '
                        . $targetFile->filename . ' in the matching source file: '
                        . $group->getSourceFile()->filename);
                }
            }
            // Iterate the source messages and find the counterparts
            foreach ($group->getSourceFile()->units as $unit) {
                $value = $targetFile->units[$unit->id]->target ?? '';

                if ($unit->isNote) {
                    $rows[$unit->id][] = '';
                    continue;
                }

                if (stripos($value, '<![CDATA[') !== false) {
                    $value = trim(preg_replace('~<!\[CDATA\[(.*?)]]>~', '$1', $value));
                }

                if (stripos($value, 'COPY FROM: ') === 0) {
                    $value = '';
                }

                $rows[$unit->id][] = $value;
            }
        }

        // Prepare the file meta data
        $file           = $this->Container()->getWithoutDi(TranslationSpreadSheetFile::class);
        $sourceFileName = $group->getSourceFile()->filename;
        $file->filename = Path::join(dirname($sourceFileName), basename($sourceFileName, '.xlf')) . '.' . $outputFormat;
        $file->rows     = array_merge([$languages], $rows);

        $writer = $this->Container()->getWithoutDi(TranslationSpreadSheetWriter::class);
        $writer->write($file);
    }

}
