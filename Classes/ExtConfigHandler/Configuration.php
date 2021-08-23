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
 * Last modified: 2021.08.23 at 16:07
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\ExtConfigHandler;


use LaborDigital\T3ba\Core\Di\NoDiInterface;
use Neunerlei\Configuration\State\ConfigState;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Configuration implements NoDiInterface
{
    /**
     * @var \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public $importConstraint;
    
    /**
     * @var \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public $exportConstraint;
    
    /**
     * @var \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public $syncConstraint;
    
    /**
     * The default file format to export translations in
     *
     * @var string
     */
    public $defaultExportFormat = 'csv';
    
    public function __construct()
    {
        $this->importConstraint = GeneralUtility::makeInstance(FileConstraint::class);
        $this->exportConstraint = GeneralUtility::makeInstance(FileConstraint::class);
        $this->syncConstraint = GeneralUtility::makeInstance(FileConstraint::class);
    }
    
    public function finish(ConfigState $state): void
    {
        $state->set('export.defaultFormat', $this->defaultExportFormat);
        
        foreach (
            [
                'import.constraint' => $this->importConstraint->getConfig(),
                'export.constraint' => $this->exportConstraint->getConfig(),
                'sync.constraint' => $this->syncConstraint->getConfig(),
            ] as $storageKey => $config
        ) {
            if ($config === null) {
                continue;
            }
            
            $state->setAsJson($storageKey, $config);
        }
    }
}