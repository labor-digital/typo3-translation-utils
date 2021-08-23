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
 * Last modified: 2021.08.23 at 10:29
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\File\Io;


use LaborDigital\T3ba\Tool\TypoContext\TypoContext;
use LaborDigital\T3tu\File\TranslationSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Reader
{
    /**
     * @var \LaborDigital\T3tu\File\Io\GroupReader
     */
    protected $groupReader;
    
    /**
     * @var \LaborDigital\T3ba\Tool\TypoContext\TypoContext
     */
    protected $typoContext;
    
    public function __construct(GroupReader $groupReader, TypoContext $typoContext)
    {
        $this->groupReader = $groupReader;
        $this->typoContext = $typoContext;
    }
    
    public function read(string $extKey, ?string $fallbackLanguage = null): TranslationSet
    {
        $directory = $this->getSetDirectory($extKey);
        $productName = $extKey;
        
        $groups = $this->groupReader->read($productName, $directory, $fallbackLanguage);
        
        return GeneralUtility::makeInstance(TranslationSet::class, $productName, $directory, $groups);
    }
    
    /**
     * Returns the absolute path to the translation directory of a given extension key
     *
     * @param   string  $extKey
     *
     * @return string
     */
    protected function getSetDirectory(string $extKey): string
    {
        return $this->typoContext->path()->getExtensionPath($extKey) . 'Resources/Private/Language/';
    }
}