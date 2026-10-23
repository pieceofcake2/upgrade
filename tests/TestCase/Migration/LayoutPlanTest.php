<?php
/**
 * LayoutPlanTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\LayoutPlan;

App::uses('Folder', 'Utility');

/**
 * The layouts, and the names of the templates in templates/
 */
class LayoutPlanTest extends CakeTestCase
{
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
        $this->_root = rtrim((string)realpath(sys_get_temp_dir()), DS) . DS . 'upgrade-layout-plan-test-' . getmypid();
        (new Folder($this->_root))->delete();
        mkdir($this->_root, 0777, true);
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
     * A directory with these files
     *
     * @param string $name The name of the directory
     * @param array<string> $files The files, relative to it
     * @param string|null $composer The contents of composer.json
     * @return string The directory
     */
    protected function _dir(string $name, array $files, ?string $composer = null): string
    {
        $dir = $this->_root . DS . $name;
        foreach ($files as $file) {
            $path = $dir . DS . str_replace('/', DS, $file);
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, '<?php');
        }
        if ($composer !== null) {
            file_put_contents($dir . DS . 'composer.json', $composer);
        }

        return $dir;
    }

    /**
     * The layouts, from the names of the files with their case
     *
     * @return void
     */
    public function testDetect(): void
    {
        $fixtures = dirname(__DIR__, 2) . DS . 'fixtures' . DS;
        $this->assertSame(LayoutPlan::APP_DIRECTORY, LayoutPlan::detect($fixtures . 'legacy-app'));
        $this->assertSame(LayoutPlan::ROOT_APP, LayoutPlan::detect($fixtures . 'legacy-root-app'));
        $this->assertSame(LayoutPlan::NEW_LAYOUT, LayoutPlan::detect($fixtures . 'app'));
        $this->assertSame(LayoutPlan::NEW_LAYOUT, LayoutPlan::detect($fixtures . 'root-app'));
        $this->assertSame(LayoutPlan::PLUGIN, LayoutPlan::detect($fixtures . 'legacy-plugin' . DS . 'Blog'));
        $this->assertSame(LayoutPlan::PLUGIN, LayoutPlan::detect($fixtures . 'plugin' . DS . 'Blog'));
        $this->assertSame(LayoutPlan::NONE, LayoutPlan::detect($this->_root));

        // Without app/, with some directories in lowercase already
        $mixed = $this->_dir('mixed', ['config/core.php', 'Controller/AppController.php', 'View/Pages/home.ctp', 'webroot/index.php', 'tmp/logs/empty', 'vendor/autoload.php']);
        $this->assertSame(LayoutPlan::ROOT_APP, LayoutPlan::detect($mixed));
        // A plugin: its composer.json says so, or it has no core.php
        $plugin = $this->_dir('typed', ['Config/core.php', 'Controller/AppController.php', 'webroot/index.php'], '{"name": "acme/typed", "type": "cakephp-plugin"}');
        $this->assertSame(LayoutPlan::PLUGIN, LayoutPlan::detect($plugin));
        $plugin = $this->_dir('plain', ['Config/routes.php', 'Controller/PlainAppController.php', 'webroot/css/plain.css']);
        $this->assertSame(LayoutPlan::PLUGIN, LayoutPlan::detect($plugin));
        // The new layout: no Controller/ in the root, also with a capitalised name of another directory
        $new = $this->_dir('new', ['config/core.php', 'src/Controller/AppController.php', 'webroot/index.php']);
        $this->assertSame(LayoutPlan::NEW_LAYOUT, LayoutPlan::detect($new));
        $this->assertSame(LayoutPlan::NEW_LAYOUT, LayoutPlan::detect($this->_dir('config-only', ['config/bootstrap.php'])));
    }

    /**
     * Without app/: the moves from the root, with the case of the names; webroot/, tmp/ and the vendor/ of Composer
     * stay
     *
     * @return void
     */
    public function testRootApplication(): void
    {
        $root = $this->_dir('root', ['config/core.php', 'Controller/AppController.php', 'Locale/eng/LC_MESSAGES/default.po', 'Test/Case/AllTestsTest.php', 'View/Helper/AppHelper.php', 'View/Posts/index.ctp', 'Plugin/Blog/Model/Post.php', 'webroot/index.php', 'tmp/logs/empty', 'vendor/autoload.php']);

        $plan = LayoutPlan::forApplication($root);

        $moves = array_map(static fn(array $move): string => substr($move[0], strlen($root) + 1) . ' => ' . substr($move[1], strlen($root) + 1), $plan->moves);
        $this->assertSame([
            'Controller => src/Controller',
            'View/Helper => src/View/Helper',
            'View/Posts/index.ctp => templates/Posts/index.php',
            'Locale => resources/locales',
            'Test/Case => tests/TestCase',
            'Plugin => plugins',
            'tmp/logs => logs',
        ], str_replace(DS, '/', $moves));
        $this->assertSame(LayoutPlan::ROOT_APP, $plan->layout);
        $this->assertSame(['Controller', 'View', 'Locale', 'Test', 'Plugin', 'templates'], $plan->sources);
    }

    /**
     * The directories and extensions of CakePHP 5, also in themes and plugins
     *
     * @return void
     */
    public function testTemplatePath(): void
    {
        $plan = LayoutPlan::forPlugin(sys_get_temp_dir() . DS . 'upgrade-no-plugin-' . getmypid());
        $paths = [
            'Posts/index.ctp' => 'Posts/index.php',
            'Posts/view.thtml' => 'Posts/view.php',
            'Posts/json/index.ctp' => 'Posts/json/index.php',
            'Layouts/default.ctp' => 'layout/default.php',
            'Layouts/Emails/html/default.ctp' => 'layout/email/html/default.php',
            'Layouts/rss/default.ctp' => 'layout/rss/default.php',
            'Elements/Flash/default.ctp' => 'element/Flash/default.php',
            'Emails/text/welcome.ctp' => 'email/text/welcome.php',
            'Errors/error400.ctp' => 'Error/error400.php',
            'Scaffolds/index.ctp' => 'Scaffolds/index.ctp',
            'Pages/Layouts/home.ctp' => 'Pages/Layouts/home.php',
            'Themed/Dark/Layouts/default.ctp' => 'Themed/Dark/layout/default.php',
            'Themed/Dark/webroot/css/dark.css' => 'Themed/Dark/webroot/css/dark.css',
            'Themed/Dark/Plugin/Blog/Elements/menu.ctp' => 'Themed/Dark/Plugin/Blog/element/menu.php',
            'Plugin/Blog/Layouts/blog.ctp' => 'Plugin/Blog/layout/blog.php',
            'Plugin/Blog/Posts/index.ctp' => 'Plugin/Blog/Posts/index.php',
            'home.ctp' => 'home.php',
            'Layouts' => 'Layouts',
            'Posts/notes.txt' => 'Posts/notes.txt',
        ];
        foreach ($paths as $path => $expected) {
            $this->assertSame($expected, $plan->templatePath($path), $path);
        }
        $this->assertSame([], $plan->moves);
    }
}
