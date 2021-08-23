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
 * Last modified: 2021.08.22 at 21:41
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\Command;


use LaborDigital\T3ba\Core\Di\ContainerAwareTrait;
use LaborDigital\T3ba\ExtConfigHandler\Command\ConfigureCliCommandInterface;
use LaborDigital\T3tu\File\Io\ConstraintApplier;
use LaborDigital\T3tu\ImportExport\TranslationImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationImportCommand extends Command implements ConfigureCliCommandInterface
{
    use ContainerAwareTrait;
    
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('t3tu:import');
        $this->setDescription('Imports the csv files of a translation into xlf translation files');
        $this->addArgument('extension', InputArgument::REQUIRED,
            'The extension key to export the translations for');
    }
    
    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getService(ConstraintApplier::class)->setAction(ConstraintApplier::ACTION_IMPORT);
        
        $extKey = $input->getArgument('extension');
        $output->writeln('Importing translations for extension: ' . $extKey);
        $this->getService(TranslationImporter::class)->import($extKey);
        $output->writeln('Done');
        
        return 0;
    }
}
