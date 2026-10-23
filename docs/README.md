# Upgrade Documentation

The Upgrade plugin provides the `upgrade` console shell, which automates most of the
mechanical changes needed to upgrade a CakePHP 1.3 application to CakePHP 2. It runs on
[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp). It also moves an application or a plugin to
the CakePHP 5 layout of [pieceofcake2/app](https://github.com/pieceofcake2/app) and to namespaced classes, and
updates code for the changes of pieceofcake2/cakephp with [Rector](https://getrector.com/).

- [Installation](#installation)
- [Running the Shell](#running-the-shell)
- [Commands](#commands)
- [Options](#options)
- [Rector Rules](#rector-rules)
- [Layout and Namespace](#layout-and-namespace)
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
| `layout` | Move an application from the CakePHP 2 layout (`app/Config/`, `app/Controller/`, ...) to the layout of pieceofcake2/app (`config/`, `src/`, `templates/`, ...), or the configuration, templates, translations and tests of a plugin. Not part of `all`: see [Layout and Namespace](#layout-and-namespace). |
| `namespace` | Move the classes of an application or a plugin into a namespace (`App\`, or the name of the plugin), with `App.namespace` and PSR-4. Not part of `all`: see [Layout and Namespace](#layout-and-namespace). |

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
| `--no-short-names` | `class-name-strings`: keep the short names, see [`class-name-strings`](#class-name-strings). |

The rules list the places that they leave for a review after the changes, under `Review these places:`.

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
        if (get_class($this->response) === 'CakeResponse') {
            $this->set('plain', true);
        }
    }
}

// After
use Cake\Network\CakeRequest;
use Cake\Network\CakeResponse;
use Cake\Network\Email\CakeEmail;

class PostsController extends AppController
{
    public function beforeFilter()
    {
        if ($this->request instanceof CakeRequest) {
            $email = new CakeEmail('default');
        }
        // CakeResponse has subclasses in CakePHP
        if (get_class($this->response) === CakeResponse::class) {
            $this->set('plain', true);
        }
    }
}
```

- The classes of CakePHP in code (`extends`, `new`, type declarations, static calls, `instanceof`, `catch`) and in
  docblocks get a `use` statement, e.g. `@param Controller` with `use Cake\Controller\Controller;`. A name stays
  fully qualified when its short name already names another class in the file. The result is the same whichever
  version of pieceofcake2/cakephp is installed.
- A string with the global name of a class of CakePHP that is compared with the class of an object changes:
  `get_class()`, `get_parent_class()`, `get_called_class()`, `$object::class` or `static::class`, with `===`,
  `==`, `!==`, `!=`, `in_array()`, `switch` or `match`.
  - `get_class($x) === 'CakeRequest'` (also `!==`, `==`, `!=`, `$x::class` and `in_array(get_class($x), [...])`)
    becomes `$x instanceof CakeRequest` when no class of CakePHP and no class of the processed code extends
    `CakeRequest`: `instanceof` then matches the same objects. The classes of other packages (`vendor/`) are not
    checked: if one of them extends the class, change the `instanceof` back to a comparison.
  - Otherwise, e.g. for `Model`, `Component` or `CakeResponse`, which other classes extend, it stays a comparison
    with `::class`, `get_class($x) === CakeResponse::class`, which compares the same class as before; the command
    lists these places for a review. `get_parent_class()`, `get_called_class()`, `static::class`, `switch` and
    `match` always keep `::class`.
- Other strings (`App::uses('Controller', 'Controller')`, `'CakeRequest'` passed to a method) and the classes of
  your application and of plugins (`AppController`, `AppModel`) are left as they are.
- The changed code needs pieceofcake2/cakephp 2.13 or later. Run your code style fixer afterwards to sort the `use`
  statements.

### `class-name-strings`

The settings of CakePHP 2 name their classes in strings: `public $components = ['Auth']`. These rules replace
the class names by `::class`, with `use` statements, so that the code names the classes that it uses:

```php
// Before
public $components = ['Session', 'Auth' => ['authenticate' => ['Form']]];
public $uses = ['Post'];

Configure::write('Exception.renderer', 'AppExceptionRenderer');

// After
use App\Error\AppExceptionRenderer;
use App\Model\Post;
use Cake\Controller\Component\Auth\FormAuthenticate;
use Cake\Controller\Component\AuthComponent;
use Cake\Controller\Component\SessionComponent;

public $components = [SessionComponent::class, AuthComponent::class => ['authenticate' => [FormAuthenticate::class]]];
public $uses = [Post::class];

Configure::write('Exception.renderer', AppExceptionRenderer::class);
```

```bash
bin/cake upgrade rector --rules class-name-strings src tests config
```

- Only the places that take a class name change: `$components`, `$helpers`, `$actsAs`, `$uses`, `$tasks`, the
  associations of models (their keys, `className` and `with`, also of `bindModel()`), `$viewClass`, the Auth objects
  (`authenticate`, `authorize`, `passwordHasher`), the `engine` of `Cache::config()`, `CakeLog::config()`, the
  `Session` handler and the Text, Number and Time helpers, the `datasource` of `DATABASE_CONFIG` and
  `ConnectionManager::create()`, the `transport` of `EmailConfig` and `CakeEmail::transport()`,
  `Exception.renderer`, `Error.handler` and `Exception.handler` (`'ErrorHandler::handleError'` becomes
  `[ErrorHandler::class, 'handleError']`, also for the `consoleHandler`), `Dispatcher.filters`, `Acl.classname`,
  `routeClass` and `Router::defaultRouteClass()`, the `viewClassMap` of RequestHandlerComponent (its setting and
  `viewClassMap()`), `Components->load()`, `Helpers->load()`, `Behaviors->load()` (and `attach()`), `loadModel()`
  and `ClassRegistry::init()`. Other strings stay, e.g. `$this->Paginator->paginate('Post')`.
- A name becomes the class that CakePHP finds for it: the class of the application in `App.namespace`, the class of
  the plugin for `'Plugin.Name'`, or the class of CakePHP, in this order. Only namespaced classes are used, as CakePHP
  reads a class name without a backslash as a short name; names of global classes, and names that the processed code
  and CakePHP do not have, stay as they are. The `rector` command passes the `App.namespace` of the application.
- A key with a `className` is an alias and stays: `'Mailer' => ['className' => 'Greeting']` becomes
  `'Mailer' => ['className' => GreetingComponent::class]`. A class name as a key gets the short name as its alias,
  as before: `AuthComponent::class` is `$this->Auth`.
- Full class names (`'AppExceptionRenderer'`, `'DbAcl'`, the models `'Post'`) always change. Short names, to which
  CakePHP adds a suffix or a plugin (`'Auth'` for `AuthComponent`, `'DebugKit.Toolbar'`, `'Database/Mysql'`), change
  too, unless you pass `--no-short-names` (or set `$shortNames` to `false` in a copy of
  `config/rector/class-name-strings.php`). CakePHP looks a short name up when the application runs, so an application
  or a plugin could replace the class of CakePHP by a class of the same name; `::class` names one class for good.
- The changed code needs pieceofcake2/cakephp 2.13 or later.

## Layout and Namespace

The `layout` and `namespace` commands move an application or a plugin on pieceofcake2/cakephp to the CakePHP 5 layout
of [pieceofcake2/app](https://github.com/pieceofcake2/app), then its classes into a namespace. They are not part of
`all`. Run them in this order, and check and commit the result of each one:

```bash
# 1. The CakePHP 2 layout (app/Config/, app/Controller/, app/View/, ...) to config/, src/, templates/, ...
app/Console/cake upgrade layout --dry-run
app/Console/cake upgrade layout
composer dump-autoload

# 2. The classes into the App namespace
bin/cake upgrade namespace --dry-run
bin/cake upgrade namespace
composer dump-autoload
```

Run them from the root of the project, the directory that holds `app/` or `config/`. In a Git repository, the files
move with `git mv`, so that Git keeps their history; elsewhere they move with `rename()`, and the command says so. A
file is never overwritten: a move to an existing file is skipped and listed. `--dry-run` lists the moves and, for
`namespace`, prints the changes as a diff, without changing anything.

| Option | Description |
| --- | --- |
| `--plugin`, `-p` | Change the given plugin instead of the application. |
| `--namespace`, `-n` | `namespace` of an application: the namespace (default: `App`), e.g. `--namespace Acme`. A plugin always gets the namespace of its name. |
| `--no-short-names` | `namespace`: keep the short names of the settings, see [`class-name-strings`](#class-name-strings). |
| `--dry-run`, `-d` | Show the moves and the changes without making them. |

### `layout`

An application moves from the CakePHP 2 layout to the layout of pieceofcake2/app:

| From | To |
| --- | --- |
| `app/Config/` | `config/` |
| `app/Controller/`, `app/Model/`, `app/Lib/`, `app/Console/Command/`, `app/Console/Templates/` | `src/Controller/`, `src/Model/`, `src/Lib/`, `src/Console/Command/`, `src/Console/Templates/` |
| `app/View/Helper/` and the view classes of `app/View/` (`AppView.php`) | `src/View/Helper/`, `src/View/` |
| The templates of `app/View/` | `templates/` |
| `app/Locale/` | `resources/locales/` |
| `app/Test/Case/`, `app/Test/Fixture/` | `tests/TestCase/`, `tests/Fixture/` |
| `app/Plugin/<Name>/` | `plugins/<Name>/` |
| `app/Vendor/` | `vendors/`, where `App::import('Vendor', ...)` finds the hand-copied libraries and which Composer does not manage |
| `app/webroot/` (but `index.php` and `test.php`), `app/tmp/logs/`, `app/tmp/` | `webroot/`, `logs/`, `tmp/` |

The entry files of the CakePHP 2 layout define its paths, so the entry files of pieceofcake2/app replace them:
`config/define.php`, `bin/cake`, `webroot/index.php`, `webroot/test.php` and `webroot/.htaccess`, and the directories
of `tmp/` and `logs/`, are created when they are missing, also in an application already in the new layout. The plugin
ships copies of these files of pieceofcake2/app in `resources/skeleton/`, so the files do not depend on the version of
pieceofcake2/app. An existing file is never overwritten. The files use the global names of the classes of CakePHP
(`ShellDispatcher`, `Dispatcher`), which work in a global application; `namespace` imports them with `use`
statements.

- The constants of your own that the old entry files define (`app/webroot/index.php`, `app/webroot/test.php`,
  `app/Console/cake.php`), e.g. `define('SITE_NAME', 'Blog')`, are added to the new `config/define.php`, which all
  the entry files load. The constants of CakePHP 2 (`ROOT`, `APP_DIR`, `WEBROOT_DIR`, `WWW_ROOT`,
  `CAKE_CORE_INCLUDE_PATH`, ...) are not: the new files define them for the new layout, and Composer loads the core.
  A constant built on the path of the old file (`__FILE__`, `__DIR__`) is listed for you to define from `ROOT`.
- In a Git repository, the old entry files (`app/webroot/index.php`, `app/webroot/test.php`, `app/Console/cake`,
  `cake.php` and `cake.bat`) are removed with `git rm`; Git keeps them in its history, and refuses to remove a file
  with changes that are not committed, which then stays. Outside Git they stay, to be compared and deleted.

A plugin keeps its classes where they are, as the `src/` of a plugin holds namespaced classes only (they move into
`src/` with `namespace`): `Config/` moves to `config/`, the templates of `View/` to `templates/`, `Locale/` to
`resources/locales/` and `Test/` to `tests/`.

The templates get the names that pieceofcake2/cakephp 2.13 reads in `templates/`, also in the directories of themes
(`Themed/<Theme>/`) and of plugins (`Plugin/<Plugin>/`), and in a `templates/` that already exists:

- `.ctp` and `.thtml` become `.php`. The scaffold templates (`Scaffolds/`) keep `.ctp`, as ScaffoldView reads only
  `.ctp`.
- `Layouts/`, `Elements/`, `Emails/` and `Errors/` become `layout/`, `element/`, `email/` and `Error/`; the email
  layouts move from `Layouts/Emails/` to `layout/email/`.

The paths of `composer.json` in moved directories follow them: the `classmap`, `files`, `psr-0` and `psr-4` paths
of `autoload` and `autoload-dev` (`app/Controller/` becomes `src/Controller/`), and `extra.installer-paths`
(`app/Plugin/{$name}/` becomes `plugins/{$name}/`). A path whose files moved to different places, such as `app/` or
`app/View/`, is listed for you to change.

What you do by hand, as the command lists it:

- Compare the old entry files with the new ones for code of your own other than constants, and move it.
- Set the document root of the web server to `webroot/`: an `index.php` or `.htaccess` in the root of the project
  that leads to `app/webroot/` is listed.
- The files that do not move: `app/index.php`, `app/.htaccess` and the other files of `app/`. Install the libraries
  of `vendors/` with Composer when they have a package.
- Remove `$ext = '.thtml'` from your controllers and views when `.thtml` templates became `.php`.
- Paths in your code (`APP . 'Config' . DS`, `App::build()`), in your web server configuration, in `.gitignore`
  (`app/tmp/`) and in your deployment are not changed.

### `namespace`

`namespace` needs the layout of pieceofcake2/app: it stops on the CakePHP 2 layout (`app/Config/`, or the `Config/`,
`Locale/`, `Test/` or templates of `View/` of a plugin) and asks you to run `layout` first. The changed code needs
pieceofcake2/cakephp 2.13 or later.

The command moves the files:

- The class folders of a plugin (`Controller/`, `Model/`, `View/`, `Console/`, `Lib/`) move into `src/`.
- `src/Lib/<Type>/` moves to `src/<Type>/` for the types of CakePHP (`Cache`, `Configure`, `Console`, `Controller`,
  `Core`, `Error`, `Event`, `I18n`, `Log`, `Model`, `Network`, `Routing`, `TestSuite`, `Utility`, `View`): namespaced
  code has no `Lib`, so `src/Lib/Error/AppExceptionRenderer.php` becomes `App\Error\AppExceptionRenderer` in
  `src/Error/`. A file directly in `src/Lib/` stays, e.g. `App\Lib\MyTool`.
- A copy of a class of CakePHP in an application (a core class override, e.g. `src/Lib/Routing/Dispatcher.php`)
  moves to `src/Cake/<path>` (`src/Cake/Routing/Dispatcher.php`), keeps the namespace of CakePHP
  (`Cake\Routing`) and gets its `class_alias()`, as the files of CakePHP have. Compare it with the current file of
  CakePHP, as it may be a copy of an older version.

Then it changes the code with Rector:

- The namespace of a class comes from the path of its file: `App\Controller` for `src/Controller/`,
  `App\Test\TestCase\Controller` for `tests/TestCase/Controller/`, `App\Test\Fixture` for `tests/Fixture/`, and
  `<Plugin>\...` in a plugin, as CakePHP looks plugin classes up as `<Plugin>\<Type>\<Class>`.
- `App::uses()` is removed: Composer loads the classes.
- The uses of the classes of the application and of CakePHP, in the code and in docblocks, get the namespaced names,
  with `use` statements; the global classes that the code and the docblocks use (PHP, other packages,
  `AppController` in a plugin) are imported too (`use Exception;`, `use ArrayObject;` for `@param ArrayObject`). A
  name stays fully qualified when its short name names another class, e.g. a class of the namespace of the file.
- The strings that PHP reads as class names become `::class`: `class_exists()`, `is_a()`, `is_subclass_of()`,
  `method_exists()`, `new ReflectionClass()`, the callables `['Class', 'method']` and `'Class::method'` of
  `call_user_func()`, `array_map()` and the other functions, and the mocks and assertions of PHPUnit
  (`getMock()`, `getMockBuilder()`, `assertInstanceOf()`, `expectException()`, ...).
- The comparisons with the class of an object, as [`namespaced-classes`](#namespaced-classes) does them, for the
  classes of the application too.
- The class names of the settings, as [`class-name-strings`](#class-name-strings) does them. Short names that
  `App::className()` resolves keep working when you pass `--no-short-names`.
- `config/`, the entry files (`bin/cake`, the PHP files of `webroot/`) and the templates stay without a namespace,
  and get `use` statements for the classes of CakePHP and of the application that they name, e.g.
  `use Cake\Core\Configure;` for `Configure::write()`. The code does not change otherwise: only the names, with the
  class names of the settings. The use statements go after the docblock of the file, between blank lines.
- A template gets the use statements at the top, as in the templates of pieceofcake2/app: in its first block of PHP
  when it starts with one (after the docblock), else in a new block:

  ```php
  <?php
  use Cake\Core\Configure;
  ?>
  <h2><?php echo h($message); ?></h2>
  <?php if (Configure::read('debug') > 0) : ?>
      <?php echo $this->element('exception_stack_trace'); ?>
  <?php endif; ?>
  ```

  A template that names no class does not change, and the docblocks of the templates keep their names
  (`@var View $this`).
- In every file that the command changes, the `use` statements are sorted as the CakePHP coding standard expects
  (`SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses`), with the use statements that the file had: by name,
  part by part and without case, the classes before the functions and the constants.

And the configuration:

- `config/core.php` gets `Configure::write('App.namespace', 'App');` with a short docblock, just before the docblock
  of `App.encoding`, as CakePHP 5 sets the namespace before the encoding. It must come before
  `Cache::config('_cake_core_')`, which runs while `config/core.php` loads and finds its engine with
  `App::className()`. Without an `App.encoding` line it goes before the first `Cache::config()`; without either, the
  command asks you to add it. When `App.namespace` is already set, nothing is added, and the command tells you when
  the value differs. The rest of `config/core.php` stays as it is.
- `composer.json` autoloads `App\` from `src/` and `App\Test\` from `tests/` with PSR-4 (`autoload` and
  `autoload-dev`), instead of the classmap of these directories; a core class override adds `src/Cake/` to the
  classmap and the file of CakePHP to `exclude-from-classmap`. The other entries and the formatting of the file stay.
  A plugin gets PSR-4 in its own `composer.json`, or in the one of the application when it has none.

Namespace the plugins of your application before the application: the class names of the settings of the application
(`'Blog.Comment'`) can then name the namespaced classes of the plugins.

What `namespace` does not change, as the command lists it or as you check it:

- A file that also declares functions (a namespace would rename them), that has HTML outside the PHP tags or a
  `declare` block, or that is not valid PHP, stays global. So do the classes declared in more than one file: their
  uses in other files are not renamed.
- Code outside the application or plugin that names its classes by their global names, e.g. an application that uses
  the classes of a plugin namespaced after it, and strings that are not listed above (`'Post'` in `paginate()`,
  which CakePHP reads as a model alias).
- Data with the old class names: serialized objects in caches and sessions. Clear the caches.

Run `composer dump-autoload` and your tests afterwards, and review the changes with `git diff`.

## After the Upgrade

The shell creates the CakePHP 2 directory layout that uses `app/Config/`, `app/Controller/`,
`app/View/` and so on. Review the changes, run your tests, and fix what the shell cannot
convert automatically.

pieceofcake2/cakephp also supports a CakePHP 5-style layout (`config/`, `src/`,
`templates/`). To move the upgraded application to it, run `layout`, and `namespace` for namespaced classes: see
[Layout and Namespace](#layout-and-namespace).

## Origin

This package is based on the `upgrade` console shell of
[cakephp/cakephp](https://github.com/cakephp/cakephp) (`cakephp/cakephp` on Packagist, last upstream
version 2.10.24), originally by the CakePHP community (Cake Software Foundation, Inc.). It was
extracted into a separate package and is maintained for CakePHP 2 on modern PHP by
[pieceofcake2](https://github.com/pieceofcake2). Changes from the original are listed in the
[commit history](https://github.com/pieceofcake2/upgrade/commits/1.x).
