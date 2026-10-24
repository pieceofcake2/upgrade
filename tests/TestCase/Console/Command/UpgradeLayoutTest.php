<?php
/**
 * UpgradeLayoutTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

require_once __DIR__ . DS . 'UpgradeCommandTestCase.php';

/**
 * `bin/cake upgrade layout`: the moves from the CakePHP 2 layout to the layout of pieceofcake2/app
 */
class UpgradeLayoutTest extends UpgradeCommandTestCase
{
    /**
     * An application in the CakePHP 2 layout becomes `tests/fixtures/app`: the configuration in `config/`, the
     * classes in `src/`, the templates in `templates/` with the names of CakePHP 5, and the paths of composer.json
     *
     * @return void
     */
    public function testApplication(): void
    {
        $app = $this->_copy('legacy-app', 'app');

        $this->_run($app, ['layout']);

        $this->_assertSameTree($this->_fixture('app'), $app);
        $output = $this->out->output;
        $this->assertStringContainsString('Move app/Config to config', $output);
        $this->assertStringContainsString('Move app/View/Layouts/Emails/html/default.ctp to templates/layout/email/html/default.php', $output);
        $this->assertStringContainsString('Move app/View/Posts/view.thtml to templates/Posts/view.php', $output);
        $this->assertStringContainsString('extra.installer-paths: app/Plugin/{$name}/ => plugins/{$name}/', $output);
        // The entry files of pieceofcake2/app, with the constants of the application of the old ones
        foreach (['config/define.php', 'bin/cake', 'webroot/index.php', 'webroot/test.php', 'webroot/.htaccess'] as $file) {
            $this->assertStringContainsString('Create ' . $file . ' of pieceofcake2/app', $output);
        }
        $this->assertFileEquals(dirname(__DIR__, 4) . '/resources/skeleton/webroot/index.php', $app . '/webroot/index.php');
        $this->assertTrue(is_executable($app . '/bin/cake'));
        $this->assertStringContainsString('Keep the constant SITE_NAME of app/webroot/index.php in config/define.php', $output);
        $this->assertStringContainsString('Keep the constant CONSOLE_USER of app/Console/cake.php in config/define.php', $output);
        $this->assertStringContainsString("// Kept from app/webroot/index.php\nif (!defined('SITE_NAME')) {\n    define('SITE_NAME', 'Acme Blog');\n}\n", (string)file_get_contents($app . '/config/define.php'));
        $this->assertStringContainsString('app/webroot/index.php defines UPLOADS from its own path', $output);
        $this->assertStringNotContainsString('CAKE_CORE_INCLUDE_PATH', (string)file_get_contents($app . '/config/define.php'));
        // Outside Git, the old entry files stay to be compared
        $this->assertStringContainsString('Delete app/webroot/index.php, app/webroot/test.php, app/Console/cake, app/Console/cake.php, app/Console/cake.bat, which the files of pieceofcake2/app replace', $output);
        foreach (['tmp/cache/models', 'tmp/cache/views', 'tmp/sessions', 'tmp/tests'] as $dir) {
            $this->assertStringContainsString('Create the directory ' . $dir, $output);
            $this->assertDirectoryExists($app . '/' . $dir);
        }
        $this->assertStringNotContainsString('Create the directory tmp/cache/persistent', $output);
        // app/Vendor/ to vendors/, and what stays in app/
        $this->assertStringContainsString('Move app/Vendor to vendors', $output);
        $this->assertFileExists($app . '/vendors/markdown.php');
        $this->assertStringContainsString('Not moved, move or replace them by hand: app/.htaccess.', $output);
        $this->assertStringContainsString('.htaccess leads to app/webroot/, which moved to webroot/', $output);
        $this->assertStringContainsString('remove the $ext = \'.thtml\'', $output);
        $this->assertStringContainsString('Not a Git repository', $output);
        // The lines that name the old templates are listed, not changed
        $this->assertStringContainsString(
            "Review them, they were not changed:\n" .
            "  config/routes.php:9: // The home page: View/Pages/home.ctp\n" .
            "  templates/Pages/home.php:16: <p><?php echo __d('cake_dev', 'To change its layout, edit: %s.', '<code>View/Layouts/default.ctp</code>'); ?></p>\n" .
            'Next, ',
            $output,
        );
        $this->assertStringContainsString('Next, delete the cache files in tmp/cache/, which hold the paths of the old layout, and run composer dump-autoload and the tests.', $output);
        $this->assertSame([], $this->_errors);
    }

