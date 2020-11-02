<?php
declare(strict_types=1);
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
 * Last modified: 2020.07.22 at 22:29
 */

namespace LaborDigital\T3TU\Sync;


use LaborDigital\T3TU\File\TranslationFileGroup;

class TranslationSyncMapping
{

    /**
     * The translation file group we should synchronize
     *
     * @var \LaborDigital\T3TU\File\TranslationFileGroup
     */
    protected $group;

    /**
     * A list that maps the messages over all registered languages by their id
     *
     * @var array
     */
    protected $map = [];

    /**
     * A list that maps the source text to the map id, as fallback if the id's were changed
     * in the origin file, but not in the children
     *
     * @var array
     */
    protected $sourceMap = [];

    /**
     * TranslationSyncMapping constructor.
     *
     * @param   \LaborDigital\T3TU\File\TranslationFileGroup  $group
     */
    public function __construct(TranslationFileGroup $group)
    {
        $this->group = $group;
    }

    /**
     * Returns the translation file group we should synchronize
     *
     * @return \LaborDigital\T3TU\File\TranslationFileGroup
     */
    public function getGroup(): TranslationFileGroup
    {
        return $this->group;
    }

    /**
     * The main method which synchronizes the file contents with each other,
     * updating id's and sources to match the origin file
     */
    public function synchronize(): TranslationFileGroup
    {
        // Initialize the map
        $this->map       = [];
        $this->sourceMap = [];
        $this->initializeSourceFile();
        $this->initializeTargetFiles();

        // Sort the map
        ksort($this->map);

        // Loop over all languages
        $sourceFile = $this->group->getSourceFile();
        foreach ($this->group->getTargetFiles() as $fileId => $targetFile) {
            $fileId       = $targetFile->filename;
            $sourceFileId = $this->group->getSourceFile()->filename;

            // Update the meta data
            $targetFile->productName = $sourceFile->productName;
            $targetFile->sourceLang  = $sourceFile->sourceLang;

            // Reset the internal message storage
            $targetFile->units = [];

            // Rebuild the messages list based on the mapping
            foreach ($this->map as $id => $units) {
                // Check if we know this message in the target lang
                if (! isset($units[$fileId])) {
                    // Clone the message from the source message
                    $unit = clone $units[$this->group->getSourceFile()->filename];
                    if (! $unit->isNote) {
                        $unit->target = 'COPY FROM: ' . $sourceFile->sourceLang . ' - ' . $unit->source;
                    }
                } else {
                    // Update the id and the source
                    $unit     = $units[$fileId];
                    $unit->id = $id;
                    if ($unit->isNote) {
                        $unit->note = $units[$sourceFileId]->note;
                    } else {
                        $unit->source = $units[$sourceFileId]->source;
                    }
                }
                $targetFile->units[$id] = $unit;
            }
        }

        // Done
        $this->map       = [];
        $this->sourceMap = [];

        return $this->getGroup();
    }

    /**
     * Reads the contents of the source translation file into the map
     */
    protected function initializeSourceFile(): void
    {
        $sourceFile = $this->group->getSourceFile();
        foreach ($sourceFile->units as $unit) {
            $sourceId                     = md5(trim($unit->isNote ? $unit->note : $unit->source));
            $this->map[$unit->id]         = [$sourceFile->filename => $unit];
            $this->sourceMap[$sourceId][] = $unit->id;
        }
    }

    /**
     * Reads the contents of the target files into the map
     */
    protected function initializeTargetFiles(): void
    {
        foreach ($this->group->getTargetFiles() as $targetFile) {
            // Read the content into the map
            foreach ($targetFile->units as $unit) {
                $sourceId = md5(trim($unit->isNote ? $unit->note : $unit->source));

                // Try to map the id over the source string (Fallback if source id was changed)
                if (! isset($this->map[$unit->id])) {
                    if (isset($this->sourceMap[$sourceId])) {
                        // Go the fast route if there is only a single match
                        if (count($this->sourceMap[$sourceId]) === 1) {
                            $unit->id = $this->sourceMap[$sourceId][0];
                        } else {
                            // Try to figure out the correct match
                            $matches = array_filter($this->sourceMap[$sourceId], static function ($v) use ($targetFile) {
                                return ! isset($targetFile->units[$v]);
                            });

                            // Mapping failed
                            if (empty($matches)) {
                                continue;
                            }

                            // Update id
                            $unit->id = reset($matches);
                        }
                    } else {
                        continue;
                    }
                }
                $this->map[$unit->id][$targetFile->filename] = $unit;
            }
        }
    }
}
