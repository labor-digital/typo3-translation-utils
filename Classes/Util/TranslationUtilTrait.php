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
 * Last modified: 2020.07.23 at 09:58
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\Util;


use LaborDigital\T3ba\Tool\TypoContext\TypoContext;
use LaborDigital\T3tu\File\TranslationSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait TranslationUtilTrait
{
    
    /**
     * Creates the translation set for a given extension key
     *
     * @param   string  $extKey
     *
     * @param   string  $fallbackLanguage
     *
     * @return \LaborDigital\T3tu\File\TranslationSet
     */
    protected function getSet(string $extKey, string $fallbackLanguage = 'en'): TranslationSet
    {
        $directory = $this->getSetDirectory($extKey);
        
        return GeneralUtility::makeInstance(TranslationSet::class, $extKey, $directory, $fallbackLanguage);
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
        return TypoContext::getInstance()->path()->getExtensionPath($extKey) . 'Resources/Private/Language/';
    }
}