    /**
     * The templates keep their place in the directories of themes and plugins, the scaffold templates keep .ctp
     *
     * @return void
     */
    public function testTemplatePaths(): void
    {
        $app = $this->_copy('legacy-app', 'app');

        $this->_run($app, ['layout']);

        $this->assertFileExists($app . '/templates/Themed/Dark/layout/default.php');
        $this->assertFileExists($app . '/templates/Themed/Dark/webroot/css/dark.css');
        $this->assertFileExists($app . '/templates/Scaffolds/index.ctp');
        $this->assertFileExists($app . '/templates/element/menu.php');
        $this->assertFileExists($app . '/templates/email/text/welcome.php');
        $this->assertFileExists($app . '/templates/Error/error400.php');
        $this->assertFileDoesNotExist($app . '/app/View');
        $this->assertFileDoesNotExist($app . '/app/Config');
    }

    /**
     * An application already in the layout of pieceofcake2/app: the templates in `templates/` get the names of
     * CakePHP 5, nothing else moves
     *
     * @return void
     */
    public function testModernApplication(): void
    {
        $app = $this->_copy('app', 'app');
        mkdir($app . '/templates/Layouts');
        file_put_contents($app . '/templates/Layouts/ajax.ctp', '<?php echo $this->fetch(\'content\'); ?>');
        file_put_contents($app . '/templates/Posts/add.ctp', 'add');

        $this->_run($app, ['layout']);

        $this->assertStringContainsString('Move templates/Layouts/ajax.ctp to templates/layout/ajax.php', $this->out->output);
        $this->assertStringContainsString('Move templates/Posts/add.ctp to templates/Posts/add.php', $this->out->output);
        $this->assertFileExists($app . '/templates/layout/ajax.php');
        $this->assertFileDoesNotExist($app . '/templates/Layouts');
        $this->assertFileExists($app . '/templates/Posts/add.php');

        $this->out->output = '';
        $this->_run($app, ['layout']);

        $this->assertStringContainsString('Nothing to move', $this->out->output);
    }

    /**
     * A template is never overwritten
     *
     * @return void
     */
    public function testConflict(): void
    {
        $app = $this->_copy('legacy-app', 'app');
        mkdir($app . '/templates/Posts', 0777, true);
        file_put_contents($app . '/templates/Posts/index.php', 'mine');

        $this->_run($app, ['layout']);

        $this->assertSame('mine', file_get_contents($app . '/templates/Posts/index.php'));
        $this->assertFileExists($app . '/app/View/Posts/index.ctp');
        $this->assertStringContainsString('app/View/Posts/index.ctp was not moved: templates/Posts/index.php exists', $this->out->output);
    }

