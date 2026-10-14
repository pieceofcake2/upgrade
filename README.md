# CakePHP Legacy Upgrade Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/upgrade?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/upgrade?label=Packagist)](https://packagist.org/packages/pieceofcake2/upgrade)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/upgrade/actions/workflows/CI.yml)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/upgrade/CI.yml?label=CI)](https://github.com/pieceofcake2/upgrade/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/upgrade?label=Coverage)](https://codecov.io/gh/pieceofcake2/upgrade)

The Upgrade plugin provides the `upgrade` console shell, which automates most of the
mechanical changes needed to upgrade a CakePHP 1.3 application to CakePHP 2 on
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp).

## Requirements

- PHP 8.0+
- pieceofcake2/cakephp 2.12+

## Installation

```bash
composer require --dev pieceofcake2/upgrade
```

Then load the plugin with `CakePlugin::load('Upgrade')` and run `bin/cake upgrade all`
(legacy layout: `app/Console/cake upgrade all`). Back up your application first.

## Documentation

Documentation: https://pieceofcake2.github.io/upgrade/

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## License

Licensed under the MIT License. See [LICENSE](LICENSE).
