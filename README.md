# CakePHP Legacy Upgrade Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/upgrade?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/upgrade?label=Packagist)](https://packagist.org/packages/pieceofcake2/upgrade)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/upgrade/CI.yml?label=CI)](https://github.com/pieceofcake2/upgrade/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/upgrade?label=Coverage)](https://codecov.io/gh/pieceofcake2/upgrade)

This plugin provides the `UpgradeShell` to help automate upgrading applications from CakePHP 1.3 to 2.0.

## Installation

### Using Composer

Install the plugin using Composer:

```bash
composer require --dev pieceofcake2/upgrade
```

## Usage

Load the plugin in your `app/Config/bootstrap.php`:

```php
CakePlugin::load('Upgrade');
```

Then run the upgrade shell:

```bash
# Run all upgrade commands
app/Console/cake upgrade all

# Run specific upgrade command
app/Console/cake upgrade tests
app/Console/cake upgrade locations
app/Console/cake upgrade i18n
app/Console/cake upgrade helpers
app/Console/cake upgrade basics
app/Console/cake upgrade request
app/Console/cake upgrade configure
app/Console/cake upgrade constants
app/Console/cake upgrade controller_redirects
app/Console/cake upgrade components
app/Console/cake upgrade exceptions
```

### Options

- `--plugin`, `-p`: The plugin to update. Only the specified plugin will be updated.
- `--ext`, `-e`: The extension(s) to search. A pipe delimited list, or a preg_match compatible subpattern (default: `php|ctp|thtml|inc|tpl`)
- `--git`, `-g`: Use git command for moving files around.
- `--dry-run`, `-d`: Dry run the update, no files will actually be modified.

### Important

**Be sure to have a backup of your application before running these commands.**

## Available Upgrade Commands

- `all` - Run all upgrade commands
- `tests` - Update tests class names to FooTest rather than FooTestCase
- `locations` - Move files and folders to their new homes
- `i18n` - Update the i18n translation method calls
- `helpers` - Update calls to helpers
- `basics` - Update removed basics functions to PHP native functions
- `request` - Update removed request access, and replace with $this->request
- `configure` - Update Configure::read() to Configure::read('debug')
- `constants` - Replace obsolete constants
- `controller_redirects` - Return early on controller redirect calls
- `components` - Update components to extend Component class
- `exceptions` - Replace use of cakeError with exceptions

## License

Licensed under The MIT License. See LICENSE file for details.
