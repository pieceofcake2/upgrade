# CakePHP Legacy Upgrade Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/upgrade?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/upgrade?label=Packagist)](https://packagist.org/packages/pieceofcake2/upgrade)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/upgrade/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/upgrade/actions/workflows/CI.yml)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/upgrade/CI.yml?label=CI)](https://github.com/pieceofcake2/upgrade/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/upgrade?label=Coverage)](https://codecov.io/gh/pieceofcake2/upgrade)

The Upgrade plugin provides the `upgrade` console shell, which automates most of the
mechanical changes needed to upgrade a CakePHP 1.3 application to CakePHP 2 on
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp).

## Documentation

- Website: https://pieceofcake2.github.io/upgrade/ (日本語: https://pieceofcake2.github.io/upgrade/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

The Markdown documentation is also included in the Composer package, in the plugin's directory: `plugins/Upgrade/docs/README.md` (the location depends on your `installer-paths`). Install the package with `--dev` if you only need it for development.

## Requirements

- PHP 8.0+
- pieceofcake2/cakephp 2.12+
- rector/rector 2.7+ (installed with the plugin), for the `rector` command

## Installation

```bash
composer require --dev pieceofcake2/upgrade
```

Then load the plugin with `CakePlugin::load('Upgrade')` and run `bin/cake upgrade all`
(legacy layout: `app/Console/cake upgrade all`). Back up your application first.

`bin/cake upgrade rector` runs the [Rector](https://getrector.com/) rules of the plugin for a change of
pieceofcake2/cakephp, e.g. `--rules variadic-arguments src tests` or `--rules namespaced-classes src tests`. See [Rector Rules](docs/README.md#rector-rules).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package is based on the `upgrade` console shell of
[cakephp/cakephp](https://github.com/cakephp/cakephp) (`cakephp/cakephp` on Packagist, last upstream
version 2.10.24), originally by the CakePHP community (Cake Software Foundation, Inc.). It was
extracted into a separate package and is maintained for CakePHP 2 on modern PHP by
[pieceofcake2](https://github.com/pieceofcake2). Changes from the original are listed in the
[commit history](https://github.com/pieceofcake2/upgrade/commits/1.x).

## License

Licensed under the MIT License. See [LICENSE](LICENSE).
