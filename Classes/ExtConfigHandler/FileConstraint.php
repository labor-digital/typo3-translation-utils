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
 * Last modified: 2021.08.23 at 09:15
 */

declare(strict_types=1);


namespace LaborDigital\T3tu\ExtConfigHandler;


use InvalidArgumentException;
use Neunerlei\Arrays\Arrays;

class FileConstraint
{
    protected $ignoredFiles = [];
    protected $allowedFiles = [];
    protected $ignoredLanguages = [];
    protected $allowedLanguages = [];
    
    /**
     * Registers a single .xlf file inside the translation directory as being ignored by actions.
     *
     * @param   string       $filename  The basename of the file to be ignored. Can have a language prefix.
     * @param   string|null  $language  An optional language in which this file should be ignored.
     *                                  If left empty the file will be ignored in all languages
     *
     * @return $this
     */
    public function addIgnoredFile(string $filename, ?string $language = null): self
    {
        [$filename, $language] = $this->parseFilename($filename, $language);
        if (isset($this->allowedFiles[$language][$filename])) {
            throw new InvalidArgumentException(
                'Could not register file: ' . $filename . ' in language: ' . $language .
                ' to be ignored, because it was registered as being allowed!');
        }
        $this->ignoredFiles[$language][$filename] = true;
        
        return $this;
    }
    
    /**
     * Removes a previously ignored translation file from the ignore-list, allowing it to be used in actions again.
     *
     * @param   string       $filename  The basename of the file to be allowed again. Can have a language prefix.
     * @param   string|null  $language  An optional language in which this file should be allowed again.
     *                                  If left empty the file will be allowed again in all languages
     *
     * @return $this
     */
    public function removeIgnoredFile(string $filename, ?string $language = null): self
    {
        [$filename, $language] = $this->parseFilename($filename, $language);
        if ($language === '*') {
            foreach ($this->ignoredFiles as &$files) {
                unset($files[$filename]);
            }
        } else {
            unset($this->ignoredFiles[$language][$filename]);
        }
        
        return $this;
    }
    
    /**
     * Registers a single .xlf file inside the translation directory as being allowed in actions.
     * If at least one file in a language was registered as "allowed", all other files/languages will be ignored by actions.
     *
     * @param   string       $filename  The basename of the file to be allowed. Can have a language prefix.
     * @param   string|null  $language  An optional language in which this file should be allowed.
     *                                  If left empty the file will be allowed in all languages
     *                                  Can be a comma separated list of multiple languages.
     *
     * @return $this
     */
    public function addAllowedFile(string $filename, ?string $language = null): self
    {
        if (is_string($language) && str_contains($language, ',')) {
            foreach (Arrays::makeFromStringList($language) as $lang) {
                $this->addAllowedFile($filename, $lang);
            }
            
            return $this;
        }
        
        [$filename, $language] = $this->parseFilename($filename, $language);
        if (isset($this->ignoredFiles[$language][$filename])) {
            throw new InvalidArgumentException(
                'Could not register file: ' . $filename . ' in language: ' . $language .
                ' to be allowed, because it was registered as being ignored!');
        }
        $this->allowedFiles[$language][$filename] = true;
        
        return $this;
    }
    
    /**
     * Removes a previously ignored translation file from the allow-list, allowing it to be used in actions again.
     * If all files have been removed from the explicit allow-list the ignore-list takes over again.
     *
     * @param   string       $filename  The basename of the file to be removed. Can have a language prefix.
     * @param   string|null  $language  An optional language in which this file should be removed.
     *                                  If left empty the file will be removed in all languages
     *                                  Can be a comma separated list of multiple languages.
     *
     * @return $this
     */
    public function removeAllowedFile(string $filename, ?string $language = null): self
    {
        if (is_string($language) && str_contains($language, ',')) {
            foreach (Arrays::makeFromStringList($language) as $lang) {
                $this->removeAllowedFile($filename, $lang);
            }
            
            return $this;
        }
        
        [$filename, $language] = $this->parseFilename($filename, $language);
        if ($language === '*') {
            foreach ($this->allowedFiles as &$files) {
                unset($files[$filename]);
            }
        } else {
            unset($this->allowedFiles[$language][$filename]);
        }
        
        return $this;
    }
    
