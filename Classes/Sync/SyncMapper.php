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

namespace LaborDigital\T3tu\Sync;


use LaborDigital\T3tu\File\NoteNode;
use LaborDigital\T3tu\File\TranslationFileGroup;
use LaborDigital\T3tu\File\TransUnitNode;

class SyncMapper
{
    /**
     * The translation file group we should synchronize
     *
     * @var \LaborDigital\T3tu\File\TranslationFileGroup
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
     * Synchronizes all files inside the given group
     *
     * @param   \LaborDigital\T3tu\File\TranslationFileGroup  $group
     */
    public function apply(TranslationFileGroup $group): void
    {
        $this->group = $group;
        $this->map = [];
        $this->sourceMap = [];
        
        $this->initializeSourceFile();
        $this->initializeTargetFiles();
        ksort($this->map);
        
        // Loop over all languages
        $sourceFile = $this->group->getSourceFile();
        foreach ($this->group->getTargetFiles() as $targetFile) {
            $fileId = $targetFile->filename;
            $sourceFileId = $this->group->getSourceFile()->filename;
            
            $targetFile->productName = $sourceFile->productName;
            $targetFile->sourceLang = $sourceFile->sourceLang;
            $targetFile->nodes = [];
            
            // Rebuild the messages list based on the mapping
            foreach ($this->map as $id => $nodes) {
                // Check if we know this message in the target lang
                if (! isset($nodes[$fileId])) {
                    // Clone the message from the source message
                    $node = clone $nodes[$this->group->getSourceFile()->filename];
                    if ($node instanceof TransUnitNode) {
                        $node->target = 'COPY FROM: ' . $sourceFile->sourceLang . ' - ' . $node->source;
                    }
                } else {
                    // Update the id and the source
                    $node = $nodes[$fileId];
                    $node->id = $id;
                    if ($node instanceof NoteNode) {
                        $node->note = $nodes[$sourceFileId]->note;
                    } elseif ($node instanceof TransUnitNode) {
                        $node->source = $nodes[$sourceFileId]->source;
                    }
                }
                $targetFile->nodes[$id] = $node;
            }
        }
        
        $this->map = $this->sourceMap = [];
    }
    
    /**
     * Reads the contents of the source translation file into the map
     */
    protected function initializeSourceFile(): void
    {
        $sourceFile = $this->group->getSourceFile();
        foreach ($sourceFile->nodes as $node) {
            $sourceId = md5(trim($node instanceof NoteNode ? $node->note : $node->source));
            $this->map[$node->id] = [$sourceFile->filename => $node];
            $this->sourceMap[$sourceId][] = $node->id;
        }
    }
    
    /**
     * Reads the contents of the target files into the map
     */
    protected function initializeTargetFiles(): void
    {
        foreach ($this->group->getTargetFiles() as $targetFile) {
            // Read the content into the map
            foreach ($targetFile->nodes as $node) {
                $sourceId = md5(trim($node instanceof NoteNode ? $node->note : $node->source));
                
                // Try to map the id over the source string (Fallback if source id was changed)
                if (! isset($this->map[$node->id])) {
                    if (isset($this->sourceMap[$sourceId])) {
                        // Go the fast route if there is only a single match
                        if (count($this->sourceMap[$sourceId]) === 1) {
                            $node->id = $this->sourceMap[$sourceId][0];
                        } else {
                            // Try to figure out the correct match
                            $matches = array_filter($this->sourceMap[$sourceId], static function ($v) use ($targetFile) {
                                return ! isset($targetFile->nodes[$v]);
                            });
                            
                            // Mapping failed
                            if (empty($matches)) {
                                continue;
                            }
                            
                            // Update id
                            $node->id = reset($matches);
                        }
                    } else {
                        continue;
                    }
                }
                $this->map[$node->id][$targetFile->filename] = $node;
            }
        }
    }
}
