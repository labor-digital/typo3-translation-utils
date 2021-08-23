<?php
/*
 * Copyright 2021 LABOR.digital
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
 * Last modified: 2021.08.23 at 10:38
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\File\Io;


use LaborDigital\T3tu\File\InvalidXmlFileException;
use LaborDigital\T3tu\File\NoteNode;
use LaborDigital\T3tu\File\TranslationFile;
use LaborDigital\T3tu\File\TransUnitNode;
use Neunerlei\Arrays\ArrayGeneratorException;
use Neunerlei\Arrays\Arrays;
use Neunerlei\FileSystem\Fs;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileReader
{
    /**
     * Creates a new, empty file instance based on the given parameters
     *
     * @param   string       $filename
     * @param   string       $productName
     * @param   string|null  $language
     * @param   string       $sourceLanguage
     *
     * @return \LaborDigital\T3tu\File\TranslationFile
     */
    public static function makeEmptyFile(string $filename, string $productName, ?string $language, string $sourceLanguage): TranslationFile
    {
        return GeneralUtility::makeInstance(TranslationFile::class, $filename, $productName, $language, $sourceLanguage, [], []);
    }
    
    /**
     * Reads a single .xlf file into its object representation
     *
     * @param   string       $filename
     * @param   string       $productName
     * @param   string|null  $language
     * @param   string       $sourceLanguage
     *
     * @return \LaborDigital\T3tu\File\TranslationFile
     */
    public function read(string $filename, string $productName, ?string $language, string $sourceLanguage): TranslationFile
    {
        $file = static::makeEmptyFile($filename, $productName, $language, $sourceLanguage);
        if (! file_exists($filename)) {
            return $file;
        }
        
        $content = $this->readContents($filename);
        $this->readMetaFromContent($content, $file);
        $this->readNodes($content, $file);
        
        return $file;
    }
    
    /**
     * Reads a single .xlf file and parses its content into an array
     *
     * @param   string  $filename
     *
     * @return array
     * @throws \LaborDigital\T3tu\File\InvalidXmlFileException
     */
    protected function readContents(string $filename): array
    {
        $content = Fs::readFile($filename);
        try {
            return Arrays::makeFromXml($content);
        } catch (ArrayGeneratorException $exception) {
            throw new InvalidXmlFileException(
                'Failed to parse the translation file: ' . $filename,
                $exception->getCode(),
                $exception
            );
        }
    }
    
    /**
     * Extracts meta information from the file content into the object representation
     *
     * @param   array                                    $content
     * @param   \LaborDigital\T3tu\File\TranslationFile  $file
     */
    protected function readMetaFromContent(array $content, TranslationFile $file): void
    {
        foreach (Arrays::getPath($content, '0.0.*', []) as $k => $row) {
            if (is_string($k) && strpos($k, '@') === 0) {
                $file->params[$k] = $row;
            }
        }
        
        $file->sourceLang = Arrays::getPath($content, '0.0.@source-language', 'en');
        $file->productName = Arrays::getPath($content, '0.0.@product-name', $file->productName);
        $file->targetLang = Arrays::getPath($content, '0.0.@target-language', $file->targetLang);
    }
    
    /**
     * Iterates the raw content rows and converts them into node objects attached to the translation file
     *
     * @param   array                                    $content
     * @param   \LaborDigital\T3tu\File\TranslationFile  $file
     */
    protected function readNodes(array $content, TranslationFile $file): void
    {
        foreach (Arrays::getPath($content, '0.0.*', []) as $entry) {
            if (! isset($entry['tag']) || $entry['tag'] !== 'body') {
                continue;
            }
            
            foreach ($entry as $k => $row) {
                if ($k === 'tag' || is_string($k) || ! isset($row['@id'])) {
                    continue;
                }
                
                $hasError = false;
                
                if ($row['tag'] === 'note') {
                    $unit = GeneralUtility::makeInstance(NoteNode::class);
                    $this->readNote($unit, $row);
                } elseif ($row['tag'] === 'trans-unit') {
                    $unit = GeneralUtility::makeInstance(TransUnitNode::class);
                    $this->readTransUnit($unit, $row, $hasError);
                }
                
                if (! $hasError) {
                    $unit->id = $row['@id'];
                    $file->nodes[$unit->id] = $unit;
                }
            }
        }
    }
    
    /**
     * Hydrates the given unit file with the data of a "note" node
     *
     * @param   \LaborDigital\T3tu\File\NoteNode  $node
     * @param   array                             $row
     */
    protected function readNote(NoteNode $node, array $row): void
    {
        $node->note = $this->unifyLinebreaks($row['content']);
    }
    
    /**
     * Hydrates the given unit file with the data of a "trans-unit" node
     *
     * @param   \LaborDigital\T3tu\File\TransUnitNode  $node
     * @param   array                                  $row
     * @param   bool                                   $hasError
     */
    protected function readTransUnit(TransUnitNode $node, array $row, bool &$hasError): void
    {
        foreach ($row as $_k => $child) {
            if (is_string($_k) || ! is_array($child)) {
                continue;
            }
            
            switch (Arrays::getPath($child, 'tag')) {
                case 'source':
                    $node->source = $this->unifyLinebreaks($child['content']);
                    break;
                case 'target':
                    $node->target = $this->unifyLinebreaks($child['content']);
                    break;
                default:
                    $hasError = true;
                    break;
            }
        }
    }
    
    /**
     * Helper to unify the linebreak inside a single line across multiple os's
     *
     * @param   string  $line
     *
     * @return string
     */
    protected function unifyLinebreaks(string $line): string
    {
        return implode(PHP_EOL, array_filter(array_map('trim', explode(PHP_EOL,
            str_replace(["\t", "\r\n", PHP_EOL], PHP_EOL, $line)
        ))));
    }
}