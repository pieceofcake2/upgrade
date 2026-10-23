<?php
/**
 * UpgradeNamespaceTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

require_once __DIR__ . DS . 'UpgradeCommandTestCase.php';

/**
 * `bin/cake upgrade namespace`: the moves, the rules of Rector, App.namespace and composer.json
 */
class UpgradeNamespaceTest extends UpgradeCommandTestCase
{
    /**
     * An application in the layout of pieceofcake2/app becomes `tests/fixtures/app-namespaced`
     *
     * - `src/Lib/<Type>/` moves to `src/<Type>/`, `src/Lib/MyTool.php` stays as App\Lib\MyTool
     * - the copy of Dispatcher (a core class override) moves to `src/Cake/Routing/` with its class_alias()
     * - the namespaces come from the paths, the classes are imported, App::uses() is removed
     * - the class names in strings become ::class: comparisons, class_exists(), mocks, settings
     * - App.namespace in config/core.php, PSR-4 in composer.json
     *
     * @return void
     */
    public function testApplication(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace']);

        $this->_assertSameTree($this->_fixture('app-namespaced'), $app);
        $output = $this->out->output;
        $this->assertStringContainsString('Move src/Lib/Error to src/Error', $output);
        $this->assertStringContainsString('Move src/Routing/Dispatcher.php to src/Cake/Routing/Dispatcher.php', $output);
        $this->assertStringContainsString('src/Lib/Routing/Dispatcher.php is a copy of the class Cake\Routing\Dispatcher of CakePHP', $output);
        $this->assertStringContainsString('src/Lib/functions.php was not namespaced: it also declares functions', $output);
        $this->assertStringContainsString('Add Configure::write(\'App.namespace\', \'App\'); to config/core.php, before Configure::write(\'App.encoding\', \'UTF-8\');', $output);
        $this->assertStringContainsString('autoload.psr-4: App\ => src/', $output);
        $this->assertStringContainsString('autoload-dev.psr-4: App\Test\ => tests/', $output);
        $this->assertStringContainsString('autoload.exclude-from-classmap: vendor/pieceofcake2/cakephp/src/Routing/Dispatcher.php', $output);
        $this->assertSame([], $this->_errors);
    }

    /**
     * The classes of CakePHP get use statements in `config/`, the entry files and the templates that name them; the
     * code and the other templates stay as they are
     *
     * @return void
     */
    public function testCoreClassImports(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace']);

        $file = static fn(string $path): string => (string)file_get_contents($app . DS . str_replace('/', DS, $path));
        // A template with a class of CakePHP after its HTML, one that starts with a block of PHP, and one with a class
        // in its docblock only
        $this->assertStringStartsWith("<?php\nuse Cake\\Core\\Configure;\n?>\n<h2><?php echo h(\$message); ?></h2>\n<?php\nif (Configure::read('debug') > 0):\n", $file('templates/Error/error400.php'));
        $this->assertStringStartsWith(
            "<?php\n/**\n * The home page\n *\n * @var View \$this\n */\n\nuse App\\Utility\\Slug;\nuse Cake\\Core\\Configure;\nuse Cake\\Error\\NotFoundException;\nuse Cake\\Utility\\Debugger;\nuse Cake\\View\\View;\n\nif (!Configure::read('debug')):\n    throw new NotFoundException();\nendif;\n?>\n",
            $file('templates/Pages/home.php'),
        );
        $this->assertStringStartsWith("<?php\n/**\n * @var AppView \$this\n */\n\nuse App\\View\\AppView;\n\n?>\n<nav>", $file('templates/element/menu.php'));
        // Templates without classes
        foreach (['templates/Posts/view.php', 'templates/layout/default.php', 'templates/Scaffolds/index.ctp'] as $template) {
            $this->assertFileEquals($this->_fixture('app/' . $template), $app . DS . $template, $template);
        }
        // config/
        $this->assertStringContainsString(" */\n\nuse App\\Log\\Engine\\AuditLog;\nuse App\\Routing\\Filter\\MaintenanceFilter;\nuse Cake\\Cache\\Cache;\nuse Cake\\Cache\\Engine\\FileEngine;\nuse Cake\\Core\\CakePlugin;\nuse Cake\\Core\\Configure;\n", $file('config/bootstrap.php'));
        $this->assertStringContainsString("\nCache::config('default', ['engine' => FileEngine::class]);\n\nConfigure::write('Dispatcher.filters', [\n", $file('config/bootstrap.php'));
        $this->assertStringContainsString("\nCakePlugin::load('Blog');\n", $file('config/bootstrap.php'));
        $this->assertStringStartsWith("<?php\n\nuse App\\Routing\\Route\\SlugRoute;\nuse Cake\\Core\\CakePlugin;\nuse Cake\\Routing\\Router;\n\nRouter::connect('/', ", $file('config/routes.php'));
        $this->assertStringContainsString(" * @package       app.Config\n */\n\nuse App\\Error\\AppExceptionRenderer;\nuse Cake\\Cache\\Cache;\n", $file('config/core.php'));
        $this->assertStringContainsString("/**\n * CakePHP Debug Level:\n */\nConfigure::write('debug', 0);\n", $file('config/core.php'));
        $this->assertFileEquals($this->_fixture('app/config/define.php'), $app . DS . 'config' . DS . 'define.php');
        // The entry files
        $this->assertStringContainsString(" */\n\nuse Cake\\Network\\CakeRequest;\nuse Cake\\Network\\CakeResponse;\nuse Cake\\Routing\\Dispatcher;\nuse Composer\\InstalledVersions;\n\n", $file('webroot/index.php'));
        $this->assertStringEndsWith("\n\$dispatcher = new Dispatcher();\n\$dispatcher->dispatch(\n    new CakeRequest(),\n    new CakeResponse(),\n);\n", $file('webroot/index.php'));
        $this->assertStringContainsString(" */\n\nuse Cake\\Core\\Configure;\nuse Cake\\Error\\NotFoundException;\nuse Cake\\TestSuite\\CakeTestSuiteDispatcher;\nuse Composer\\InstalledVersions;\n\n", $file('webroot/test.php'));
        $this->assertStringEndsWith("\nCakeTestSuiteDispatcher::run();\n", $file('webroot/test.php'));
        $this->assertStringStartsWith("#!/usr/bin/env php\n<?php\n/**\n", $file('bin/cake'));
        $this->assertStringContainsString(" * @license       https://opensource.org/licenses/mit-license.php MIT License\n */\n\nuse Cake\\Console\\ShellDispatcher;\n\nif (!defined('DS')) {\n", $file('bin/cake'));
        $this->assertStringEndsWith("\nShellDispatcher::run(\$argv);\n", $file('bin/cake'));
        $this->assertTrue(is_executable($app . DS . 'bin' . DS . 'cake'));
    }

