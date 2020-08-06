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


namespace LaborDigital\T3TU\Util;


use LaborDigital\T3TU\File\TranslationSet;
use LaborDigital\Typo3BetterApi\Container\ContainerAwareTrait;
use LaborDigital\Typo3BetterApi\TypoContext\TypoContext;

trait TranslationUtilTrait
{
    use ContainerAwareTrait;
    
    /**
     * Creates the translation set for a given extension key
     *
     * @param   string  $extKey
     *
     * @param   string  $fallbackLanguage
     *
     * @return \LaborDigital\T3TU\File\TranslationSet
     */
    protected function getSet(string $extKey, string $fallbackLanguage = 'en'): TranslationSet
    {
        // Build the directory name
        $directory = $this->getSetDirectory($extKey);
        
        // Build the set
        return $this->Container()->getWithoutDi(TranslationSet::class, [$extKey, $directory, $fallbackLanguage]);
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
        return $this->getInstanceOf(TypoContext::class)
                    ->Path()->getExtensionPath($extKey) . 'Resources/Private/Language/';
    }
}
