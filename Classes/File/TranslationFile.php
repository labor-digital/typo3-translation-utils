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
 * Last modified: 2020.07.22 at 17:22
 */

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
 * Last modified: 2020.03.16 at 18:42
 */

namespace LaborDigital\T3tu\File;

use LaborDigital\T3ba\Core\Di\NoDiInterface;
use LaborDigital\T3ba\Core\Util\FilePermissionUtil;
use Neunerlei\Arrays\Arrays;
use Neunerlei\FileSystem\Fs;
use Neunerlei\TinyTimy\DateTimy;

class TranslationFile implements NoDiInterface
{
    
    /**
     * The full filename of this translation file
     *
     * @var string
     */
    public $filename;
    
    /**
     * The source language of this translation file
     *
     * @var string
     */
    public $sourceLang = 'en';
    
    /**
     * The target language (on lang files) or null if this is the origin file
     *
     * @var string|null
     */
    public $targetLang;
    
    /**
     * The product name to set for this translation file
     *
     * @var string
     */
    public $productName;
    
    /**
     * The list of nodes inside this translation file
     *
     * @var \LaborDigital\T3tu\File\AbstractNode[]
     */
    public $nodes = [];
    
    /**
     * Additional xml attributes for the xliff tag
     *
     * @var array
     */
    public $params = [];
    
    /**
     * TranslationFile constructor.
     *
     * @param   string       $filename        The absolute path to the file to load
     * @param   string       $productName     The name of the extension we load the language for
     * @param   string|null  $language        The target language represented by this file
     * @param   string       $sourceLanguage  The source language used as translation base
     * @param   array        $units           The translation units included in the file
     * @param   array        $params          additional xml attributes for the xliff tag
     */
    public function __construct(string $filename, string $productName, ?string $language, string $sourceLanguage, array $units, array $params)
    {
        $this->filename = $filename;
        $this->productName = $productName;
        $this->targetLang = $language;
        $this->sourceLang = $sourceLanguage;
        $this->nodes = $units;
        $this->params = $params;
    }
    
    
    public function write(): void
    {
        // Prepare map to sort namespaced and global elements correctly
        $isBaseFile = $this->targetLang === null || $this->sourceLang === $this->targetLang;
        $nodes = $this->nodes;
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
        
        // Sort the messages by their id
        ksort($temporaryMap);
        
        // Revert the keys
        $sortedMap = [];
        foreach ($temporaryMap as $k => $v) {
            if (isset($mapIdMapping[$k])) {
                $k = $mapIdMapping[$k];
            }
            $sortedMap[$k] = $v;
        }
        $nodes = $sortedMap;
        unset($temporaryMap, $mapIdMapping, $sortedMap);
        
        // Build a list of children
        $children = [
            'tag' => 'body',
        ];
        foreach ($nodes as $node) {
            if ($node instanceof NoteNode) {
                $children[] = [
                    'tag' => 'note',
                    '@id' => $node->id,
                    'content' => $this->wrapCDataLabel(PHP_EOL . $node->note . PHP_EOL),
                ];
            } elseif ($node instanceof TransUnitNode) {
                $children[]
                    = Arrays::attach(
                    [
                        'tag' => 'trans-unit',
                        '@id' => $node->id,
                        [
                            'tag' => 'source',
                            'content' => $this->wrapCDataLabel($node->source),
                        ],
                    ],
                    $isBaseFile
                        ? []
                        : [
                        1 => [
                            'tag' => 'target',
                            'content' => $this->wrapCDataLabel($node->target),
                        ],
                    ]
                );
            }
        }
        
        // Create an array representation for this file
        $out = [
            [
                'tag' => 'xliff',
                '@version' => '1.0',
                Arrays::attach(
                    [
                        'tag' => 'file',
                        '@source-language' => $this->sourceLang,
                        '@datatype' => 'plaintext',
                        '@original' => 'messages',
                        '@date' => (new DateTimy())->format("Y-m-d\TH:i:s\Z"),
                        '@product-name' => $this->productName,
                        [
                            'tag' => 'header',
                            'content' => '',
                        ],
                        $children,
                    ],
                    $isBaseFile ? [] : ['@target-language' => $this->targetLang]),
            ],
        ];
        
        // Dump the file
        $xml = Arrays::dumpToXml($out, true);
        $xml = $this->postProcessXmlStyle($xml);
        Fs::writeFile($this->filename, $xml);
        FilePermissionUtil::setFilePermissions($this->filename);
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
                    
                    return $space . '    ' . $v;
                }, $lines));
                
                return PHP_EOL . $openTag . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . $space . $closeTag;
            }, $xml);
    }
}