    /**
     * The commands of the upgrade in their order, from the CakePHP 2 layout: layout, then namespace. A second run
     * changes nothing.
     *
     * @return void
     */
    public function testLayoutThenNamespace(): void
    {
        $app = $this->_copy('legacy-app', 'app');

        $this->_run($app, ['layout']);
        $this->_run($app, ['namespace']);

        $this->_assertSameTree($this->_fixture('app-namespaced'), $app);

        $this->out->output = '';
        $this->_run($app, ['namespace']);

        $this->_assertSameTree($this->_fixture('app-namespaced'), $app);
        $this->assertStringContainsString('config/core.php already sets App.namespace to App.', $this->out->output);
        $this->assertStringContainsString('composer.json already autoloads App with PSR-4.', $this->out->output);
        $this->assertStringNotContainsString('Move ', $this->out->output);
    }

    /**
     * A dry run prints the moves and the changes as a diff, and changes nothing
     *
     * @return void
     */
    public function testDryRun(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace', '--dry-run']);

        $this->_assertSameTree($this->_fixture('app'), $app);
        $output = $this->out->output;
        $this->assertStringContainsString('Move src/Lib/Utility to src/Utility', $output);
        $this->assertStringContainsString("--- src/Model/Post.php\n+++ src/Model/Post.php", $output);
        $this->assertStringContainsString('+namespace App\Model;', $output);
        $this->assertStringContainsString("-    public \$uses = ['Post', 'Blog.Comment'];\n+    public \$uses = [Post::class, 'Blog.Comment'];", $output);
        $this->assertStringContainsString("+Configure::write('App.namespace', 'App');", $output);
        $this->assertStringContainsString('+            "App\\\\": "src/"', $output);
        $this->assertStringContainsString("--- webroot/index.php\n+++ webroot/index.php", $output);
        $this->assertStringContainsString('+use Cake\Routing\Dispatcher;', $output);
        $this->assertStringContainsString("--- bin/cake\n+++ bin/cake", $output);
        $this->assertStringNotContainsString('webroot/css', $output);
        $this->assertStringNotContainsString(TMP, $output);
        $this->assertSame([], glob(TMP . 'upgrade-dry-run-*') ?: []);
    }

