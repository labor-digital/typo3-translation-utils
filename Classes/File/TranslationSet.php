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


namespace LaborDigital\T3tu\File;


use LaborDigital\T3ba\Core\Di\NoDiInterface;

class TranslationSet implements NoDiInterface
{
    
    /**
     * The product name / extension key for this translation file set
     *
     * @var string
     */
    protected $productName;
    
    /**
     * The directory where this file set is located
     *
     * @var string
     */
    protected $directory;
    
    /**
     * The list of groups inside this set
     *
     * @var \LaborDigital\T3tu\File\TranslationFileGroup[]
     */
    protected $groups = [];
    
    public function __construct(string $productName, string $directory, array $groups)
    {
        $this->productName = $productName;
        $this->directory = $directory;
        $this->groups = $groups;
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
        $languages = [];
        
        foreach ($this->groups as $group) {
            $languages[] = $group->getSourceFile()->sourceLang;
            
            foreach ($group->getTargetFiles() as $targetFile) {
                $languages[] = $targetFile->sourceLang;
                $languages[] = $targetFile->targetLang;
            }
        }
        
        return array_unique(array_filter($languages));
    }
    
    /**
     * Returns the list of all translation groups
     *
     * @return \LaborDigital\T3tu\File\TranslationFileGroup[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
