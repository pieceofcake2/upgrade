# Upgrade Documentation

The Upgrade plugin provides the `upgrade` console shell, which automates most of the
mechanical changes needed to upgrade a CakePHP 1.3 application to CakePHP 2. It runs on
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp).

- [Installation](#installation)
- [Running the Shell](#running-the-shell)
- [Commands](#commands)
- [Options](#options)
- [Rector Rules](#rector-rules)
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
| `rector` | Run a set of Rector rules for a change of pieceofcake2/cakephp. Not part of `all`: see [Rector Rules](#rector-rules). |

## Options

| Option | Description |
| --- | --- |
| `--plugin`, `-p` | Update only the given plugin. |
| `--ext`, `-e` | Extensions of the files to update: a pipe-delimited list or a `preg_match()` compatible subpattern (default: `php\|ctp\|thtml\|inc\|tpl`). |
| `--git`, `-g` | Move files with `git mv`, so that Git keeps their history. The current directory must be a Git repository. |
| `--dry-run`, `-d` | Show the changes without modifying any file. |

## Rector Rules

The `rector` command runs a set of [Rector](https://getrector.com/) rules on the directories or files that you
give, for a change of pieceofcake2/cakephp that your code must follow. It is not part of `all`, which upgrades from
CakePHP 1.3.

```bash
# See the changes
bin/cake upgrade rector --rules variadic-arguments src tests --dry-run

# Apply them
bin/cake upgrade rector --rules variadic-arguments src tests
```

| Option | Description |
| --- | --- |
| `--rules`, `-r` | The set of rules, a file of `config/rector/` of the plugin (default: `variadic-arguments`). |
| `--dry-run`, `-d` | Show the changes without modifying any file. |

### `variadic-arguments`

pieceofcake2/cakephp declares the variable arguments of some methods as variadic parameters instead of reading them
with `func_get_args()`, e.g. `Model::query($sql, ...$params)` (see the UPGRADE of pieceofcake2/cakephp for the
list). PHP refuses a method that overrides one of them with fewer parameters, so the rules add the same variadic
parameter to the methods of your classes that override them:

```php
// Before
class AppModel extends Model
{
    public function query($sql)
    {
        return parent::query(...func_get_args());
    }
}

// After
class AppModel extends Model
{
    public function query($sql, ...$params)
    {
        return parent::query($sql, ...$params);
    }
}
```

- `func_get_args()` becomes the list of the parameters when they are all required. With an optional parameter, it
  stays: it leaves out the optional parameters that are not passed, so a list would pass their defaults instead.
  It returns the same values with the variadic parameter.
- The variadic parameter takes the name of CakePHP, or another one when the method already has a parameter or a
  variable of that name.
- The changed code also works with the versions of pieceofcake2/cakephp before the change, so you can run the rules
  before you update.

### `namespaced-classes`

Since CakePHP 2.13, the classes of pieceofcake2/cakephp are in the `Cake` namespace, e.g.
`Cake\Controller\Controller`, and their global names are aliases of the same classes. The global names keep
working, so these rules are optional: they replace the global names in your code by the namespaced names, with
`use` statements, and fix the comparisons of class names that `get_class()` breaks:

```php
// Before
class PostsController extends AppController
{
    public function beforeFilter()
    {
        if (get_class($this->request) === 'CakeRequest') {
            $email = new CakeEmail('default');
        }
    }
}

// After
use Cake\Network\CakeRequest;
use Cake\Network\Email\CakeEmail;

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if (get_class($this->request) === CakeRequest::class) {
            $email = new CakeEmail('default');
        }
    }
}
```

- The classes of CakePHP in code (`extends`, `new`, type declarations, static calls, `instanceof`, `catch`) get a
  `use` statement; in docblocks they become fully qualified names, e.g. `@param \Cake\Controller\Controller`.
- A string with the global name of a class of CakePHP is replaced by `::class` when it is compared with the class of
  an object: `get_class()`, `get_parent_class()`, `get_called_class()`, `$object::class` or `static::class`, with
  `===`, `==`, `!==`, `!=`, `in_array()`, `switch` or `match`. `get_class($x) === 'CakeRequest'` then compares the
  same class as before; `instanceof` would also match the subclasses, so it is not used.
- Other strings (`App::uses('Controller', 'Controller')`, `'CakeRequest'` passed to a method) and the classes of
  your application and of plugins (`AppController`, `AppModel`) are left as they are.
- The changed code needs pieceofcake2/cakephp 2.13 or later. Run your code style fixer afterwards to sort the `use`
  statements.
- If you use PHPStan, update pieceofcake2/phpstan-cakephp2 to `^1.1` together with pieceofcake2/cakephp 2.13
  (`composer require --dev pieceofcake2/phpstan-cakephp2:^1.1`). Version 1.0 does not resolve the components,
  models, tasks and behaviors of the namespaced core, and reports errors such as
  `Call to an undefined method Cake\Controller\Component::paginate()`.

## After the Upgrade

The shell creates the CakePHP 2 directory layout that uses `app/Config/`, `app/Controller/`,
`app/View/` and so on. Review the changes, run your tests, and fix what the shell cannot
convert automatically.

pieceofcake2/cakephp also supports a CakePHP 5-style layout (`config/`, `src/`,
`templates/`). To move the upgraded application to it, follow the file migration map of the
[pieceofcake2/app](https://github.com/pieceofcake2/app) skeleton.

## Origin

This package is based on the `upgrade` console shell of
[cakephp/cakephp](https://github.com/cakephp/cakephp) (`cakephp/cakephp` on Packagist, last upstream
version 2.10.24), originally by the CakePHP community (Cake Software Foundation, Inc.). It was
extracted into a separate package and is maintained for CakePHP 2 on modern PHP by
[pieceofcake2](https://github.com/pieceofcake2). Changes from the original are listed in the
[commit history](https://github.com/pieceofcake2/upgrade/commits/1.x).