    /**
     * `--namespace Acme`: the namespace declarations, the imports, PSR-4 and App.namespace
     *
     * @return void
     */
    public function testNamespaceOption(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace', '--namespace', 'Acme\Blog']);

        $post = (string)file_get_contents($app . '/src/Model/Post.php');
        $this->assertStringContainsString("namespace Acme\\Blog\\Model;\n", $post);
        $this->assertStringContainsString("use Acme\\Blog\\Model\\Behavior\\SluggableBehavior;\n", $post);
        $test = (string)file_get_contents($app . '/tests/TestCase/Controller/PostsControllerTest.php');
        $this->assertStringContainsString("namespace Acme\\Blog\\Test\\TestCase\\Controller;\n", $test);
        $this->assertStringContainsString("use Acme\\Blog\\Controller\\PostsController;\n", $test);
        $this->assertStringContainsString("Configure::write('App.namespace', 'Acme\\Blog');\n", (string)file_get_contents($app . '/config/core.php'));
        $composer = json_decode((string)file_get_contents($app . '/composer.json'), true);
        $this->assertSame(['Acme\\Blog\\' => 'src/'], $composer['autoload']['psr-4']);
        $this->assertSame(['Acme\\Blog\\Test\\' => 'tests/'], $composer['autoload-dev']['psr-4']);
        // The core class override keeps the namespace of CakePHP
        $this->assertStringContainsString("namespace Cake\\Routing;\n", (string)file_get_contents($app . '/src/Cake/Routing/Dispatcher.php'));
    }

    /**
     * Not a namespace
     *
     * @return void
     */
    public function testInvalidNamespace(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace', '--namespace', 'acme-blog']);

        $this->assertSame(['Not a namespace: acme-blog'], $this->_errors);
        $this->_assertSameTree($this->_fixture('app'), $app);
    }

    /**
     * `--no-short-names`: the short names of the settings stay, the full class names change
     *
     * @return void
     */
    public function testNoShortNames(): void
    {
        $app = $this->_copy('app', 'app');

        $this->_run($app, ['namespace', '--no-short-names']);

        $controller = (string)file_get_contents($app . '/src/Controller/PostsController.php');
        $this->assertStringContainsString("public \$uses = [Post::class, 'Blog.Comment'];", $controller);
        $this->assertStringContainsString("public \$components = ['Paginator', 'Mailer' => ['className' => 'Greeting']];", $controller);
        $this->assertStringContainsString("\$this->viewClass = 'Xml';", $controller);
        $this->assertStringContainsString('ClassRegistry::init(Post::class)', $controller);
        $core = (string)file_get_contents($app . '/config/core.php');
        $this->assertStringContainsString("'renderer' => AppExceptionRenderer::class,", $core);
        $this->assertStringContainsString("'handler' => [ErrorHandler::class, 'handleError'],", $core);
    }

    /**
     * The command stops on the CakePHP 2 layout, before it changes anything
     *
     * @return void
     */
    public function testLegacyLayout(): void
    {
        $app = $this->_copy('legacy-app', 'app');
        $plugins = $this->_copy('legacy-plugin', 'plugins');
        App::build(['Plugin' => [$plugins . DS]]);

        $this->_run($app, ['namespace']);
        $this->_run($app, ['namespace', '--plugin', 'Blog']);

        $this->assertSame([
            'The application is in the CakePHP 2 layout (app/Config/): run bin/cake upgrade layout first.',
            'Blog is in the CakePHP 2 layout (Config/): run bin/cake upgrade layout --plugin Blog first.',
        ], $this->_errors);
        $this->_assertSameTree($this->_fixture('legacy-app'), $app);
        $this->_assertSameTree($this->_fixture('legacy-plugin'), $plugins);
    }

    /**
     * Not the root of an application
     *
     * @return void
     */
    public function testNoCoreConfig(): void
    {
        $app = $this->_copy('app', 'app');
        unlink($app . '/config/core.php');

        $this->_run($app, ['namespace']);

        $this->assertSame([sprintf('No config/core.php in %s: run the command from the root of the application.', $app)], $this->_errors);
    }