    /**
     * Registers all files in the given language as ignored from actions
     *
     * @param   string  $language  The two char language code of the language
     *                             Can be a comma separated list of multiple languages.
     *
     * @return $this
     */
    public function addIgnoredLanguage(string $language): self
    {
        if (str_contains($language, ',')) {
            array_map([$this, 'addIgnoredLanguage'], Arrays::makeFromStringList($language));
            
            return $this;
        }
        
        $language = strtolower(trim($language));
        if (isset($this->allowedLanguages[$language])) {
            throw new InvalidArgumentException(
                'Could not register language: ' . $language . ' to be ignored, because it was registered as being allowed!');
        }
        $this->ignoredLanguages[$language] = true;
        
        return $this;
    }
    
    /**
     * Removes the given language from being ignored by actions
     *
     * @param   string  $language  The two char language code of the language
     *
     * @return $this
     */
    public function removeIgnoredLanguage(string $language): self
    {
        $language = strtolower(trim($language));
        unset($this->ignoredLanguages[$language]);
        
        return $this;
    }
    
    /**
     * The "allow" list will always win over the "ignore" list, so as soon as you add a language
     * to this list, all other languages will be ignored, except if specified using the addAllowedFile() method.
     *
     * @param   string  $language  The two char language code of the language
     *                             Can be a comma separated list of multiple languages.
     *
     * @return $this
     */
    public function addAllowedLanguage(string $language): self
    {
        if (str_contains($language, ',')) {
            array_map([$this, 'addAllowedLanguage'], Arrays::makeFromStringList($language));
            
            return $this;
        }
        
        $language = strtolower(trim($language));
        if (isset($this->ignoredLanguages[$language])) {
            throw new InvalidArgumentException(
                'Could not register language: ' . $language . ' to be allowed, because it was registered as being ignored!');
        }
        $this->allowedLanguages[$language] = true;
        
        return $this;
    }
    
    /**
     * Removes the given language from the allow-list.
     *
     * @param   string  $language  The two char language code of the language
     *
     * @return $this
     */
    public function removeAllowedLanguage(string $language): self
    {
        $language = strtolower(trim($language));
        unset($this->allowedLanguages[$language]);
        
        return $this;
    }
    
    /**
     * Returns the configuration as a raw array.
     *
     * @return array
     * @internal
     */
    public function getConfig(): ?array
    {
        $result = array_filter(
            array_map(
                'array_filter',
                [
                    'allowed' => array_filter([
                        'files' => array_map('array_keys', array_filter($this->allowedFiles)),
                        'languages' => array_keys($this->allowedLanguages),
                    ]),
                    'ignored' => array_filter([
                        'files' => array_map('array_keys', array_filter($this->ignoredFiles)),
                        'languages' => array_keys($this->ignoredLanguages),
                    ]),
                ]
            )
        );
        
        return empty($result) ? null : $result;
    }
    
    /**
     * Parses the filename and finds the language it applies to
     *
     * @param   string       $filename
     * @param   string|null  $language
     *
     * @return array<string,string>
     */
    protected function parseFilename(string $filename, ?string $language): array
    {
        $filename = basename($filename);
        
        if ($language === null && strpos($filename, '.') === 2) {
            $language = substr($filename, 0, 2) . '';
            $filename = substr($filename, 3) . '';
        }
        
        if (str_ends_with(strtolower($filename), '.xlf')) {
            $filename = substr($filename, 0, -4);
        }
        
        $language = $language ?? '*';
        
        return [$filename, strtolower(trim($language))];
    }
}