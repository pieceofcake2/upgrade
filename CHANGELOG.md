# CHANGELOG

## Unreleased

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
  template gets them at the top, in its first block of PHP or in a new `<?php ... ?>` block, as the templates of
  CakePHP 5; a template that names no class does not change. `layout` still creates the entry files with the global
  names, which work in a global application
  ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- `namespace` sorts the `use` statements of the files that it changes, with the ones that the files had, as the
  CakePHP coding standard expects (`SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses`)
  ([PR #23](https://github.com/pieceofcake2/upgrade/pull/23))
- New `class-name-strings` rules: the class names in the settings that name a class (`$components`, `$helpers`,
  `$uses`, the associations, engines, handlers, `Dispatcher.filters`, `viewClassMap`, `Components->load()`,
  `Helpers->load()`, `Behaviors->load()`, `loadModel()`, ...) become `::class`. `--no-short-names` keeps the short
  names (`'Auth'`, `'DebugKit.Toolbar'`)
- `namespaced-classes`: a comparison with the class of an object (`get_class($x) === 'CakeRequest'`) becomes
  `instanceof` when no class of CakePHP or of the processed code extends the class; otherwise it keeps `::class`, and
  the command lists the place for a review. The names in docblocks get `use` statements, as the names of the code,
  whichever version of pieceofcake2/cakephp is installed
- Requires nikic/php-parser ^5.0, which `layout` and `namespace` use outside Rector
