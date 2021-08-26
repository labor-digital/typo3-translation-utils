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


namespace LaborDigital\T3tu\ImportExport;


use LaborDigital\T3ba\Core\Di\PublicServiceInterface;
use LaborDigital\T3tu\File\Io\Reader;
use LaborDigital\T3tu\File\NoteNode;
use LaborDigital\T3tu\File\TranslationFileGroup;
use Neunerlei\PathUtil\Path;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TranslationExporter implements PublicServiceInterface
{
    /**
     * @var \LaborDigital\T3tu\File\Io\Reader
     */
    protected $reader;
    
    /**
     * @var \LaborDigital\T3tu\ImportExport\TranslationSpreadSheetWriter
     */
    protected $writer;
    
    public function __construct(Reader $reader, TranslationSpreadSheetWriter $writer)
    {
        $this->reader = $reader;
        $this->writer = $writer;
    }
    
    /**
     * Exports the translations of a single extension into csv files. One csv is created for each translation file,
     * where the columns represent the existing language variants
     *
     * @param   string  $extKey
     * @param   string  $outputFormat
     */
    public function export(string $extKey, string $outputFormat = 'csv'): void
    {
        $set = $this->reader->read($extKey);
        foreach ($set->getGroups() as $group) {
            $this->exportGroup($group, $outputFormat);
        }
    }
    
    /**
     * Exports a single translation group (source and target files) into a csv file
     *
     * @param   \LaborDigital\T3tu\File\TranslationFileGroup  $group
     * @param   string                                        $outputFormat
     *
     * @throws \LaborDigital\T3tu\ImportExport\SourceTargetMismatchException
     */
    protected function exportGroup(TranslationFileGroup $group, string $outputFormat): void
    {
        $rows = [];
        $languages = ['', $group->getSourceFile()->sourceLang];
        
        // Collect all rows on the source file
        foreach ($group->getSourceFile()->nodes as $node) {
            if ($node instanceof NoteNode) {
                $rows[$node->id] = ['@NOTE@' . $node->id, $node->note];
                continue;
            }
            $rows[$node->id] = [$node->id, $node->source];
        }
        
        // Collect the rows of the target files
        foreach ($group->getTargetFiles() as $language => $targetFile) {
            $languages[] = $language;
            
            // Check if we have all messages and don't miss out on something
            foreach ($targetFile->nodes as $node) {
                if (! isset($rows[$node->id])) {
                    throw new SourceTargetMismatchException(
                        'Missing translation unit: ' . $node->id . ' found in: '
                        . $targetFile->filename . ' in the matching source file: '
                        . $group->getSourceFile()->filename);
                }
            }
            
            foreach ($group->getSourceFile()->nodes as $node) {
                if ($node instanceof NoteNode) {
                    $rows[$node->id][] = '';
                    continue;
                }
                
                $value = $targetFile->nodes[$node->id]->target ?? '';
                
                if (stripos($value, '<![CDATA[') !== false) {
                    $value = trim(preg_replace('~<!\[CDATA\[(.*?)]]>~', '$1', $value));
                }
                
                if (stripos($value, 'COPY FROM: ') === 0) {
                    $value = '';
                }
                
                $rows[$node->id][] = $value;
            }
        }
        
        // Prepare the file meta data
        $file = GeneralUtility::makeInstance(TranslationSpreadSheetFile::class);
        $sourceFileName = $group->getSourceFile()->filename;
        $file->filename = Path::join(dirname($sourceFileName), basename($sourceFileName, '.xlf')) . '.' . $outputFormat;
        $file->rows = array_merge([$languages], $rows);
        
        $this->writer->write($file);
    }
    
}
