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
 * Last modified: 2020.07.23 at 11:48
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\Sync;


use LaborDigital\T3TU\File\TranslationFileGroup;
use LaborDigital\T3TU\Util\TranslationUtilTrait;

class TranslationSynchronizer
{
    use TranslationUtilTrait;
    
    /**
     * Synchronizes all translation files that are found in the translation directory of the extension with $extKey.
     * Synchronize will: Create missing translation files based on the list of all possible translation languages for all found source files.
     * It will also use the source file (the one without the language key) as a single source of origin an update all target files (the ones with the language
     * key) to contain all trans-units that exist in the source file. It removes old translation units that were removed in the source file.
     * It can automatically re-map trans-unit id's if you have changed them in your source file. It makes sure that your "source" node is up to date.
     * And it also updates the files meta-data and sorts all units alphabetically
     *
     * @param   string  $extKey
     * @param   string  $sourceFallbackLanguage
     */
    public function synchronize(string $extKey, string $sourceFallbackLanguage = 'en'): void
    {
        $set = $this->getSet($extKey, $sourceFallbackLanguage);
        foreach ($set->getGroups() as $group) {
            $this->synchronizeSingleGroup($group, $set->getLanguages());
        }
    }
    
    /**
     * Synchronizes the given group of translation files and adds missing languages based on all languages in the translation set
     *
     * @param   TranslationFileGroup  $group      The group to synchronize
     * @param   array                 $languages  The list of languages that exist in the set
     */
    protected function synchronizeSingleGroup(TranslationFileGroup $group, array $languages): void
    {
        // Make sure we have target files for all languages
        $sourceLanguage  = $group->getSourceFile()->sourceLang;
        $targetLanguages = array_diff($languages, [$sourceLanguage]);
        foreach ($targetLanguages as $language) {
            if (! isset($group->getTargetFiles()[$language])) {
                $group->addTargetFile($language);
            }
        }
        
        // Create the map to synchronize the group
        $map = $this->Container()->getWithoutDi(TranslationSyncMapping::class, [$group]);
        $map->synchronize();
        
        // Persist the files
        $group->getSourceFile()->write();
        foreach ($group->getTargetFiles() as $targetFile) {
            $targetFile->write();
        }
    }
}