    /**
     * A dry run lists the moves and changes nothing
     *
     * @return void
     */
    public function testDryRun(): void
    {
        $app = $this->_copy('legacy-app', 'app');

        $this->_run($app, ['layout', '--dry-run']);

        $this->_assertSameTree($this->_fixture('legacy-app'), $app);
        $output = $this->out->output;
        $this->assertStringContainsString('Dry-run mode enabled!', $output);
        $this->assertStringContainsString('Move app/Controller to src/Controller', $output);
        $this->assertStringContainsString('Move app/View/Helper to src/View/Helper', $output);
        $this->assertStringContainsString('Move app/Test/Case to tests/TestCase', $output);
        $this->assertStringContainsString('Move app/tmp/logs to logs', $output);
        $this->assertStringContainsString('autoload.classmap: app/Controller/ => src/Controller/', $output);
        $this->assertStringContainsString('Create bin/cake of pieceofcake2/app', $output);
        $this->assertStringContainsString('Keep the constant SITE_NAME of app/webroot/index.php', $output);
        $this->assertStringNotContainsString('Not a Git repository', $output);
        // The lines that name the old templates, at the places of the files after the moves
        $this->assertStringContainsString('  config/routes.php:9: // The home page: View/Pages/home.ctp', $output);
        $this->assertStringContainsString('  templates/Pages/home.php:16: ', $output);
    }

    /**
     * The entry files that exist are not overwritten, and the constants of the old files are then listed
     *
     * @return void
     */
    public function testExistingEntryFiles(): void
    {
        $app = $this->_copy('legacy-app', 'app');
        mkdir($app . '/bin');
        mkdir($app . '/config');
        file_put_contents($app . '/bin/cake', 'mine');
        file_put_contents($app . '/config/define.php', '<?php // mine');

        $this->_run($app, ['layout']);

        $this->assertSame('mine', file_get_contents($app . '/bin/cake'));
        $this->assertSame('<?php // mine', file_get_contents($app . '/config/define.php'));
        $this->assertStringNotContainsString('Create bin/cake', $this->out->output);
        $this->assertStringContainsString('Create webroot/index.php of pieceofcake2/app', $this->out->output);
        $this->assertStringContainsString('config/define.php exists, so these constants of the old entry files were not added to it: SITE_NAME, CONSOLE_USER.', $this->out->output);
        // app/Config/ merged into config/
        $this->assertFileExists($app . '/config/core.php');
    }

    /**
     * An application in the new layout gets the entry files that it misses
     *
     * @return void
     */
    public function testMissingEntryFiles(): void
    {
        $app = $this->_copy('app', 'app');
        unlink($app . '/bin/cake');
        unlink($app . '/webroot/test.php');

        $this->_run($app, ['layout']);

        $this->assertStringContainsString('Create bin/cake of pieceofcake2/app', $this->out->output);
        $this->assertStringContainsString('Create webroot/test.php of pieceofcake2/app', $this->out->output);
        $this->assertStringNotContainsString('Create config/define.php', $this->out->output);
        $this->assertFileEquals(dirname(__DIR__, 4) . '/resources/skeleton/bin/cake', $app . '/bin/cake');
    }

    /**
     * In a Git repository, the files move with git mv, so that Git keeps their history; the files that Git does not
     * track move too
     *
     * @return void
     */
    public function testGit(): void
    {
        $app = $this->_copy('legacy-app', 'app');
        exec('git -C ' . escapeshellarg($app) . ' init -q 2>&1', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('git is not available');
        }
        exec('git -C ' . escapeshellarg($app) . ' add -A -- . ":!app/tmp"');
        // A commit of the test repository, so that git rm removes the files without changes
        exec('git -C ' . escapeshellarg($app) . ' -c user.name=Test -c user.email=test@example.com -c commit.gpgsign=false commit -q -m Legacy');
        // A change that is not committed: the file stays
        file_put_contents($app . '/app/Console/cake.bat', 'changed');

        $this->_run($app, ['layout']);

        exec('git -C ' . escapeshellarg($app) . ' ls-files', $files);
        $this->assertContains('src/Controller/PostsController.php', $files);
        $this->assertContains('templates/layout/default.php', $files);
        $this->assertContains('config/core.php', $files);
        $this->assertNotContains('app/Controller/PostsController.php', $files);
        $this->assertNotContains('app/View/Layouts/default.ctp', $files);
        // Not tracked
        $this->assertFileExists($app . '/tmp/cache/persistent/empty');
        $this->assertNotContains('tmp/cache/persistent/empty', $files);
        $this->assertStringNotContainsString('Not a Git repository', $this->out->output);
        // The old entry files are removed with git rm, Git keeps them in its history
        foreach (['app/webroot/index.php', 'app/webroot/test.php', 'app/Console/cake', 'app/Console/cake.php'] as $file) {
            $this->assertStringContainsString('Remove ' . $file . ', which the files of pieceofcake2/app replace', $this->out->output);
            $this->assertNotContains($file, $files);
            $this->assertFileDoesNotExist($app . '/' . $file);
        }
        $this->assertFileExists($app . '/webroot/index.php');
        $this->assertSame('changed', file_get_contents($app . '/app/Console/cake.bat'));
        $this->assertStringContainsString('Delete app/Console/cake.bat, which the files of pieceofcake2/app replace', $this->out->output);
    }

