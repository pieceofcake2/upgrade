# Upgrade Documentation

The Upgrade plugin provides the `upgrade` console shell, which automates most of the
mechanical changes needed to upgrade a CakePHP 1.3 application to CakePHP 2. It runs on
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp).

- [Installation](#installation)
- [Running the Shell](#running-the-shell)
- [Commands](#commands)
- [Options](#options)
- [After the Upgrade](#after-the-upgrade)

> [!IMPORTANT]
> The shell moves and rewrites your files. Back up your application, or commit it to Git,
> before you run it, and try `--dry-run` first.

## Installation

Install the plugin with [Composer](https://getcomposer.org/) as a development dependency:

```bash
composer require --dev pieceofcake2/upgrade
```

The plugin has the `cakephp-plugin` type, so it is installed into your plugins directory
by `composer/installers`. Set `installer-paths` in your application's `composer.json` so it
is installed into the right place:

| Layout | `installer-paths` |
| --- | --- |
| Modern (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| Legacy (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`).
If you already use `CakePlugin::loadAll()`, this is not necessary:

```php
CakePlugin::load('Upgrade');
```

## Running the Shell

Run the shell with `bin/cake` (legacy layout: `app/Console/cake`):

```bash
# Run all upgrade commands
bin/cake upgrade all

# See what would be changed without changing anything
bin/cake upgrade all --dry-run

# Run a single command
bin/cake upgrade locations
```

Run it from the directory of the application you upgrade, with that application as the
console's application (`APP`): `locations` moves folders relative to the current directory,
and the other commands rewrite the PHP files of the application, or of the plugin given
with `--plugin`.

## Commands

| Command | Description |
| --- | --- |
| `all` | Run all the other commands. |
| `tests` | Rename test classes from `FooTestCase` to `FooTest`. |
| `locations` | Move files and folders to their CakePHP 2 locations (`config` to `Config`, `models` to `Model`, `views` to `View`, `tests/cases` to `Test/Case`, and so on), including the plugins in `plugins/`. |
| `i18n` | Update the calls of the translation functions: `__('text', true)` becomes `__('text')`, and calls that printed directly get an `echo`. |
| `helpers` | Update the calls to helpers in views to `$this->Helper`. |
| `basics` | Replace removed functions of `basics.php` with native PHP functions. |
| `request` | Replace the removed request properties (`$this->data`, `$this->params`, ...) with `$this->request`. |
| `configure` | Replace `Configure::read()` with `Configure::read('debug')`. |
| `constants` | Replace obsolete constants such as `CONFIGS` and `CONTROLLERS` with paths built from `APP`. |
| `controller_redirects` | Add `return` to `$this->redirect()` calls in controllers. |
| `components` | Make components extend the `Component` class. |
| `exceptions` | Replace `cakeError()` calls with exceptions. |

## Options

| Option | Description |
| --- | --- |
| `--plugin`, `-p` | Update only the given plugin. |
| `--ext`, `-e` | Extensions of the files to update: a pipe-delimited list or a `preg_match()` compatible subpattern (default: `php\|ctp\|thtml\|inc\|tpl`). |
| `--git`, `-g` | Move files with `git mv`, so that Git keeps their history. The current directory must be a Git repository. |
| `--dry-run`, `-d` | Show the changes without modifying any file. |

## After the Upgrade

The shell creates the CakePHP 2 directory layout that uses `app/Config/`, `app/Controller/`,
`app/View/` and so on. Review the changes, run your tests, and fix what the shell cannot
convert automatically.

pieceofcake2/cakephp also supports a CakePHP 5-style layout (`config/`, `src/`,
`templates/`). To move the upgraded application to it, follow the file migration map of the
[pieceofcake2/app](https://github.com/pieceofcake2/app) skeleton.
