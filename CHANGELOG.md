# CHANGELOG

## Unreleased

- `namespace` removes `src/Lib/` (and `Lib/` of a plugin) after the moves to `src/<Type>/` when it holds nothing but
  placeholder files (`empty`, `.gitkeep`), with `git rm` in a Git repository, as namespaced code has no `Lib`. It
  stays with any other file, e.g. `src/Lib/Foo.php` (`App\Lib\Foo`)
  ([PR #25](https://github.com/pieceofcake2/upgrade/pull/25))
- `namespace` also changes the default files of `config/` (`email.php.default`, `database.php.default`) as the files
  of `config/`, e.g. `'transport' => 'Mail'` becomes `MailTransport::class`, so that a new `email.php` matches the
  changed configuration ([PR #25](https://github.com/pieceofcake2/upgrade/pull/25))

## v1.1.0 (2026-10-23)

- New `rector` command, which runs the Rector rules of the plugin for a change of pieceofcake2/cakephp: the sets
  `variadic-arguments` ([PR #20](https://github.com/pieceofcake2/upgrade/pull/20)) and `namespaced-classes`
  ([PR #21](https://github.com/pieceofcake2/upgrade/pull/21))
- New `layout` command: moves an application from the CakePHP 2 layout (`app/Config/`, `app/Controller/`,
  `app/View/`, ...) to the layout of pieceofcake2/app (`config/`, `src/`, `templates/`, `resources/locales/`,
  `tests/`, `plugins/`, `webroot/`), or the configuration, templates, translations and tests of a plugin
  (`--plugin`). The templates become `.php` in the CakePHP 5 directories (`layout/`, `element/`, `email/`, `Error/`),
  `app/Vendor/` moves to `vendors/`, and the paths of `composer.json` follow the moves. Files move with `git mv` in a
  Git repository. The missing entry files of pieceofcake2/app (`config/define.php`, `bin/cake`, `webroot/index.php`,
  `webroot/test.php`, `webroot/.htaccess`) are created from the copies in `resources/skeleton/`, with the constants
  of the application of the old entry files, which are removed with `git rm`
- New `namespace` command: moves the classes of an application (`App`, or `--namespace`) or of a plugin (its name)
  into a namespace. It moves the class folders of a plugin into `src/`, `src/Lib/<Type>/` to `src/<Type>/` and core
  class overrides to `src/Cake/<path>`, declares the namespaces from the paths, renames and imports the classes,
  removes `App::uses()`, replaces the class names in strings by `::class`, adds `App.namespace` to
  `config/core.php` and switches `composer.json` to PSR-4. It needs the layout of pieceofcake2/app and stops on the
  CakePHP 2 layout. `--dry-run` shows the changes as a diff
- `namespace` imports the classes of CakePHP and of the application with `use` statements in `config/`, the entry
  files (`bin/cake`, `webroot/index.php`, `webroot/test.php`) and the templates, which stay without a namespace. A
  template gets them at the top, also for the classes of its docblocks (`@var View $this`), in its first block of
  PHP or in a new `<?php ... ?>` block, as the templates of pieceofcake2/app; a template that names no class does not
  change. `layout` still creates the entry files with the global names, which work in a global application
  ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- `namespace` sorts the `use` statements of the files that it changes, with the ones that the files had, as the
  CakePHP coding standard expects (`SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses`)
  ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- `layout` also moves an application without `app/`, whose root is the directory `app/` of CakePHP 2 (`Config/`,
  `Controller/`, `View/`, `webroot/`, ... in the root), with directories that may be lowercase already. It tells it
  apart from a plugin and from the new layout, keeps `webroot/`, `tmp/` and the `vendor/` of Composer, moves `Plugin/`
  to `plugins/` with `extra.installer-paths` for the plugins that Composer installs, replaces the old
  `webroot/index.php` and `webroot/test.php` and a `Config/define.php` in the old form when Git has them, and keeps the
  entry files that the application already has in the new style, with the paths of the moved directories updated. The
  constants of the old entry files that are built on the paths of the old layout or defined under a condition are listed
  instead of copied. Run it with `Console/cake upgrade layout -root "$PWD" -app "$PWD"`, as the console looks for the
  application in `app/` of the directory of `composer.json`; without the options, `layout` changes nothing and prints
  this command
  ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- `namespace` no longer asks to update `autoload.psr-4: src/` by hand when it moves `src/Lib/<Type>/` to
  `src/<Type>/` ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- New `class-name-strings` rules: the class names in the settings that name a class (`$components`, `$helpers`,
  `$uses`, the associations, engines, handlers, `Dispatcher.filters`, `viewClassMap`, `Components->load()`,
  `Helpers->load()`, `Behaviors->load()`, `loadModel()`, ...) become `::class`. `--no-short-names` keeps the short
  names (`'Auth'`, `'DebugKit.Toolbar'`)
- `namespaced-classes`: a comparison with the class of an object (`get_class($x) === 'CakeRequest'`) becomes
  `instanceof` when no class of CakePHP or of the processed code extends the class; otherwise it keeps `::class`, and
  the command lists the place for a review. The names in docblocks get `use` statements, as the names of the code,
  whichever version of pieceofcake2/cakephp is installed
- Requires nikic/php-parser ^5.0, which `layout` and `namespace` use outside Rector