    /**
     * A plugin: only the configuration, templates, translations and tests move, as its src/ holds namespaced
     * classes only; the result is `tests/fixtures/plugin/Blog`
     *
     * @return void
     */
    public function testPlugin(): void
    {
        $plugins = $this->_copy('legacy-plugin', 'plugins');
        App::build(['Plugin' => [$plugins . DS]]);

        $this->_run($this->_root, ['layout', '--plugin', 'Blog']);

        $this->_assertSameTree($this->_fixture('plugin/Blog'), $plugins . DS . 'Blog');
        $output = $this->out->output;
        $this->assertStringContainsString('Move Config to config', $output);
        $this->assertStringContainsString('Move View/Layouts/blog.ctp to templates/layout/blog.php', $output);
        $this->assertStringContainsString('Move Test/Case to tests/TestCase', $output);
        $this->assertStringContainsString('autoload-dev.classmap: Test/ => tests/', $output);
        $this->assertStringContainsString('bin/cake upgrade namespace --plugin Blog', $output);
        $this->assertStringNotContainsString('Move Controller', $output);
    }

    /**
     * An application whose root is the directory `app/` of CakePHP 2 becomes `tests/fixtures/root-app`, in a Git
     * repository: `Config/` becomes `config/` (also on a case-insensitive file system, where Git records the new case),
     * the old front controllers of `webroot/` are replaced, the plugins installed by Composer move with `Plugin/` and
     * composer.json installs them in `plugins/`, the vendor/ of Composer stays, and the entry files of the application
     * stay with the new paths
     *
     * @return void
     */
    public function testRootApplication(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        // The plugin that Composer installs, and its vendor/
        $this->_commit($app, ['Plugin/Tags']);
        mkdir($app . '/vendor');
        file_put_contents($app . '/vendor/autoload.php', '<?php // Composer');

        $this->_run($app, ['layout']);

        $this->_assertSameTree($this->_fixture('root-app'), $app);
        $this->assertSame(['.git', '.htaccess', 'bin', 'composer.json', 'config', 'index.php', 'logs', 'plugins', 'resources', 'src', 'templates', 'tests', 'tmp', 'vendor', 'webroot'], array_values(array_diff((array)scandir($app), ['.', '..'])));
        $this->assertSame('<?php // Composer', file_get_contents($app . '/vendor/autoload.php'));
        exec('git -C ' . escapeshellarg($app) . ' ls-files', $files);
        $this->assertContains('config/core.php', $files);
        $this->assertNotContains('Config/core.php', $files);
        $this->assertContains('plugins/Admin/Controller/AdminAppController.php', $files);
        $this->assertNotContains('plugins/Tags/Model/Tag.php', $files);
        $this->assertNotContains('Console/cake.php', $files);
        $output = $this->out->output;
        $this->assertStringContainsString('Move Config to config', $output);
        $this->assertStringContainsString('Move View/Elements/price.ctp to templates/element/price.php', $output);
        $this->assertStringContainsString('Move Plugin to plugins', $output);
        $this->assertStringNotContainsString('Move vendor', $output);
        $this->assertStringNotContainsString('Move webroot', $output);
        $this->assertStringContainsString('Replace webroot/index.php of the CakePHP 2 layout with the one of pieceofcake2/app', $output);
        $this->assertStringContainsString('Replace webroot/test.php of the CakePHP 2 layout with the one of pieceofcake2/app', $output);
        $this->assertFileEquals(dirname(__DIR__, 4) . '/resources/skeleton/webroot/index.php', $app . '/webroot/index.php');
        $this->assertStringContainsString('Remove Console/cake.php, which the files of pieceofcake2/app replace', $output);
        $this->assertStringContainsString("Update the paths of bin/cake: 'Config' => 'config'", $output);
        $this->assertStringContainsString("require_once dirname(__DIR__) . DS . 'config' . DS . 'define.php';", (string)file_get_contents($app . '/bin/cake'));
        $this->assertStringContainsString("if (getenv('LAMBDA_TASK_ROOT') !== false) {", (string)file_get_contents($app . '/bin/cake'));
        $this->assertStringContainsString('autoload.classmap: Controller/ => src/Controller/', $output);
        $this->assertStringContainsString('Update composer.json: extra.installer-paths: plugins/{$name}/ for type:cakephp-plugin', $output);
        $this->assertStringContainsString('Kept the entry files that the application has, review them for the new layout: bin/cake.', $output);
        // Config/define.php in the old form: replaced, with the constants of its own and of the old entry files
        $this->assertStringContainsString('Replace config/define.php of the CakePHP 2 layout with the one of pieceofcake2/app', $output);
        $this->assertStringContainsString('Keep the constant SHOP_TAX of Config/define.php in config/define.php', $output);
        $this->assertStringContainsString('Keep the constant SHOP_NAME of webroot/index.php in config/define.php', $output);
        $this->assertStringContainsString('Config/define.php defines PAYMENT_URL under a condition', $output);
        $this->assertStringStartsWith((string)file_get_contents(dirname(__DIR__, 4) . '/resources/skeleton/config/define.php'), rtrim((string)file_get_contents($app . '/config/define.php')) . "\n");
        $this->assertStringNotContainsString('config/define.php was kept', $output);
        $this->assertStringContainsString('webroot/index.php defines IMAGES_DIR from ROOT, APP_DIR of the CakePHP 2 layout', $output);
        $this->assertStringContainsString('webroot/index.php defines SHOP_DEMO under a condition', $output);
        $this->assertStringContainsString('index.php leads to webroot/: set the document root of the web server to webroot/, then delete it.', $output);
        $this->assertStringContainsString('Next, delete the cache files in tmp/cache/, which hold the paths of the old layout, and run composer install and the tests.', $output);
        $this->assertSame([], $this->_errors);
    }

