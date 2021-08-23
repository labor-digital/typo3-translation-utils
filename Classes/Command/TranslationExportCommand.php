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


use InvalidArgumentException;
use LaborDigital\T3ba\Core\Di\ContainerAwareTrait;
use LaborDigital\T3ba\ExtConfigHandler\Command\ConfigureCliCommandInterface;
use LaborDigital\T3ba\Tool\TypoContext\TypoContext;
use LaborDigital\T3tu\File\Io\ConstraintApplier;
use LaborDigital\T3tu\ImportExport\TranslationExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationExportCommand extends Command implements ConfigureCliCommandInterface
{
    use ContainerAwareTrait;
    
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('t3tu:export');
        $this->setDescription('Exports the translation labels of an extension into a csv file');
        $this->addArgument('extension', InputArgument::REQUIRED,
            'The extension key to export the translations for');
        $this->addOption('format', 'f', InputOption::VALUE_OPTIONAL,
            'allows you to set the output format (default: csv), Options are: "csv", "xls", "xlsx" and "ods"', null);
    }
    
    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getService(ConstraintApplier::class)->setAction(ConstraintApplier::ACTION_EXPORT);
        $typoContext = $this->getService(TypoContext::class);
        
        $extKey = $input->getArgument('extension');
        
        $format = $input->getOption('format')
                  ?? $typoContext->config()->getConfigValue('t3tu.' . $extKey . '.export.defaultFormat', 'csv');
        $format = strtolower(ltrim($format, '.'));
        
        if (! in_array($format, ['csv', 'xls', 'xlsx', 'ods'])) {
            throw new InvalidArgumentException('The given format: ' . $format . ' is invalid!');
        }
        
        $output->writeln('Exporting translations for extension: ' . $extKey);
        $this->getService(TranslationExporter::class)->export($extKey, $format);
        $output->writeln('Done');
        
        return 0;
    }
}
