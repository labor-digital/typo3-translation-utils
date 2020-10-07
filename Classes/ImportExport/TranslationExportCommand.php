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
 * Last modified: 2020.07.22 at 17:30
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use InvalidArgumentException;
use LaborDigital\Typo3BetterApi\Container\TypoContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationExportCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('translation:export');
        $this->setDescription('Exports the translation labels of an extension into a csv file');
        $this->addArgument('extension', InputArgument::REQUIRED,
            'The extension key to export the translations for');
        $this->addOption('format', 'f', InputOption::VALUE_OPTIONAL,
            'allows you to set the output format (default: csv), Options are: "csv", "xls", "xlsx" and "ods"', 'csv');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $extKey = $input->getArgument('extension');
        $format = strtolower(ltrim($input->getOption('format'), '.'));

        if (! in_array($format, ['csv', 'xls', 'xlsx', 'ods'])) {
            throw new InvalidArgumentException('The given format: ' . $format . ' is invalid!');
        }

        $output->writeln('Exporting translations for extension: ' . $extKey);
        TypoContainer::getInstance()->get(TranslationExporter::class)->export($extKey, $format);
        $output->writeln('Done');
    }
}