    /**
     * Outside Git, the old front controllers of `webroot/` of an application without `app/` stay, as nothing would
     * keep their contents; an application whose config/ is lowercase already
     *
     * @return void
     */
    public function testRootApplicationOutsideGit(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        rename($app . '/Config', $app . '/config.tmp');
        rename($app . '/config.tmp', $app . '/config');
        unlink($app . '/config/define.php');

        $this->_run($app, ['layout']);

        $output = $this->out->output;
        $this->assertStringNotContainsString('Move Config', $output);
        $this->assertStringNotContainsString('Move config', $output);
        $this->assertFileEquals($this->_fixture('legacy-root-app/webroot/index.php'), $app . '/webroot/index.php');
        $this->assertStringContainsString('Replace webroot/index.php, webroot/test.php of the CakePHP 2 layout with the files of pieceofcake2/app in resources/skeleton/ of pieceofcake2/upgrade, once you have compared them', $output);
        $this->assertStringContainsString('Delete Console/cake, Console/cake.php, Console/cake.bat, which the files of pieceofcake2/app replace', $output);
        // config/define.php is created, with the constants of the old entry files
        $this->assertStringContainsString('Create config/define.php of pieceofcake2/app', $output);
        $this->assertStringContainsString("// Kept from webroot/index.php\nif (!defined('SHOP_NAME')) {\n    define('SHOP_NAME', 'Acme Shop');\n}\n", (string)file_get_contents($app . '/config/define.php'));
        $this->assertStringContainsString('Keep the constant SHOP_CURRENCY of Console/cake.php in config/define.php', $output);
        $this->assertStringNotContainsString('IMAGES_DIR', (string)file_get_contents($app . '/config/define.php'));
        $this->assertStringContainsString('Kept the entry files that the application has, review them for the new layout: bin/cake.', $output);
        $this->assertFileExists($app . '/src/Controller/PagesController.php');
        $this->assertSame([], $this->_errors);
    }

