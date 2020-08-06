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
 * Last modified: 2020.07.23 at 09:51
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use LaborDigital\Typo3BetterApi\Container\TypoContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationImportCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('translation:import');
        $this->setDescription('Imports the csv files of a translation into xlf translation files');
        $this->addArgument('extension', InputArgument::REQUIRED,
            'The extension key to export the translations for');
    }
    
    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $extKey = $input->getArgument('extension');
        $output->writeln('Importing translations for extension: ' . $extKey);
        TypoContainer::getInstance()->get(TranslationImporter::class)->import($extKey);
        $output->writeln('Done');
    }
}
