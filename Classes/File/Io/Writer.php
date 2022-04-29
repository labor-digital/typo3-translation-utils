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
 * Last modified: 2021.08.23 at 12:46
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\File\Io;


use InvalidArgumentException;
use LaborDigital\T3ba\Core\Util\FilePermissionUtil;
use LaborDigital\T3tu\File\AbstractNode;
use LaborDigital\T3tu\File\NoteNode;
use LaborDigital\T3tu\File\TranslationFile;
use LaborDigital\T3tu\File\TranslationFileGroup;
use LaborDigital\T3tu\File\TranslationSet;
use LaborDigital\T3tu\File\TransUnitNode;
use Neunerlei\Arrays\Arrays;
use Neunerlei\FileSystem\Fs;
use Neunerlei\TinyTimy\DateTimy;

class Writer
{
    public function writeSet(TranslationSet $set): void
    {
        array_map([$this, 'writeGroup'], $set->getGroups());
    }
    
    public function writeGroup(TranslationFileGroup $group): void
    {
        $this->writeFile($group->getSourceFile());
        foreach ($group->getTargetFiles() as $targetFile) {
            $this->writeFile($targetFile);
        }
    }
    
    public function writeFile(TranslationFile $file): void
    {
        if ($file->initialHash === $file->getHash()) {
            return;
        }
        
        $isBaseFile = $file->targetLang === null || $file->sourceLang === $file->targetLang;
        $children = $this->convertNodesToChildren(
            $isBaseFile,
            $this->sortNodes($file->nodes)
        );
        $xml = Arrays::dumpToXml($this->createFileWrap($isBaseFile, $children, $file), true);
        $xml = $this->postProcessXmlStyle($xml);
        Fs::writeFile($file->filename, $xml);
        FilePermissionUtil::setFilePermissions($file->filename);
    }
    
    /**
     * Sorts the nodes inside a file while honoring the namespacing of keys
     *
     * @param   array  $nodes
     *
     * @return array
     */
    protected function sortNodes(array $nodes): array
    {
        $mapIdMapping = [];
        $temporaryMap = [];
        foreach ($nodes as $k => $v) {
            if (strpos($k, '.') === false) {
                $_k = '_globalKeys.' . $k;
                $mapIdMapping[$_k] = $k;
                $k = $_k;
            }
            $temporaryMap[$k] = $v;
        }
        
        ksort($temporaryMap);
        
        $sortedMap = [];
        foreach ($temporaryMap as $k => $v) {
            if (isset($mapIdMapping[$k])) {
                $k = $mapIdMapping[$k];
            }
            $sortedMap[$k] = $v;
        }
        
        return $sortedMap;
    }
    
    /**
     * Converts the node objects into a xml builder compatible array representation of children.
     *
     * @param   bool   $isBaseFile
     * @param   array  $nodes
     *
     * @return string[]
     */
    protected function convertNodesToChildren(bool $isBaseFile, array $nodes): array
    {
        $children = [
            'tag' => 'body',
        ];
        
        foreach ($nodes as $node) {
            $children[] = $this->convertNode($isBaseFile, $node);
        }
        
        return $children;
    }
    
    /**
     * Converts a single node object into a xml builder compatible array representation
     *
     * @param   bool                                  $isBaseFile
     * @param   \LaborDigital\T3tu\File\AbstractNode  $node
     *
     * @return array
     */
    protected function convertNode(bool $isBaseFile, AbstractNode $node): array
    {
        if ($node instanceof NoteNode) {
            return $this->convertNote($node);
        }
        
        if ($node instanceof TransUnitNode) {
            return $this->convertTransUnit($isBaseFile, $node);
        }
        
        throw new InvalidArgumentException(
            'Failed to prepare a node: ' . $node->id .
            ', because it is of an unknown type: ' . get_class($node));
    }
    
    /**
     * Converts a "note" node into a xml builder compatible array representation
     *
     * @param   \LaborDigital\T3tu\File\NoteNode  $unit
     *
     * @return array
     */
    protected function convertNote(NoteNode $unit): array
    {
        return [
            'tag' => 'note',
            '@id' => $unit->id,
            'content' => $this->wrapCDataLabel(PHP_EOL . $unit->note . PHP_EOL),
        ];
    }
    
    /**
     * Converts a "trans-unit" node into a xml builder compatible array representation
     *
     * @param   bool                                   $isBaseFile
     * @param   \LaborDigital\T3tu\File\TransUnitNode  $unit
     *
     * @return array
     */
    protected function convertTransUnit(bool $isBaseFile, TransUnitNode $unit): array
    {
        return Arrays::attach(
            [
                'tag' => 'trans-unit',
                '@id' => $unit->id,
                [
                    'tag' => 'source',
                    'content' => $this->wrapCDataLabel($unit->source),
                ],
            ],
            $isBaseFile
                ? []
                :
                [
                    1 => [
                        'tag' => 'target',
                        'content' => $this->wrapCDataLabel($unit->target),
                    ],
                ]
        );
    }
    
    /**
     * Makes sure that labels containing tags are encoded in cData
     *
     * @param   string  $label
     *
     * @return string
     */
    protected function wrapCDataLabel(string $label): string
    {
        if (strpos($label, '<') !== false) {
            return '<![CDATA[' . $label . ']]>';
        }
        
        return $label;
    }
    
    /**
     * Creates the outer wrap around the generated children based on the file configuration
     *
     * @param   bool                                     $isBaseFile
     * @param   array                                    $children
     * @param   \LaborDigital\T3tu\File\TranslationFile  $file
     *
     * @return array[]
     */
    protected function createFileWrap(bool $isBaseFile, array $children, TranslationFile $file): array
    {
        return [
            [
                'tag' => 'xliff',
                '@version' => '1.0',
                Arrays::merge(
                    $file->params,
                    Arrays::attach(
                        [
                            'tag' => 'file',
                            '@source-language' => $file->sourceLang,
                            '@datatype' => 'plaintext',
                            '@original' => 'messages',
                            '@date' => (new DateTimy())->format("Y-m-d\TH:i:s\Z"),
                            '@product-name' => $file->productName,
                            [
                                'tag' => 'header',
                                'content' => '',
                            ],
                            $children,
                        ],
                        $isBaseFile ? [] : ['@target-language' => $file->targetLang]
                    )
                ),
            ],
        ];
    }
    
    /**
     * Make sure we format the xml correctly to match the recommended format
     *
     * @param   string  $xml
     *
     * @return string
     */
    protected function postProcessXmlStyle(string $xml): string
    {
        $xml = preg_replace_callback('~^(\s\s)+<~m', static function ($m) {
            return str_replace('  ', '    ', $m[0]);
        }, $xml);
        
        return preg_replace_callback('~\n(([^<]*?)<(trans-unit|source|target|note)\s[^>]*?>)(.*?)(</\3>)~msi',
            static function ($m) {
                $openTag = $m[1];
                $closeTag = trim($m[5]);
                $space = str_replace(PHP_EOL, '', $m[2]);
                $lines = explode(PHP_EOL, $m[4]);
                $lines = array_filter(array_map(static function ($v) use ($space) {
                    $v = trim($v);
                    if ($v === '') {
                        return null;
                    }
                    
                    return $space . str_repeat(' ', 4) . $v;
                }, $lines));
                
                return PHP_EOL . $openTag . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . $space . $closeTag;
            }, $xml);
    }
}