    /**
     * The console of CakePHP 2 runs `app/` of the directory of composer.json unless `-root` and `-app` say where the
     * application is: `layout` then changes nothing and prints the command
     *
     * @return void
     */
    public function testRootApplicationWithoutAppOption(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        $this->_app = $app . DS . 'app' . DS;

        $this->_run($app, ['layout']);

        $this->assertSame([sprintf('The console runs the application of %s, not %s, which has Config/ and Controller/ in its root: the console looks for the application in app/ of the directory of composer.json. Run `Console/cake upgrade layout -root "$PWD" -app "$PWD"` from %s.', $app . DS . 'app', $app, $app)], $this->_errors);
        $this->_assertSameTree($this->_fixture('legacy-root-app'), $app);
        $this->assertStringNotContainsString('Move', $this->out->output);
    }

    /**
     * A `Config/define.php` in the old form with changes that are not committed stays, with its constants and those of
     * the old entry files, and is listed
     *
     * @return void
     */
    public function testRootApplicationOldDefineNotCommitted(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        $this->_commit($app, ['Plugin/Tags']);
        file_put_contents($app . '/Config/define.php', "\ndefine('SHOP_LOCALE', 'de_DE');\n", FILE_APPEND);
        $define = (string)file_get_contents($app . '/Config/define.php');

        $this->_run($app, ['layout']);

        $output = $this->out->output;
        $this->assertSame($define, file_get_contents($app . '/config/define.php'));
        $this->assertStringNotContainsString('Replace config/define.php of the CakePHP 2 layout with the one of', $output);
        $this->assertStringContainsString('Replace config/define.php of the CakePHP 2 layout with the files of pieceofcake2/app in resources/skeleton/ of pieceofcake2/upgrade, once you have compared them', $output);
        $this->assertStringContainsString('config/define.php is not replaced, so these constants of the old entry files were not added to it: SHOP_TAX, SHOP_LOCALE, SHOP_NAME, SHOP_CURRENCY.', $output);
        $this->assertStringNotContainsString('Keep the constant', $output);
        $this->assertStringContainsString('Kept the entry files that the application has, review them for the new layout: bin/cake, config/define.php.', $output);
        $this->assertStringContainsString('config/define.php was kept: compare it with resources/skeleton/config/define.php of pieceofcake2/upgrade, as it defines ROOT and APP_DIR with other values than the new layout', $output);
        // The other old entry files are replaced
        $this->assertFileEquals(dirname(__DIR__, 4) . '/resources/skeleton/webroot/index.php', $app . '/webroot/index.php');
    }

