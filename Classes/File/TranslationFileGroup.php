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
 * Last modified: 2020.07.22 at 17:34
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\File;


use LaborDigital\Typo3BetterApi\Container\ContainerAwareTrait;
use Neunerlei\FileSystem\Fs;
use Neunerlei\PathUtil\Path;

class TranslationFileGroup
{
    use ContainerAwareTrait;
    
    /**
     * The product name / extension key for this translation file set
     *
     * @var string
     */
    protected $productName;
    
    /**
     * The default source language code if no source file was found
     *
     * @var string
     */
    protected $fallbackLanguage;
    
    /**
     * The source translation file
     *
     * @var \LaborDigital\T3TU\File\TranslationFile
     */
    protected $sourceFile;
    
    /**
     * The list of languages files for the source translation
     *
     * @var \LaborDigital\T3TU\File\TranslationFile[]
     */
    protected $targetFiles = [];
    
    /**
     * TranslationFileGroup constructor.
     *
     * @param   string  $productName       The product name / extension key for this translation file set
     * @param   string  $sourceFile        The absolute path to the source translation file
     * @param   array   $targetFiles       A list of absolute path's for the target translation files
     * @param   string  $fallbackLanguage  Optional language key to use if the source file does not exist
     */
    public function __construct(
        string $productName,
        string $sourceFile,
        array $targetFiles,
        string $fallbackLanguage = 'en'
    ) {
        $this->productName      = $productName;
        $this->fallbackLanguage = $fallbackLanguage;
        $this->initialize($sourceFile, $targetFiles);
    }
    
    /**
     * Returns the product name / extension key for this translation file set
     *
     * @return string
     */
    public function getProductName(): string
    {
        return $this->productName;
    }
    
    /**
     * Returns the source translation file
     *
     * @return \LaborDigital\T3TU\File\TranslationFile
     */
    public function getSourceFile(): TranslationFile
    {
        return $this->sourceFile;
    }
    
    /**
     * Returns the list of languages files for the source translation
     *
     * @return \LaborDigital\T3TU\File\TranslationFile[]
     */
    public function getTargetFiles(): array
    {
        return $this->targetFiles;
    }
    
    /**
     * Adds a new language to the list of target files.
     *
     * @param   string  $language  The language to add to the list of files
     */
    public function addTargetFile(string $language): void
    {
        if (isset($this->targetFiles[$language])) {
            return;
        }
        
        $filename = Path::join(dirname($this->sourceFile->filename),
            $language . '.' . basename($this->sourceFile->filename));
        
        $this->targetFiles[$language] = $this->makeTranslationFile($filename, $language);
    }
    
    
    protected function initialize(string $sourceFile, array $targetFiles): void
    {
        // Initialize the source file
        if (! Fs::exists($sourceFile)) {
            $this->sourceFile = $this->initializeMissingSourceFile($sourceFile, $targetFiles);
        } else {
            $this->sourceFile = $this->makeTranslationFile($sourceFile);
        }
        
        // Initialize the target files
        foreach ($targetFiles as $language => $targetFile) {
            $this->targetFiles[$language] = $this->makeTranslationFile($targetFile, $language);
        }
    }
    
    protected function initializeMissingSourceFile(string $sourceFile, array $targetFiles): TranslationFile
    {
        $fallbackFile           = $this->makeTranslationFile(reset($targetFiles));
        $fallbackFile->filename = $sourceFile;
        
        // Reset it to english
        $fallbackFile->targetLang  = null;
        $fallbackFile->sourceLang  = $this->fallbackLanguage;
        $fallbackFile->productName = $this->productName;
        
        // Clear target for all messages
        foreach ($fallbackFile->units as $k => $message) {
            $message->target = null;
        }
        
        return $fallbackFile;
    }
    
    protected function makeTranslationFile(string $filename, string $targetLangFallback = 'en'): TranslationFile
    {
        return $this->Container()
                    ->getWithoutDi(TranslationFile::class, [$filename, $this->productName, $targetLangFallback]);
    }
}
