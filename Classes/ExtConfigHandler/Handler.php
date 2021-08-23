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
 * Last modified: 2021.08.23 at 09:06
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\ExtConfigHandler;


use LaborDigital\T3ba\ExtConfig\Abstracts\AbstractSimpleExtConfigHandler;
use Neunerlei\Configuration\Handler\HandlerConfigurator;

class Handler extends AbstractSimpleExtConfigHandler
{
    protected $configureMethod = 'configureTranslationUtils';
    
    /**
     * @var \LaborDigital\T3tu\ExtConfigHandler\TransUtilsConfigurator[]
     */
    protected $configurators = [];
    
    /**
     * @inheritDoc
     */
    public function configure(HandlerConfigurator $configurator): void
    {
        $this->registerDefaultLocation($configurator);
        $configurator->registerInterface(ConfigureTranslationUtilsInterface::class);
    }
    
    /**
     * @inheritDoc
     */
    protected function getConfiguratorClass(): string
    {
        return TransUtilsConfigurator::class;
    }
    
    /**
     * @inheritDoc
     */
    protected function getStateNamespace(): string
    {
        return 't3tu';
    }


//    /**
//     * @inheritDoc
//     */
//    public function prepare(): void { }
//
//    /**
//     * @inheritDoc
//     */
//    public function handle(string $class): void
//    {
//        $key = $this->context->getExtKey();
//        $configurator = $this->configurators[$key] ??
//                        ($this->configurators[$key] = $this->getInstanceWithoutDi(TransUtilsConfigurator::class));
//        /** @noinspection PhpUndefinedMethodInspection */
//        $class::configureTranslationUtils($configurator, $this->context);
//    }
//
//    /**
//     * @inheritDoc
//     */
//    public function finish(): void
//    {
//        $state = $this->context->getState();
//        foreach ($this->configurators as $extKey => $configurator) {
//            $state->useNamespace('t3tu.' . $extKey, [$configurator, 'finish']);
//        }
//    }
//
}