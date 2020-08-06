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
 * Last modified: 2020.07.22 at 22:28
 */

declare(strict_types=1);

namespace LaborDigital\T3TU\Sync;

use LaborDigital\Typo3BetterApi\Container\TypoContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TranslationSyncCommand extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('translation:sync');
        $this->setHelp('Synchronizes the translation files of a given extension');
        $this->addArgument('extension', InputArgument::REQUIRED,
            'The extension key to synchronize the translations for');
    }
    
    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $extKey = $input->getArgument('extension');
        $output->writeln('Syncing translations for extension: ' . $extKey);
        TypoContainer::getInstance()->get(TranslationSynchronizer::class)->synchronize($extKey);
        $output->writeln('Done');
    }
}
