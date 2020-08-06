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
 * Last modified: 2020.07.22 at 17:32
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\File;


use Iterator;
use LaborDigital\Typo3BetterApi\Container\ContainerAwareTrait;
use Neunerlei\FileSystem\Fs;

class TranslationSet
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
     * The directory where this file set is located
     *
     * @var string
     */
    protected $directory;
    
    /**
     * The list of all languages in this set
     *
     * @var array
     */
    protected $languages = [];
    
    /**
     * The list of groups inside this set
     *
     * @var \LaborDigital\T3TU\File\TranslationFileGroup[]
     */
    protected $groups = [];
    
    public function __construct(string $productName, string $directory, string $fallbackLanguage = 'en')
    {
        $this->productName      = $productName;
        $this->directory        = $directory;
        $this->fallbackLanguage = $fallbackLanguage;
        $this->initialize();
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
     * Returns the directory where this file set is located
     *
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }
    
    /**
     * Returns the list of all languages in this set
     *
     * @return array
     */
    public function getLanguages(): array
    {
        return $this->languages;
    }
    
    /**
     * Returns the list of all translation groups
     *
     * @return \LaborDigital\T3TU\File\TranslationFileGroup[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
    
    
    protected function initialize(): void
    {
        $this->createGroupInstances(
            $this->findGroups(Fs::getDirectoryIterator($this->directory, false, ['regex' => '~\\.xlf$~']))
        );
    }
    
    protected function findGroups(Iterator $iterator): array
    {
        $groups = [];
        foreach ($iterator as $k => $v) {
            $basename = $v->getBasename('.xlf');
            $langKey  = '';
            if (strpos($basename, '.') === 2) {
                $langKey                   = substr($basename, 0, 2);
                $this->languages[$langKey] = $langKey;
                $basename                  = substr($basename, 3);
            }
            
            // Make sure we have a fileset
            if (! isset($groups[$basename])) {
                $groups[$basename] = [
                    'source' => '',
                    'target' => [],
                ];
            }
            
            // Add file to list
            if (empty($langKey)) {
                $groups[$basename]['source'] = $v->getPathname();
            } else {
                $groups[$basename]['target'][$langKey] = $v->getPathname();
            }
        }
        
        return $groups;
    }
    
    protected function createGroupInstances(array $groups): void
    {
        foreach ($groups as $group) {
            // Make sure the source file exists
            if (! isset($group['source'])) {
                $fallbackFile         = reset($group['target']);
                $fallbackFileBaseName = basename($fallbackFile);
                $group['source']      = dirname($fallbackFile) . DIRECTORY_SEPARATOR .
                                        substr($fallbackFileBaseName, strpos($fallbackFileBaseName, '.') + 1);
            }
            
            // Instantiate the group
            $this->groups[] = $this->Container()->getWithoutDi(TranslationFileGroup::class, [
                $this->productName,
                $group['source'],
                $group['target'],
            ]);
        }
    }
}
