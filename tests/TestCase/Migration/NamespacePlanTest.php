<?php
/**
 * NamespacePlanTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\NamespacePlan;

App::uses('Folder', 'Utility');

/**
 * The moves and namespaces of `upgrade namespace`
 */
class NamespacePlanTest extends CakeTestCase
{
    /**
     * The classes of CakePHP of the tests
     *
     * @var array<string, string>
     */
    public const CORE = [
        'AppShell' => 'Cake\Console\Command\AppShell',
        'Dispatcher' => 'Cake\Routing\Dispatcher',
        'SessionComponent' => 'Cake\Controller\Component\SessionComponent',
    ];

    /**
     * @var string
     */
    protected $_root;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->_root = rtrim((string)realpath(sys_get_temp_dir()), DS) . DS . 'upgrade-plan-test-' . getmypid();
        (new Folder($this->_root))->delete();
        new Folder($this->_root, true);
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        (new Folder($this->_root))->delete();
        parent::tearDown();
    }

    /**
     * @param string $file Path relative to the root
     * @param string $contents Contents
     * @return void
     */
    protected function _write(string $file, string $contents = "<?php\n"): void
    {
        $path = $this->_root . DS . str_replace('/', DS, $file);
        new Folder(dirname($path), true);
        file_put_contents($path, $contents);
    }

    /**
     * @param string $path Path relative to the root
     * @return string
     */
    protected function _path(string $path): string
    {
        return $this->_root . DS . str_replace('/', DS, $path);
    }

    /**
     * An array sorted by key, or by value
     *
     * @param array<string, string> $array The array
     * @param bool $byValue Whether to sort by value
     * @return array<string, string>
     */
    protected function _sorted(array $array, bool $byValue = false): array
    {
        $byValue ? asort($array) : ksort($array);

        return $array;
    }

    /**
     * The layouts that `namespace` refuses
     *
     * @return void
     */
    public function testLegacyLayout(): void
    {
        $this->assertNull(NamespacePlan::legacyLayout($this->_root, false));
        $this->assertNull(NamespacePlan::legacyLayout($this->_root, true));

        $this->_write('View/Helper/BlogHelper.php');
        $this->_write('View/BlogView.php');
        $this->assertNull(NamespacePlan::legacyLayout($this->_root, true));

        $this->_write('View/Posts/index.ctp');
        $this->assertSame('View/Posts', NamespacePlan::legacyLayout($this->_root, true));

        $this->_write('Locale/eng/LC_MESSAGES/blog.po');
        $this->assertSame('Locale/', NamespacePlan::legacyLayout($this->_root, true));

        $this->_write('app/Config/core.php');
        $this->assertSame('app/Config/', NamespacePlan::legacyLayout($this->_root, false));
    }

    /**
     * An application: src/Lib/<Type>/ moves to src/<Type>/, a core class override to src/Cake/, the namespaces come
     * from the paths
     *
     * @return void
     */
    public function testApplication(): void
    {
        $this->_write('src/Lib/Routing/Route/SlugRoute.php', "<?php\nclass SlugRoute extends CakeRoute\n{\n}\n");
        $this->_write('src/Lib/Routing/Dispatcher.php', "<?php\nclass Dispatcher\n{\n}\n");
        $this->_write('src/Lib/MyTool.php', "<?php\nclass MyTool\n{\n}\ninterface MyToolInterface\n{\n}\n");
        $this->_write('src/Console/Command/AppShell.php', "<?php\nclass AppShell extends Shell\n{\n}\n");
        $this->_write('src/Console/Templates/skel/Controller/AppController.php', "<?php\nclass AppController extends Controller\n{\n}\n");
        $this->_write('src/Controller/Component/SessionComponent.php', "<?php\nnamespace App\\Controller\\Component;\n\nclass SessionComponent\n{\n}\n");
        $this->_write('tests/TestCase/Lib/MyToolTest.php', "<?php\nclass MyToolTest extends CakeTestCase\n{\n}\n");
        $this->_write('tests/bootstrap.php', "<?php\nrequire 'vendor/autoload.php';\n");

        $plan = NamespacePlan::create($this->_root, 'Acme', false, self::CORE);

        $this->assertSame([
            [$this->_path('src/Lib/Routing'), $this->_path('src/Routing')],
            [$this->_path('src/Routing/Dispatcher.php'), $this->_path('src/Cake/Routing/Dispatcher.php')],
        ], $plan->moves);
        $this->assertSame(['src/Lib/Routing' => 'src/Routing'], $plan->paths);
        $this->assertSame([
            $this->_path('src/Cake/Routing/Dispatcher.php') => 'Cake\Routing',
            $this->_path('src/Console/Command/AppShell.php') => 'Acme\Console\Command',
            $this->_path('src/Lib/MyTool.php') => 'Acme\Lib',
            $this->_path('src/Routing/Route/SlugRoute.php') => 'Acme\Routing\Route',
            $this->_path('tests/TestCase/Lib/MyToolTest.php') => 'Acme\Test\TestCase\Lib',
        ], $this->_sorted($plan->files));
        $this->assertSame([
            'AppShell' => 'Acme\Console\Command\AppShell',
            'MyTool' => 'Acme\Lib\MyTool',
            'MyToolInterface' => 'Acme\Lib\MyToolInterface',
            'SlugRoute' => 'Acme\Routing\Route\SlugRoute',
            'MyToolTest' => 'Acme\Test\TestCase\Lib\MyToolTest',
            'Dispatcher' => 'Cake\Routing\Dispatcher',
        ], $this->_sorted($plan->classes, true));
        $this->assertSame([$this->_path('src/Cake/Routing/Dispatcher.php')], $plan->aliases);
        $this->assertSame(['Routing/Dispatcher.php'], $plan->overridden);
        $this->assertSame([$this->_path('src'), $this->_path('tests')], $plan->codePaths());
        $this->assertSame([], $plan->configPaths());
    }

    /**
     * The files that stay global, and the classes declared twice
     *
     * @return void
     */
    public function testNotNamespaced(): void
    {
        $this->_write('src/Lib/functions.php', "<?php\nclass Formatter\n{\n}\nfunction format_price()\n{\n}\n");
        $this->_write('src/Lib/template.php', "<?php\nclass Page\n{\n}\n?>\n<html></html>\n");
        $this->_write('src/Lib/broken.php', "<?php\nclass {\n");
        $this->_write('src/Lib/my-lib/Tool.php', "<?php\nclass Tool\n{\n}\n");
        $this->_write('tests/TestCase/A/HelperTest.php', "<?php\nclass TestHelper\n{\n}\n");
        $this->_write('tests/TestCase/B/HelperTest.php', "<?php\nclass TestHelper\n{\n}\n");

        $plan = NamespacePlan::create($this->_root, 'App', false, self::CORE);

        $this->assertSame([], array_values(array_filter(array_keys($plan->files), fn(string $file): bool => str_starts_with($file, $this->_path('src')))));
        $this->assertSame([], $plan->classes);
        $notices = implode("\n", $plan->notices);
        $this->assertStringContainsString('src/Lib/functions.php was not namespaced: it also declares functions', $notices);
        $this->assertStringContainsString('src/Lib/template.php was not namespaced: it has HTML outside the PHP tags', $notices);
        $this->assertStringContainsString('src/Lib/broken.php was not namespaced: Syntax error', $notices);
        $this->assertStringContainsString('src/Lib/my-lib/Tool.php was not namespaced: its directories are no valid namespace', $notices);
        $this->assertStringContainsString('The class TestHelper is declared in tests/TestCase/A/HelperTest.php, tests/TestCase/B/HelperTest.php', $notices);
    }

    /**
     * A plugin: the class folders move into src/, the namespace is the name of the plugin, no core class override
     *
     * @return void
     */
    public function testPlugin(): void
    {
        $this->_write('Controller/PostsController.php', "<?php\nclass PostsController extends BlogAppController\n{\n}\n");
        $this->_write('Lib/Cache/Engine/BlogEngine.php', "<?php\nclass BlogEngine extends CacheEngine\n{\n}\n");
        $this->_write('Lib/Markdown.php', "<?php\nclass Markdown\n{\n}\n");
        $this->_write('Lib/Routing/Dispatcher.php', "<?php\nclass Dispatcher\n{\n}\n");
        $this->_write('View/Helper/MarkdownHelper.php', "<?php\nclass MarkdownHelper extends AppHelper\n{\n}\n");
        $this->_write('config/bootstrap.php');
        $this->_write('templates/Posts/index.php', "<h1>Posts</h1>\n");

        $plan = NamespacePlan::create($this->_root, 'Blog', true, self::CORE);

        $this->assertSame([
            [$this->_path('Lib/Cache'), $this->_path('src/Cache')],
            [$this->_path('Lib/Routing'), $this->_path('src/Routing')],
            [$this->_path('Lib'), $this->_path('src/Lib')],
            [$this->_path('Controller'), $this->_path('src/Controller')],
            [$this->_path('View'), $this->_path('src/View')],
        ], $plan->moves);
        $this->assertSame([
            'BlogEngine' => 'Blog\Cache\Engine\BlogEngine',
            'PostsController' => 'Blog\Controller\PostsController',
            'Markdown' => 'Blog\Lib\Markdown',
            'Dispatcher' => 'Blog\Routing\Dispatcher',
            'MarkdownHelper' => 'Blog\View\Helper\MarkdownHelper',
        ], $this->_sorted($plan->classes, true));
        $this->assertSame([], $plan->aliases);
        $this->assertSame([$this->_path('src')], $plan->codePaths());
        $this->assertSame([$this->_path('config'), $this->_path('templates')], $plan->configPaths());
    }
}
