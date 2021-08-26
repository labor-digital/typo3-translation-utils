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
}
