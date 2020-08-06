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
 * Last modified: 2020.07.23 at 09:53
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use LaborDigital\T3TU\File\TranslationFile;
use LaborDigital\T3TU\File\TranslationFileGroup;
use LaborDigital\T3TU\File\TranslationFileUnit;
use LaborDigital\T3TU\Util\TranslationUtilTrait;
use Neunerlei\FileSystem\Fs;
use Neunerlei\PathUtil\Path;

class TranslationImporter
{
    use TranslationUtilTrait;
    
    /**
     * Imports all .csv files that are located in the language directory of the given extension into the .xlf files
     *
     * @param   string  $extKey  The ext key of the extension you want to sync the translations for
     */
    public function import(string $extKey): void
    {
        // Load the files
        $files = $this->findCsvFiles($extKey);
        
        // Skip if the files are empty
        if (empty($files)) {
            return;
        }
        
        // Import the files in order
        foreach ($files as $file) {
            $this->importSingleCsvFile($extKey, $file);
        }
    }
    
    /**
     * Returns a list of csv files in the language directory of the given extension
     *
     * @param   string  $extKey
     *
     * @return array
     */
    protected function findCsvFiles(string $extKey): array
    {
        $directory = $this->getSetDirectory($extKey);
        $iterator  = Fs::getDirectoryIterator($directory, false, ['regex' => '~\\.csv$~']);
        $files     = [];
        foreach ($iterator as $file) {
            $csvFile           = $this->Container()->getWithoutDi(TranslationCsvFile::class);
            $files[]           = $csvFile;
            $csvFile->filename = $file->getPathname();
            $csvFile->read();
        }
        
        return $files;
    }
    
    /**
     * Handles the import of the file-group combined in a single .csv file
     *
     * @param   string              $extKey   The extension key to import the file to
     * @param   TranslationCsvFile  $csvFile  The file to import
     */
    protected function importSingleCsvFile(string $extKey, TranslationCsvFile $csvFile): void
    {
        // Check if we have this file in our set
        $basename  = basename($csvFile->filename, '.csv');
        $languages = reset($csvFile->rows);
        array_shift($languages);
        $group = $this->initializeGroup($languages, $extKey, $csvFile, $basename);
        
        // Ignore the source language
        $sourceLanguageKey = reset($languages);
        
        $offset = 0;
        foreach ($languages as $language) {
            $file = $sourceLanguageKey === $language ? $group->getSourceFile() : $group->getTargetFiles()[$language];
            $this->importSingleLanguage(++$offset, $file, $csvFile->rows, $sourceLanguageKey);
        }
    }
    
    /**
     * Imports a single language column into its matching translation file
     *
     * @param   int              $offset             The column offset in the csv rows to import
     * @param   TranslationFile  $file               The file to import the csv data to
     * @param   array            $rows               The csv rows to import
     * @param   string           $sourceLanguageKey  The source language key for the csv file we import
     */
    protected function importSingleLanguage(int $offset, TranslationFile $file, array $rows, string $sourceLanguageKey): void
    {
        $isSourceFile = $file->targetLang === null;
        foreach ($rows as $row) {
            $id = $row[0];
            if (empty($id)) {
                continue;
            }
            
            // Check if we have to handle a note
            $isNote      = false;
            $sourceValue = null;
            if (stripos($id, '@NOTE@') === 0) {
                $isNote = true;
                $id     = substr($id, 6);
                $value  = $row[1];
            } else {
                $sourceValue = $row[1];
                $value       = $row[$offset];
            }
            
            // Inherit from source if the value is empty
            if (empty($value)) {
                if (! empty($sourceValue)) {
                    $value = 'COPY FROM: ' . $sourceLanguageKey . ' - ' . $sourceValue;
                } else {
                    continue;
                }
            }
            
            if (isset($file->units[$id])) {
                // Update the unit
                if ($isSourceFile) {
                    $file->units[$id]->source = $sourceValue;
                } else {
                    $file->units[$id]->source = $sourceValue;
                    $file->units[$id]->target = $value;
                }
            } else {
                // Create the unit
                $unit         = $this->Container()->getWithoutDi(TranslationFileUnit::class);
                $unit->id     = $id;
                $unit->isNote = $isNote;
                if ($isNote) {
                    $unit->note = $value;
                } elseif ($isSourceFile) {
                    $unit->source = $sourceValue;
                } else {
                    $unit->source = $sourceValue;
                    $unit->target = $value;
                }
                $file->units[$id] = $unit;
            }
        }
        
        // Persist the file
        $file->write();
    }
    
    /**
     * Used to create the instance of a translation file group
     *
     * @param   array               $languages  The list of language keys we import (source language is first)
     * @param   string              $extKey     The extension key we import the translations for
     * @param   TranslationCsvFile  $csvFile    The reference of the csv file object we create this group fore
     * @param   string              $basename   The csv file basename to inherit the filename for
     *
     * @return \LaborDigital\T3TU\File\TranslationFileGroup
     */
    protected function initializeGroup(array $languages, string $extKey, TranslationCsvFile $csvFile, string $basename): TranslationFileGroup
    {
        // Compute the source file
        $sourceLanguageKey = array_shift($languages);
        $sourceFile        = Path::join(dirname($csvFile->filename), $basename . '.xlf');
        
        // Compute the translation files
        $targetFiles = [];
        foreach ($languages as $language) {
            $targetFiles[$language] = Path::join(dirname($csvFile->filename), $language . '.' . $basename . '.xlf');
        }
        
        return $this->Container()->getWithoutDi(TranslationFileGroup::class, [$extKey, $sourceFile, $targetFiles, $sourceLanguageKey]);
    }
}
