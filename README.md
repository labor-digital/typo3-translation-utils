# TYPO3 - Translation Utils
A simple development helper package which provides some command line utilities to work with translation files.

**A word of caution:** This is a development helper and should not be installed on a production machine!

## Requirements

- TYPO3 v9
- [TYPO3 - Better API](https://github.com/labor-digital/typo3-better-api)
- Installation using Composer

## Installation
Install this package using composer:

```
composer require labor-digital/typo3-translation-utils --dev
```

After that, you can activate the extension in the Extensions-Manager of your TYPO3 installation

## Documentation
#### translation:export <EXT_KEY>
Exports the translation labels of an extension into a csv file. A csv file will be created
for every base translation file in your extension. Meaning locallang.xlf will be exported into locallang.csv,
while locallang_be.xlf will be exported into locallang_be.csv.

The script will load all translations in your directory, that are named de.locallang.xlf, es.locallang.xlf,... and
put the labels into their own column of the file.

With this you can easily provide your Translators an overview of all translations and their matching key.

##### Option: --format | -f
Allows you to change the output format of the translations.
By default a .csv file is exported. With this option you can also set it to .xls, .xlsx or .ods,
depending on your needs

#### translation:import <EXT_KEY>
Imports the csv files of a translation into xlf translation files. This is basically the reverse operation to "export".
After you changed the content in your csv files in your translation directory.
This command will read all the csv files and update/create the required translation xlf files.

**NOTE**
If the script finds .xls, .xlsx or .ods files in your language directory, it will parse them in the
same way it does .csv files. This is helpful if you have issues with UTF-8 encoded chars in your
excel file

#### translation:sync <EXT_KEY>
Synchronizes all translation files (e.g. de.locallang.xlf,...) with your origin file (e.g. locallang.xlf) and vice versa.

The sync uses the origin file, as "source of truth", meaning all translation keys you add or remove there will be added/removed to the matching translation files.
All newly created entries in the translation files are prefixed with COPY FROM - $SOURCELANG: to make them easy to spot.

If you rename a translation key in the origin file, the keys in the translation files will be updated automatically to match.
(ONLY if the "source" tag matches both the origin file and the translation file, otherwise the key in the translation file will be recreated!).

The sync will automatically update the "source" tag in your translation files if it was changed in the origin file.

The sync will also make sure that all source files, have the same language variants available to them.
This means in practice, if you start with:

- locallang.xlf
- de.locallang.xlf
- locallang_be.xlf
- es.locallang_be.xlf

After the sync you will get something like this:

- locallang.xlf
- de.locallang.xlf
- es.locallang.xlf
- locallang_be.xlf
- de.locallang_be.xlf
- es.locallang_be.xlf

You can also introduce a new language variant by adding an empty file for your language. The file gets automatically prepared with a dummy content and added to all existing origin files.

## Postcardware
You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: LABOR.digital - Fischtorplatz 21 - 55116 Mainz, Germany

We publish all received postcards on our [company website](https://labor.digital).