    /**
     * A `Config/define.php` in the form of pieceofcake2/app stays, with the paths of the moved directories updated
     *
     * @return void
     */
    public function testRootApplicationNewDefine(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        $skeleton = (string)file_get_contents(dirname(__DIR__, 4) . '/resources/skeleton/config/define.php');
        file_put_contents($app . '/Config/define.php', $skeleton . "\nif (!defined('SCHEMA')) {\n    define('SCHEMA', ROOT . DS . 'Config' . DS . 'Schema' . DS);\n}\n");
        $this->_commit($app, ['Plugin/Tags']);

        $this->_run($app, ['layout']);

        $output = $this->out->output;
        $this->assertSame($skeleton . "\nif (!defined('SCHEMA')) {\n    define('SCHEMA', ROOT . DS . 'config' . DS . 'Schema' . DS);\n}\n", file_get_contents($app . '/config/define.php'));
        $this->assertStringContainsString("Update the paths of config/define.php: 'Config' => 'config'", $output);
        $this->assertStringContainsString('Kept the entry files that the application has, review them for the new layout: config/define.php, bin/cake.', $output);
        $this->assertStringNotContainsString('Replace config/define.php', $output);
        $this->assertStringNotContainsString('config/define.php was kept', $output);
        $this->assertStringContainsString('config/define.php exists, so these constants of the old entry files were not added to it: SHOP_NAME, SHOP_CURRENCY.', $output);
    }

    /**
     * The `Vendor/` of CakePHP 2 in the root moves to `vendors/`, unless it is the vendor-dir of Composer; a dry run
     * changes nothing
     *
     * @return void
     */
    public function testRootApplicationVendor(): void
    {
        $app = $this->_copy('legacy-root-app', 'app');
        mkdir($app . '/Vendor');
        file_put_contents($app . '/Vendor/barcode.php', '<?php // barcode');

        $this->_run($app, ['layout', '--dry-run']);

        $this->assertStringContainsString('Move Vendor to vendors', $this->out->output);
        $this->assertFileExists($app . '/Vendor/barcode.php');
        $this->assertFileExists($app . '/Config/core.php');
        $this->assertStringContainsString("Update the paths of bin/cake: 'Config' => 'config'", $this->out->output);
        $this->assertStringContainsString("'Config' . DS . 'define.php'", (string)file_get_contents($app . '/bin/cake'));

        $this->out->output = '';
        $this->_run($app, ['layout']);

        $this->assertFileExists($app . '/vendors/barcode.php');
        $this->assertStringContainsString('Vendor/ moved to vendors/', $this->out->output);

        $app = $this->_copy('legacy-root-app', 'composer-vendor');
        mkdir($app . '/Vendor');
        file_put_contents($app . '/Vendor/autoload.php', '<?php // Composer');
        $json = json_decode((string)file_get_contents($app . '/composer.json'), true);
        $json['config']['vendor-dir'] = 'Vendor';
        file_put_contents($app . '/composer.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->out->output = '';

        $this->_run($app, ['layout']);

        $this->assertStringNotContainsString('Move Vendor', $this->out->output);
        $this->assertFileExists($app . '/Vendor/autoload.php');
    }

    /**
     * A plugin is no application: `layout` needs `--plugin`
     *
     * @return void
     */
    public function testPluginWithoutOption(): void
    {
        $plugins = $this->_copy('legacy-plugin', 'plugins');

        $this->_run($plugins . DS . 'Blog', ['layout']);

        $this->assertSame([sprintf('%s holds a plugin: run the command with --plugin <Name> from the root of the application.', $plugins . DS . 'Blog')], $this->_errors);
        $this->_assertSameTree($this->_fixture('legacy-plugin/Blog'), $plugins . DS . 'Blog');
    }

    /**
     * A directory without an application
     *
     * @return void
     */
    public function testNoApplication(): void
    {
        $this->_run($this->_root, ['layout']);

        $this->assertSame([sprintf('Neither app/, Config/ nor config/ in %s: run the command from the root of the project.', rtrim($this->_root, DS))], $this->_errors);
    }

    /**
     * An unknown plugin
     *
     * @return void
     */
    public function testUnknownPlugin(): void
    {
        $this->expectException(MissingPluginException::class);

        $this->_run($this->_root, ['layout', '--plugin', 'Nothing']);
    }
}
