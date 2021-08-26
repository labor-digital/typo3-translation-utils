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


namespace LaborDigital\T3tu\ImportExport;


use LaborDigital\T3ba\Core\Di\PublicServiceInterface;
use LaborDigital\T3tu\File\Io\ConstraintApplier;
use LaborDigital\T3tu\File\Io\GroupReader;
use LaborDigital\T3tu\File\Io\Writer;
use LaborDigital\T3tu\File\NoteNode;
use LaborDigital\T3tu\File\TranslationFile;
use LaborDigital\T3tu\File\TranslationFileGroup;
use LaborDigital\T3tu\File\TransUnitNode;
use LaborDigital\T3tu\Util\TranslationUtilTrait;
use Neunerlei\FileSystem\Fs;
use Neunerlei\PathUtil\Path;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationImporter implements PublicServiceInterface
{
    use TranslationUtilTrait;
    
    /**
     * @var \LaborDigital\T3tu\ImportExport\TranslationSpreadSheetReader
     */
    protected $reader;
    
    /**
     * @var \LaborDigital\T3tu\ImportExport\TranslationSpreadSheetWriter
     */
    protected $writer;
    
    /**
     * @var \LaborDigital\T3tu\File\Io\GroupReader
     */
    protected $groupReader;
    
    /**
     * @var \LaborDigital\T3tu\File\Io\Writer
     */
    protected $fileWriter;
    
    /**
     * @var \LaborDigital\T3tu\File\Io\ConstraintApplier
     */
    protected $constraintApplier;
    
    public function __construct(
        TranslationSpreadSheetReader $reader,
        TranslationSpreadSheetWriter $writer,
        GroupReader $groupReader,
        Writer $fileWriter,
        ConstraintApplier $constraintApplier
    )
    {
        $this->reader = $reader;
        $this->writer = $writer;
        $this->groupReader = $groupReader;
        $this->fileWriter = $fileWriter;
        $this->constraintApplier = $constraintApplier;
    }
    
    /**
     * Imports all .csv files that are located in the language directory of the given extension into the .xlf files
     *
     * @param   string  $extKey  The ext key of the extension you want to sync the translations for
     */
    public function import(string $extKey): void
    {
        // Load the files
        $files = $this->findImportFiles($extKey);
        
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
    protected function findImportFiles(string $extKey): array
    {
        $directory = $this->getSetDirectory($extKey);
        $iterator = Fs::getDirectoryIterator($directory, false, ['regex' => '~\\.(xls|xlsx|ods|csv)$~']);
        $files = [];
        foreach ($iterator as $file) {
            $files[] = $this->reader->readFile($file->getPathname());
        }
        
        return $files;
    }
    
    /**
     * Handles the import of the file-group combined in a single .csv file
     *
     * @param   string                      $extKey   The extension key to import the file to
     * @param   TranslationSpreadSheetFile  $csvFile  The file to import
     */
    protected function importSingleCsvFile(string $extKey, TranslationSpreadSheetFile $csvFile): void
    {
        // Check if we have this file in our set
        $basename = basename($csvFile->filename, '.csv');
        $languages = reset($csvFile->rows);
        array_shift($languages);
        $group = $this->initializeGroup($languages, $extKey, $csvFile, $basename);
        
        // Ignore the source language
        $sourceLanguageKey = reset($languages);
        
        $offset = 0;
        foreach ($languages as $language) {
            if (! $this->constraintApplier->isFileAllowed($extKey, $basename, $language)) {
                continue;
            }
            
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
            $isNote = false;
            $sourceValue = null;
            if (stripos($id, '@NOTE@') === 0) {
                $isNote = true;
                $id = substr($id, 6);
                $value = $row[1];
            } else {
                $sourceValue = $row[1];
                $value = $row[$offset];
            }
            
            // Inherit from source if the value is empty
            if (empty($value)) {
                if (! empty($sourceValue)) {
                    $value = 'COPY FROM: ' . $sourceLanguageKey . ' - ' . $sourceValue;
                } else {
                    continue;
                }
            }
            
            if (isset($file->nodes[$id])) {
                $file->nodes[$id]->source = $sourceValue;
                if (! $isSourceFile) {
                    $file->nodes[$id]->target = $value;
                }
            } else {
                if ($isNote) {
                    $node = GeneralUtility::makeInstance(NoteNode::class);
                    $node->note = $value;
                } else {
                    $node = GeneralUtility::makeInstance(TransUnitNode::class);
                    $node->source = $sourceValue;
                    if (! $isSourceFile) {
                        $node->target = $value;
                    }
                }
                
                $node->id = $id;
                $file->nodes[$id] = $node;
            }
        }
        
        $this->fileWriter->writeFile($file);
    }
    
    /**
     * Used to create the instance of a translation file group
     *
     * @param   array                       $languages  The list of language keys we import (source language is first)
     * @param   string                      $extKey     The extension key we import the translations for
     * @param   TranslationSpreadSheetFile  $csvFile    The reference of the csv file object we create this group fore
     * @param   string                      $basename   The csv file basename to inherit the filename for
     *
     * @return \LaborDigital\T3tu\File\TranslationFileGroup
     */
    protected function initializeGroup(array $languages, string $extKey, TranslationSpreadSheetFile $csvFile, string $basename): TranslationFileGroup
    {
        $sourceLanguageKey = array_shift($languages);
        $sourceFile = Path::join(dirname($csvFile->filename), $basename . '.xlf');
        
        $group = $this->groupReader->readSingleGroup($extKey, $sourceFile, $sourceLanguageKey);
        
        foreach ($languages as $language) {
            if ($this->constraintApplier->isFileAllowed($extKey, $sourceFile, $language)) {
                $group->addTargetFile($language);
            }
        }
        
        return $group;
    }
}