    /**
     * App.namespace already set to the same namespace, or to another one: config/core.php does not change
     *
     * @return void
     */
    public function testAppNamespaceAlreadySet(): void
    {
        $app = $this->_copy('app', 'app');
        $core = (string)file_get_contents($app . '/config/core.php');
        $core = str_replace("Configure::write('debug', 0);", "Configure::write('debug', 0);\nConfigure::write('App.namespace', 'Acme');", $core);
        file_put_contents($app . '/config/core.php', $core);

        $this->_run($app, ['namespace']);

        $this->assertStringContainsString('config/core.php sets App.namespace to Acme, not App: the classes are now in App. Change one of them.', $this->out->output);
        $after = (string)file_get_contents($app . '/config/core.php');
        $this->assertSame(1, substr_count($after, 'App.namespace'));
        $this->assertStringContainsString("Configure::write('App.namespace', 'Acme');", $after);
    }

    /**
     * Without App.encoding the setting goes before the first Cache::config(); without either, it is left to the user
     *
     * @return void
     */
    public function testAppNamespacePlaces(): void
    {
        $app = $this->_copy('app', 'app');
        $file = $app . '/config/core.php';
        $core = str_replace("Configure::write('App.encoding', 'UTF-8');", '', (string)file_get_contents($file));
        file_put_contents($file, $core);

        $this->_run($app, ['namespace', '--dry-run']);

        $this->assertStringContainsString("Add Configure::write('App.namespace', 'App'); to config/core.php, before Cache::config('_cake_core_', [", $this->out->output);
        $this->assertStringContainsString("+Configure::write('App.namespace', 'App');\n+\n /**\n  * Configure the cache used for general framework caching.", $this->out->output);

        $core = (string)preg_replace('/^Cache::config\(.*?\]\);$/ms', '', $core);
        file_put_contents($file, $core);
        $this->out->output = '';

        $this->_run($app, ['namespace']);

        $this->assertStringContainsString("config/core.php has neither App.encoding nor Cache::config(): add Configure::write('App.namespace', 'App'); to it by hand", $this->out->output);
        $this->assertStringNotContainsString('App.namespace', (string)file_get_contents($file));
    }

    /**
     * A plugin: the class folders move into src/, its namespace is its name, its composer.json gets PSR-4; the
     * result is `tests/fixtures/plugin-namespaced/Blog`
     *
     * @return void
     */
    public function testPlugin(): void
    {
        $plugins = $this->_copy('plugin', 'plugins');
        App::build(['Plugin' => [$plugins . DS]]);

        $this->_run($this->_root, ['namespace', '--plugin', 'Blog']);

        $this->_assertSameTree($this->_fixture('plugin-namespaced/Blog'), $plugins . DS . 'Blog');
        $output = $this->out->output;
        $this->assertStringContainsString('Move Lib/Log to src/Log', $output);
        $this->assertStringContainsString('Move Controller to src/Controller', $output);
        $this->assertStringContainsString('autoload.psr-4: Blog\ => src/', $output);
        $this->assertStringContainsString('autoload.classmap: removed Controller/', $output);
        $this->assertStringNotContainsString('App.namespace', $output);
        $this->assertSame([], $this->_errors);
    }

    /**
     * A plugin without composer.json: the composer.json of the application autoloads it
     *
     * @return void
     */
    public function testPluginWithoutComposerJson(): void
    {
        $app = $this->_copy('app-namespaced', 'app');
        (new Folder($this->_fixture('plugin/Blog')))->copy(['to' => $app . '/plugins/Shop', 'mode' => 0777]);
        unlink($app . '/plugins/Shop/composer.json');
        App::build(['Plugin' => [$app . DS . 'plugins' . DS]]);

        $this->_run($app, ['namespace', '--plugin', 'Shop']);

        $composer = json_decode((string)file_get_contents($app . '/composer.json'), true);
        $this->assertSame(['App\\' => 'src/', 'Shop\\' => 'plugins/Shop/src/'], $composer['autoload']['psr-4']);
        $this->assertSame(['App\\Test\\' => 'tests/', 'Shop\\Test\\' => 'plugins/Shop/tests/'], $composer['autoload-dev']['psr-4']);
        $this->assertStringContainsString("namespace Shop\\Model;\n", (string)file_get_contents($app . '/plugins/Shop/src/Model/Post.php'));
    }

    /**
     * A plugin uses its name as its namespace
     *
     * @return void
     */
    public function testPluginRejectsNamespace(): void
    {
        $plugins = $this->_copy('plugin', 'plugins');
        App::build(['Plugin' => [$plugins . DS]]);

        $this->_run($this->_root, ['namespace', '--plugin', 'Blog', '--namespace', 'Acme']);

        $this->assertSame(['A plugin has the namespace of its name, as CakePHP looks its classes up as Blog\<Type>\<Class>: leave out --namespace.'], $this->_errors);
        $this->_assertSameTree($this->_fixture('plugin'), $plugins);
    }
}
