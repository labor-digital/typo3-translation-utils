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
 * Last modified: 2020.07.22 at 22:44
 */

declare(strict_types=1);


namespace LaborDigital\T3TU\ImportExport;


use Neunerlei\Arrays\Arrays;
use Neunerlei\FileSystem\Fs;

class TranslationCsvFile
{
    /**
     * The name of the file
     *
     * @var string
     */
    public $filename;
    
    /**
     * Contains the rows of the csv file
     *
     * @var array
     */
    public $rows = [];
    
    /**
     * Dumps the rows into the file specified in $filename
     */
    public function write(): void
    {
        // Combine the rows into a string content
        $output = [];
        foreach ($this->rows as $row) {
            $rowOutput = [];
            foreach ($row as $field) {
                $rowOutput[] = '"' . str_replace('"', '""', $field) . '"';
            }
            $output[] = implode(';', $rowOutput);
        }
        $content = implode("\r\n", $output);
        
        // Dump the file to the disc
        Fs::writeFile($this->filename, utf8_decode($content));
    }
    
    /**
     * Loads the contents of $filename into the rows array
     */
    public function read(): void
    {
        $content    = Fs::readFile($this->filename);
        $this->rows = Arrays::makeFromCsv($content, false, ';');
    }
}
