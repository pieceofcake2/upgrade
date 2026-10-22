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
     * A directory without an application
     *
     * @return void
     */
    public function testNoApplication(): void
    {
        $this->_run($this->_root, ['layout']);

        $this->assertSame([sprintf('Neither app/ nor config/ in %s: run the command from the root of the project.', rtrim($this->_root, DS))], $this->_errors);
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
