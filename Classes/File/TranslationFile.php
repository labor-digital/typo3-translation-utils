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

namespace LaborDigital\T3TU\File;

use LaborDigital\Typo3BetterApi\Container\ContainerAwareTrait;
use LaborDigital\Typo3BetterApi\FileAndFolder\Permissions;
use Neunerlei\Arrays\ArrayGeneratorException;
use Neunerlei\Arrays\Arrays;
use Neunerlei\FileSystem\Fs;
use Neunerlei\TinyTimy\DateTimy;

class TranslationFile
{
    use ContainerAwareTrait;
    
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
     * The list of translation pairs inside of this translation file
     *
     * @var \LaborDigital\T3TU\File\TranslationFileUnit[]
     */
    public $units = [];
    
    /**
     * Additional xml attributes for the xliff tag
     *
     * @var array
     */
    public $params = [];
    
    /**
     * TranslationFile constructor.
     *
     * @param   string  $filename            The absolute path to the file to load
     * @param   string  $productName         The name of the extension we load the language for
     * @param   string  $targetLangFallback  The target language fallback if we don't have one set
     */
    public function __construct(string $filename, string $productName, string $targetLangFallback = 'en')
    {
        $this->filename = $filename;
        $this->initialize($productName, $targetLangFallback);
    }
    
    
    public function write(): void
    {
        // Prepare map to sort namespaced and global elements correctly
        $isBaseFile   = $this->targetLang === null || $this->sourceLang === $this->targetLang;
        $messages     = $this->units;
        $mapIdMapping = [];
        $temporaryMap = [];
        foreach ($messages as $k => $v) {
            if (strpos($k, '.') === false) {
                $_k                = '_globalKeys.' . $k;
                $mapIdMapping[$_k] = $k;
                $k                 = $_k;
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
        $messages = $sortedMap;
        unset($temporaryMap, $mapIdMapping, $sortedMap);
        
        // Build a list of children
        $children = [
            'tag' => 'body',
        ];
        foreach ($messages as $message) {
            if ($message->isNote) {
                $children[] = [
                    'tag'     => 'note',
                    '@id'     => $message->id,
                    'content' => PHP_EOL . $message->note . PHP_EOL,
                ];
            } else {
                $children[]
                    = Arrays::attach(
                    [
                        'tag' => 'trans-unit',
                        '@id' => $message->id,
                        [
                            'tag'     => 'source',
                            'content' => $message->source,
                        ],
                    ],
                    $isBaseFile
                        ? []
                        : [
                        1 => [
                            'tag'     => 'target',
                            'content' => $message->target,
                        ],
                    ]
                );
            }
        }
        
        // Create an array representation for this file
        $out = [
            [
                'tag'      => 'xliff',
                '@version' => '1.0',
                Arrays::attach(
                    [
                        'tag'              => 'file',
                        '@source-language' => $this->sourceLang,
                        '@datatype'        => 'plaintext',
                        '@original'        => 'messages',
                        '@date'            => (new DateTimy())->format("Y-m-d\TH:i:s\Z"),
                        '@product-name'    => $this->productName,
                        [
                            'tag'     => 'header',
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
        Permissions::setFilePermissions($this->filename);
    }
    
    /**
     * Initializes the instance by reading the file contents
     *
     * @param   string  $productName         The name of the extension we load the language for
     * @param   string  $targetLangFallback  the target language fallback if we don't have one set
     *
     * @throws \LaborDigital\T3TU\File\InvalidXmlFileException
     */
    protected function initialize(string $productName, string $targetLangFallback): void
    {
        // Handle non-existing files
        if (! file_exists($this->filename)) {
            $this->sourceLang  = 'en';
            $this->productName = $productName;
            $this->targetLang  = $targetLangFallback;
            
            return;
        }
        
        $content = Fs::readFile($this->filename);
        try {
            $contentList = Arrays::makeFromXml($content);
        } catch (ArrayGeneratorException $exception) {
            throw new InvalidXmlFileException(
                'Failed to parse the translation file: ' . $this->filename,
                $exception->getCode(),
                $exception);
        }
        
        // Read the file metadata
        foreach (Arrays::getPath($contentList, '0.0.*', []) as $k => $row) {
            if (is_string($k) && strpos($k, '@') === 0) {
                $this->params[$k] = $row;
            }
        }
        $this->sourceLang  = Arrays::getPath($contentList, '0.0.@source-language', 'en');
        $this->productName = Arrays::getPath($contentList, '0.0.@product-name', $productName);
        $this->targetLang  = Arrays::getPath($contentList, '0.0.@target-language', $targetLangFallback);
        
        // Read the messages
        foreach (Arrays::getPath($contentList, '0.0.*', []) as $entry) {
            if (! isset($entry['tag']) || $entry['tag'] !== 'body') {
                continue;
            }
            foreach ($entry as $k => $row) {
                // Ignore body tag
                if ($k === 'tag') {
                    continue;
                }
                // Ignore attributes
                if (is_string($k)) {
                    continue;
                }
                // Ignore invalid elements
                if (! isset($row['@id'])) {
                    continue;
                }
                
                // Create a new item/unit
                $unit     = $this->Container()->getWithoutDi(TranslationFileUnit::class);
                $unit->id = $row['@id'];
                $hasError = false;
                
                // Save notes
                if ($row['tag'] === 'note') {
                    $unit->isNote = true;
                    
                    // Unify line breaks
                    $row['content'] = str_replace(["\t", "\r\n", PHP_EOL], PHP_EOL, $row['content']);
                    
                    $unit->note = isset($row['content']) ?
                        implode(PHP_EOL, array_filter(array_map('trim', explode(PHP_EOL, $row['content'])))) : '';
                } // Save translation units
                elseif ($row['tag'] === 'trans-unit') {
                    foreach ($row as $_k => $child) {
                        if (is_string($_k) || ! is_array($child)) {
                            continue;
                        }
                        
                        // Unify line breaks
                        $child['content'] = str_replace(["\t", "\r\n", PHP_EOL], PHP_EOL, $child['content']);
                        $child['content'] = implode(' ',
                            array_filter(array_map('trim', explode(PHP_EOL, $child['content']))));
                        
                        // Load the content
                        switch (Arrays::getPath($child, 'tag')) {
                            case 'source':
                                $unit->source = $child['content'];
                                break;
                            case 'target':
                                $unit->target = $child['content'];
                                break;
                            default:
                                $hasError = true;
                                break;
                        }
                    }
                }
                
                // Ignore on error
                if ($hasError) {
                    continue;
                }
                $this->units[$unit->id] = $unit;
            }
        }
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
        $xml = preg_replace_callback('~\n(([^<]*?)<(trans-unit|source|target|note)\s[^>]*?>)(.*?)(</\3>)~msi',
            static function ($m) {
                $openTag  = $m[1];
                $closeTag = trim($m[5]);
                $space    = str_replace(PHP_EOL, '', $m[2]);
                $lines    = explode(PHP_EOL, $m[4]);
                $lines    = array_filter(array_map(static function ($v) use ($space) {
                    $v = trim($v);
                    if ($v === '') {
                        return null;
                    }
                    
                    return $space . '    ' . $v;
                }, $lines));
                
                return PHP_EOL . $openTag . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . $space . $closeTag;
            }, $xml);
        
        return $xml;
    }
}
