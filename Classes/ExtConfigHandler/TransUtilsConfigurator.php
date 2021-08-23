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
 * Last modified: 2021.08.23 at 09:05
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\ExtConfigHandler;


use InvalidArgumentException;
use LaborDigital\T3ba\ExtConfig\Abstracts\AbstractExtConfigConfigurator;
use Neunerlei\Configuration\State\ConfigState;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TransUtilsConfigurator extends AbstractExtConfigConfigurator
{
    /**
     * The list of instantiated configuration objects by ext key
     *
     * @var \LaborDigital\T3tu\ExtConfigHandler\Configuration[]
     */
    protected $configList = [];
    
    /**
     * Configures the default file format to use when exporting the translations
     *
     * @param   string  $format  Allows you to set the output format (default: csv), Options are: "csv", "xls", "xlsx" and "ods"
     *
     * @return $this
     */
    public function setDefaultExportFormat(string $format): self
    {
        $format = strtolower(trim($format));
        if (! in_array($format, ['csv', 'xls', 'xlsx', 'ods'])) {
            throw new InvalidArgumentException('The given format: ' . $format . ' is invalid!');
        }
        
        $this->getConfig()->defaultExportFormat = $format;
        
        return $this;
    }
    
    /**
     * Defines which files should be handled by the "import" task.
     *
     * @return \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public function importConstraint(): FileConstraint
    {
        return $this->getConfig()->importConstraint;
    }
    
    /**
     * Defines which files should be handled by the "export" task.
     *
     * @return \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public function exportConstraint(): FileConstraint
    {
        return $this->getConfig()->exportConstraint;
    }
    
    /**
     * Defines which files should be handled by the "sync" task.
     *
     * @return \LaborDigital\T3tu\ExtConfigHandler\FileConstraint
     */
    public function syncConstraint(): FileConstraint
    {
        return $this->getConfig()->syncConstraint;
    }
    
    /**
     * @inheritDoc
     */
    public function finish(ConfigState $state): void
    {
        foreach ($this->configList as $extKey => $config) {
            $state->useNamespace('t3tu.' . $extKey, [$config, 'finish']);
        }
    }
    
    /**
     * Returns the concrete config object for the current
     *
     * @return \LaborDigital\T3tu\ExtConfigHandler\Configuration
     */
    protected function getConfig(): Configuration
    {
        if (! isset($this->configList[$this->context->getExtKey()])) {
            $this->configList[$this->context->getExtKey()] = GeneralUtility::makeInstance(Configuration::class);
        }
        
        return $this->configList[$this->context->getExtKey()];
    }
}