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
 * Last modified: 2021.08.23 at 10:33
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\File\Io;


use LaborDigital\T3tu\File\TranslationFile;
use LaborDigital\T3tu\File\TranslationFileGroup;
use Neunerlei\FileSystem\Fs;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GroupReader
{
    /**
     * @var \LaborDigital\T3tu\File\Io\FileReader
     */
    protected $fileReader;
    
    /**
     * @var \LaborDigital\T3tu\File\Io\ConstraintApplier
     */
    protected $constraintApplier;
    
    public function __construct(FileReader $fileReader, ConstraintApplier $constraintApplier)
    {
        $this->fileReader = $fileReader;
        $this->constraintApplier = $constraintApplier;
    }
    
    public function read(string $productName, string $directory, ?string $fallbackLanguage = null): array
    {
        return $this->createGroupInstances(
            $productName,
            $fallbackLanguage,
            $this->findGroups($directory)
        );
    }
    
    public function readSingleGroup(string $productName, string $sourceFilePath, ?string $fallbackLanguage = null): TranslationFileGroup
    {
        $groups = $this->findGroups(dirname($sourceFilePath));
        $basename = basename($sourceFilePath, '.xlf');
        
        if (isset($groups[$basename])) {
            return $this->createSingleGroupInstance(
                $groups[$basename]['source'], $groups[$basename]['target'], $productName, $fallbackLanguage);
        }
        
        
        return $this->createSingleGroupInstance(
            $sourceFilePath, [], $productName, $fallbackLanguage);
    }
    
    protected function findGroups(string $directory): array
    {
        $groups = [];
        foreach (Fs::getDirectoryIterator($directory, false, ['regex' => '~\\.xlf$~']) as $v) {
            $basename = $v->getBasename('.xlf');
            $language = '';
            if (strpos($basename, '.') === 2) {
                $language = substr($basename, 0, 2);
                $basename = substr($basename, 3);
            }
            
            if (! isset($groups[$basename])) {
                $groups[$basename] = [
                    'source' => '',
                    'target' => [],
                ];
            }
            
            if (empty($language)) {
                $groups[$basename]['source'] = $v->getPathname();
            } else {
                $groups[$basename]['target'][$language] = $v->getPathname();
            }
        }
        
        return $groups;
    }
    
    protected function createGroupInstances(string $productName, ?string $fallbackLanguage, array $groups): array
    {
        $output = [];
        
        foreach ($groups as $group) {
            $_group = $this->createSingleGroupInstance($group['source'] ?? null, $group['target'], $productName, $fallbackLanguage);
            if ($_group) {
                $output[] = $_group;
            }
        }
        
        return $output;
    }
    
    protected function createSingleGroupInstance(
        ?string $sourceFile,
        array $targetFiles,
        string $productName,
        ?string $fallbackLanguage
    ): ?TranslationFileGroup
    {
        $fallbackLanguage = $fallbackLanguage ?? 'en';
        
        if (! isset($sourceFile)) {
            $fallbackFile = reset($targetFiles);
            $fallbackFileBaseName = basename($fallbackFile);
            $sourceFile = dirname($fallbackFile) . DIRECTORY_SEPARATOR .
                          substr($fallbackFileBaseName, strpos($fallbackFileBaseName, '.') + 1);
        }
        
        $source = $this->readSource($sourceFile, $targetFiles, $productName, $fallbackLanguage);
        if (! $this->constraintApplier->isFileAllowed($productName, $source->filename, $source->sourceLang)) {
            return null;
        }
        
        return GeneralUtility::makeInstance(
            TranslationFileGroup::class,
            $productName,
            $source,
            $this->readTargets($targetFiles, $productName, $source)
        );
    }
    
    protected function readSource(string $sourceFile, array $targetFiles, string $productName, string $fallbackLanguage): TranslationFile
    {
        if (! Fs::exists($sourceFile)) {
            return $this->initializeMissingSourceFile($sourceFile, $targetFiles, $productName, $fallbackLanguage);
        }
        
        return $this->fileReader->read($sourceFile, $productName, null, $fallbackLanguage);
    }
    
    protected function readTargets(array $targetFiles, string $productName, TranslationFile $source): array
    {
        $targets = [];
        foreach ($targetFiles as $language => $targetFile) {
            if (! $this->constraintApplier->isFileAllowed($productName, $targetFile, $language)) {
                continue;
            }
            
            $targets[$language] = $this->fileReader->read($targetFile, $productName, $language, $source->sourceLang);
        }
        
        return $targets;
    }
    
    protected function initializeMissingSourceFile(string $sourceFile, array $targetFiles, string $productName, string $fallbackLanguage): TranslationFile
    {
        $fallbackFile = FileReader::makeEmptyFile($sourceFile, $productName, null, $fallbackLanguage);
        
        if (! empty($targetFiles)) {
            $firstTargetFile = $this->fileReader->read(reset($targetFiles), $productName, key($targetFiles), $fallbackLanguage);
            $fallbackFile->nodes = $firstTargetFile->nodes;
            $fallbackFile->params = $firstTargetFile->params;
        }
        
        foreach ($fallbackFile->nodes as $message) {
            $message->target = null;
        }
        
        return $fallbackFile;
    }
}