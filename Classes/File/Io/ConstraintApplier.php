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
 * Last modified: 2021.08.23 at 11:21
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\File\Io;


use LaborDigital\T3ba\Core\Di\PublicServiceInterface;
use LaborDigital\T3ba\Tool\Cache\CacheConsumerInterface;
use LaborDigital\T3ba\Tool\Cache\CacheInterface;
use LaborDigital\T3ba\Tool\OddsAndEnds\SerializerUtil;
use LaborDigital\T3ba\Tool\TypoContext\TypoContext;

class ConstraintApplier implements PublicServiceInterface, CacheConsumerInterface
{
    public const ACTION_IMPORT = 'import';
    public const ACTION_EXPORT = 'export';
    public const ACTION_SYNC = 'sync';
    
    /**
     * @var \LaborDigital\T3ba\Tool\TypoContext\TypoContext
     */
    protected $typoContext;
    
    /**
     * The action for which we should check the constraints
     *
     * @var string
     */
    protected $action = self::ACTION_SYNC;
    
    /**
     * @var \LaborDigital\T3ba\Tool\Cache\CacheInterface
     */
    protected $runtimeCache;
    
    public function __construct(TypoContext $typoContext, CacheInterface $runtimeCache)
    {
        $this->typoContext = $typoContext;
        $this->runtimeCache = $runtimeCache;
    }
    
    /**
     * Used to determine the action for which the constraints should be looked up.
     *
     * @param   string  $action  one of the ACTION_ constants of this class
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }
    
    /**
     * Checks if a language is allowed to be used for a given product name based on the provided action
     *
     * @param   string  $productName  The product name / extension key to check for
     * @param   string  $language     The language code that should be checked
     *
     * @return bool
     */
    public function isLanguageAllowed(string $productName, string $language): bool
    {
        $language = strtolower(trim($language));
        
        $config = $this->findConfig($productName);
        if (isset($config['allowed']['languages'])) {
            return in_array($language, $config['allowed']['languages'], true);
        }
        
        if (isset($config['ignored']['languages']) && is_array($config['ignored']['languages'])) {
            return ! in_array($language, $config['ignored']['languages'], true);
        }
        
        return true;
    }
    
    /**
     * Checks if a single file or file permutation is allowed to be used in the provided action
     *
     * @param   string       $productName  The product name / extension key to check for
     * @param   string       $filename     The filename that should be checked for
     * @param   string|null  $language     Optional language to narrow the constraint down
     *
     * @return bool
     */
    public function isFileAllowed(string $productName, string $filename, ?string $language): bool
    {
        $filename = basename($filename);
        
        if (strpos($filename, '.') === 2) {
            $filename = substr($filename, 3) . '';
        }
        
        if (str_ends_with(strtolower($filename), '.xlf')) {
            $filename = substr($filename, 0, -4);
        }
        
        $config = $this->findConfig($productName);
        if (! empty($language) && ! $this->isLanguageAllowed($productName, $language)) {
            return isset($config['allowed']['files'][$language]) &&
                   in_array($filename, $config['allowed']['files'][$language], true);
        }
        
        $language = strtolower(trim($language ?? '-1'));
        if (isset($config['allowed']['files'][$language]) &&
            in_array($filename, $config['allowed']['files'][$language], true)) {
            return true;
        }
        
        if (isset($config['allowed']['files']['*'])) {
            return in_array($filename, $config['allowed']['files']['*'], true);
        }
        
        if (isset($config['ignored']['files'][$language]) &&
            in_array($filename, $config['ignored']['files'][$language], true)) {
            return false;
        }
        
        if (isset($config['ignored']['files']['*']) &&
            in_array($filename, $config['ignored']['files']['*'], true)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Finds the configuration array for a certain extension/product name
     *
     * @param   string  $productName
     *
     * @return array
     */
    protected function findConfig(string $productName): array
    {
        return $this->runtimeCache->remember(function () use ($productName) {
            $config = $this->typoContext->config()->getConfigValue('t3tu.' . $productName . '.' . $this->action);
            if (is_array($config) && isset($config['constraint'])) {
                return SerializerUtil::unserializeJson($config['constraint']);
            }
            
            return [];
        }, [__CLASS__, $productName]);
    }
}