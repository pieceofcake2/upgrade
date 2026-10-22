<?php
/**
 * UpgradeShellTest file
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @package       Upgrade.Test.Case.Console.Command
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('Folder', 'Utility');
App::uses('UpgradeShell', 'Upgrade.Console/Command');

/**
 * UpgradeShellTestOutput
 */
class UpgradeShellTestOutput extends ConsoleOutput
{
    /**
     * @var string
     */
    public $output = '';

    /**
     * @param string $message Message to write.
     * @return int
     */
    protected function _write($message): int
    {
        $this->output .= $message;

        return strlen($message);
    }
}

/**
 * UpgradeShellTest
 *
 * Each test builds a CakePHP 1.3 style plugin in a temporary directory,
 * runs an upgrade command against it with `--plugin` and asserts the result.
 */
class UpgradeShellTest extends CakeTestCase
{
    /**
     * Name of the temporary plugin.
     *
     * @var string
     */
    public const PLUGIN = 'UpgradeTestPlugin';

    /**
     * @var UpgradeShell
     */
    public $Shell;

    /**
     * @var UpgradeShellTestOutput
     */
    public $out;

    /**
     * Directory holding the temporary plugin.
     *
     * @var string
     */
    protected $_root;

    /**
     * Path of the temporary plugin, with trailing DS.
     *
     * @var string
     */
    protected $_path;

    /**
     * Working directory before the test.
     *
     * @var string
     */
    protected $_cwd;

    /**
     * setUp
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->_cwd = getcwd();
        $this->_root = TMP . 'tests' . DS . 'upgrade_shell_' . getmypid() . DS;
        $this->_path = $this->_root . self::PLUGIN . DS;
        new Folder($this->_path, true);

        App::build(['Plugin' => [$this->_root]]);

        $this->out = new UpgradeShellTestOutput();
        $in = $this->getMock('ConsoleInput', [], [], '', false);
        $this->Shell = $this->getMock('UpgradeShell', ['in', '_stop', 'clear'], [$this->out, $this->out, $in]);
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown(): void
    {
        chdir($this->_cwd);
        CakePlugin::unload(self::PLUGIN);
        $Folder = new Folder($this->_root);
        $Folder->delete();
        unset($this->Shell, $this->out);

        parent::tearDown();
    }

    /**
     * Write a file below the temporary plugin.
     *
     * @param string $file Path relative to the plugin, using "/".
     * @param string $contents File contents.
     * @return void
     */
    protected function _write(string $file, string $contents): void
    {
        $file = $this->_path . str_replace('/', DS, $file);
        new Folder(dirname($file), true);
        file_put_contents($file, $contents);
    }

    /**
     * Read a file below the temporary plugin.
     *
     * @param string $file Path relative to the plugin, using "/".
     * @return string
     */
    protected function _read(string $file): string
    {
        $file = $this->_path . str_replace('/', DS, $file);
        $this->assertFileExists($file);

        return file_get_contents($file);
    }

    /**
     * Run an upgrade command against the temporary plugin.
     *
     * @param string $command Subcommand name.
     * @param array $options Extra CLI options.
     * @return mixed
     */
    protected function _run(string $command, array $options = []): mixed
    {
        $argv = array_merge([$command, '--plugin', self::PLUGIN], $options);

        return $this->Shell->runCommand($command, $argv);
    }

    public function testOptionParser(): void
    {
        $parser = $this->Shell->getOptionParser();
        $commands = array_keys($parser->subcommands());

        $this->assertSame([
            'all', 'tests', 'locations', 'i18n', 'helpers', 'basics', 'request',
            'configure', 'constants', 'controller_redirects', 'components', 'exceptions', 'rector', 'layout', 'namespace',
        ], $commands);

        $this->_write('README', '');
        $this->_run('tests');
        $this->assertSame(self::PLUGIN, $this->Shell->params['plugin']);
        $this->assertSame('php|ctp|thtml|inc|tpl', $this->Shell->params['ext']);
        $this->assertFalse($this->Shell->params['git']);
        $this->assertFalse($this->Shell->params['dry-run']);
    }

    public function testTests(): void
    {
        $this->_write('tests/cases/models/post.test.php', <<<'PHP'
<?php
class PostTestCase extends CakeTestCase {
}
PHP);

        $this->_run('tests');

        $this->assertSame(<<<'PHP'
<?php
class PostTest extends CakeTestCase {
}
PHP, $this->_read('tests/cases/models/post.test.php'));
    }

    public function testI18n(): void
    {
        $this->_write('views/posts/index.ctp', <<<'PHP'
<h1><?php __('Posts'); ?></h1>
<p><?php __d('blog', 'Hello', true); ?></p>
<?php echo $this->Html->link(__('Add', true), '/add'); ?>
PHP);

        $this->_run('i18n');

        $this->assertSame(<<<'PHP'
<h1><?php echo __('Posts'); ?></h1>
<p><?php echo __d('blog', 'Hello'); ?></p>
<?php echo $this->Html->link(__('Add'), '/add'); ?>
PHP, $this->_read('views/posts/index.ctp'));
    }

    public function testHelpers(): void
    {
        $this->_write('views/posts/view.ctp', <<<'PHP'
<?php echo $html->link('Home', '/'); ?>
<?php echo $form->create('Post'); ?>
<?php echo $this->Html->tag('p', 'kept'); ?>
PHP);

        $this->_run('helpers');

        $this->assertSame(<<<'PHP'
<?php echo $this->Html->link('Home', '/'); ?>
<?php echo $this->Form->create('Post'); ?>
<?php echo $this->Html->tag('p', 'kept'); ?>
PHP, $this->_read('views/posts/view.ctp'));
    }

    public function testBasics(): void
    {
        $this->_write('libs/legacy.php', <<<'PHP'
<?php
$list = a(1, 2);
e($name);
$value = ife($flag, 'yes', 'no');
$text = r('a', 'b', $text);
$upper = up($text);
$lower = low($text);
$start = getMicrotime();
$area = rectangle($a);
PHP);

        $this->_run('basics');

        $this->assertSame(<<<'PHP'
<?php
$list = array(1, 2);
echo $name;
$value = !empty($flag) ? 'yes' : 'no';
$text = str_replace('a', 'b', $text);
$upper = strtoupper($text);
$lower = strtolower($text);
$start = microtime(true);
$area = rectangle($a);
PHP, $this->_read('libs/legacy.php'));
    }

    public function testRequest(): void
    {
        $this->_write('controllers/posts_controller.php', <<<'PHP'
<?php
class PostsController extends AppController {
    function add() {
        if ($this->data) {
            $id = $this->params['pass'][0];
            $url = $this->webroot . $this->base . $this->here;
            $action = $this->action;
            $this->request->onlyAllow('post');
            $this->database();
        }
    }
}
PHP);
        $this->_write('views/posts/add.ctp', '<?php echo $this->data[\'Post\'][\'title\']; ?>');

        $this->_run('request');

        $this->assertSame(<<<'PHP'
<?php
class PostsController extends AppController {
    function add() {
        if ($this->request->data) {
            $id = $this->request->params['pass'][0];
            $url = $this->request->webroot . $this->request->base . $this->request->here;
            $action = $this->request->action;
            $this->request->allowMethod('post');
            $this->database();
        }
    }
}
PHP, $this->_read('controllers/posts_controller.php'));
        $this->assertSame('<?php echo $this->request->data[\'Post\'][\'title\']; ?>', $this->_read('views/posts/add.ctp'));
    }

    public function testConfigure(): void
    {
        $this->_write('config/bootstrap.php', <<<'PHP'
<?php
if (Configure::read() > 0) {
    Configure::read('App.encoding');
}
PHP);

        $this->_run('configure');

        $this->assertSame(<<<'PHP'
<?php
if (Configure::read('debug') > 0) {
    Configure::read('App.encoding');
}
PHP, $this->_read('config/bootstrap.php'));
    }

    public function testConstants(): void
    {
        $this->_write('libs/paths.php', <<<'PHP'
<?php
require LIBS . 'basics.php';
$paths = array(CONFIGS, CONTROLLERS, MODELS, BEHAVIORS, VIEWS, HELPERS, LAYOUTS, ELEMENTS);
$console = CONSOLE_LIBS;
$tests = array(CAKE_TESTS_LIB, CAKE_TESTS);
$keep = MY_LIBS;
PHP);

        $this->_run('constants');

        $this->assertSame(<<<'PHP'
<?php
require CAKE . 'basics.php';
$paths = array(APP . 'Config' . DS, APP . 'Controller' . DS, APP . 'Model' . DS, APP . 'Model' . DS . 'Behavior' . DS, APP . 'View' . DS, APP . 'View' . DS . 'Helper' . DS, APP . 'View' . DS . 'Layouts' . DS, APP . 'View' . DS . 'Elements' . DS);
$console = CAKE . 'Console' . DS;
$tests = array(CAKE . 'TestSuite' . DS, CAKE . 'Test' . DS);
$keep = MY_LIBS;
PHP, $this->_read('libs/paths.php'));
    }

    public function testConstantsComponents(): void
    {
        $this->_write('libs/components.php', '<?php require COMPONENTS . \'auth.php\';');

        $this->_run('constants');

        $this->assertSame('<?php require APP . \'Controller\' . DS . \'Component\' . \'auth.php\';', $this->_read('libs/components.php'));
    }

    public function testControllerRedirects(): void
    {
        $this->_write('Controller/PostsController.php', "<?php\nclass PostsController extends AppController {\n\tfunction add() {\n\t\t\$this->redirect(array('action' => 'index'));\n\t}\n}\n");

        $this->_run('controller_redirects');

        $this->assertSame(
            "<?php\nclass PostsController extends AppController {\n\tfunction add() {\n\t\treturn \$this->redirect(array('action' => 'index'));\n\t}\n}\n",
            $this->_read('Controller/PostsController.php'),
        );
    }

    public function testComponents(): void
    {
        $this->_write('Controller/Component/FooComponent.php', "<?php\nclass FooComponent extends Object {\n}\n");
        $this->_write('Controller/Component/BarComponent.php', "<?php\nclass BarComponent extends CakeObject {\n}\n");

        $this->_run('components');

        $this->assertSame("<?php\nclass FooComponent extends Component {\n}\n", $this->_read('Controller/Component/FooComponent.php'));
        $this->assertSame("<?php\nclass BarComponent extends Component {\n}\n", $this->_read('Controller/Component/BarComponent.php'));
    }

    public function testExceptions(): void
    {
        $this->_write('controllers/posts_controller.php', <<<'PHP'
<?php
class PostsController extends AppController {
    function view($id) {
        $this->cakeError('error400');
        $this->cakeError("error404");
        $this->cakeError('error500');
        $this->cakeError('error404', array('url' => $id));
    }
}
PHP);

        $this->_run('exceptions');

        $this->assertSame(<<<'PHP'
<?php
class PostsController extends AppController {
    function view($id) {
        throw new BadRequestException();
        throw new NotFoundException();
        throw new InternalErrorException();
        $this->cakeError('error404', array('url' => $id));
    }
}
PHP, $this->_read('controllers/posts_controller.php'));
    }

    public function testLocations(): void
    {
        $this->_write('controllers/posts_controller.php', "<?php\nclass PostsController extends AppController {\n}\n");
        $this->_write('controllers/components/foo.php', "<?php\nclass FooComponent extends Component {\n}\n");
        $this->_write('models/post.php', "<?php\nclass Post extends AppModel {\n}\n");
        $this->_write('Model/Comment.php', "<?php\nclass Comment extends AppModel {\n}\n");
        $this->_write('models/behaviors/sluggable.php', "<?php\nclass SluggableBehavior extends ModelBehavior {\n}\n");
        $this->_write('views/helpers/time_ago.php', "<?php\nclass TimeAgoHelper extends AppHelper {\n}\n");
        $this->_write('views/blog_posts/index.ctp', 'index');
        $this->_write('vendors/shells/import.php', "<?php\nclass ImportShell extends Shell {\n}\n");
        $this->_write('libs/my_lib.php', "<?php\nclass MyLib {\n}\n");
        $this->_write('tests/cases/models/post.test.php', "<?php\nclass PostTest extends CakeTestCase {\n}\n");
        $this->_write('tests/fixtures/post_fixture.php', "<?php\nclass PostFixture extends CakeTestFixture {\n}\n");
        $this->_write('config/schema/schema.php', "<?php\nclass AppSchema extends CakeSchema {\n}\n");

        $this->_run('locations');

        $this->assertFileExists($this->_path . 'Controller' . DS . 'PostsController.php');
        $this->assertFileExists($this->_path . 'Controller' . DS . 'Component' . DS . 'FooComponent.php');
        $this->assertFileExists($this->_path . 'Model' . DS . 'Post.php');
        $this->assertFileExists($this->_path . 'Model' . DS . 'Comment.php');
        $this->assertFileExists($this->_path . 'Model' . DS . 'Behavior' . DS . 'SluggableBehavior.php');
        $this->assertFileExists($this->_path . 'View' . DS . 'Helper' . DS . 'TimeAgoHelper.php');
        $this->assertFileExists($this->_path . 'View' . DS . 'BlogPosts' . DS . 'index.ctp');
        $this->assertFileExists($this->_path . 'Console' . DS . 'Command' . DS . 'ImportShell.php');
        $this->assertFileExists($this->_path . 'Lib' . DS . 'MyLib.php');
        $this->assertFileExists($this->_path . 'Test' . DS . 'Case' . DS . 'models' . DS . 'PostTest.php');
        $this->assertFileExists($this->_path . 'Test' . DS . 'Fixture' . DS . 'post_fixture.php');
        $this->assertFileExists($this->_path . 'Config' . DS . 'Schema' . DS . 'schema.php');

        // Old paths (only those that do not differ from the new ones by case alone).
        $this->assertFileDoesNotExist($this->_path . 'controllers' . DS . 'posts_controller.php');
        $this->assertFileDoesNotExist($this->_path . 'controllers' . DS . 'components' . DS . 'foo.php');
        $this->assertFileDoesNotExist($this->_path . 'models' . DS . 'post.php');
        $this->assertFileDoesNotExist($this->_path . 'vendors' . DS . 'shells' . DS . 'import.php');
        $this->assertFileDoesNotExist($this->_path . 'libs' . DS . 'my_lib.php');
        $this->assertFileDoesNotExist($this->_path . 'tests' . DS . 'cases' . DS . 'models' . DS . 'post.test.php');
    }

    public function testDryRunDoesNotModifyFiles(): void
    {
        $contents = "<?php\nclass FooComponent extends Object {\n}\n";
        $this->_write('Controller/Component/FooComponent.php', $contents);
        $this->_write('models/post.php', "<?php\nclass Post extends AppModel {\n}\n");

        $this->_run('components', ['--dry-run']);
        $this->_run('locations', ['--dry-run']);

        $this->assertSame($contents, $this->_read('Controller/Component/FooComponent.php'));
        $this->assertFileExists($this->_path . 'models' . DS . 'post.php');
        $this->assertFileDoesNotExist($this->_path . 'Model' . DS . 'Post.php');
        $this->assertStringContainsString('Dry-run mode enabled!', $this->out->output);
    }

    public function testExtOption(): void
    {
        $this->_write('libs/a.php', '<?php up($a);');
        $this->_write('libs/b.inc', '<?php up($b);');

        $this->_run('basics', ['--ext', 'inc']);

        $this->assertSame('<?php up($a);', $this->_read('libs/a.php'));
        $this->assertSame('<?php strtoupper($b);', $this->_read('libs/b.inc'));
    }

    public function testAll(): void
    {
        $this->_write('libs/legacy.php', <<<'PHP'
<?php
class Legacy {
    function title() {
        $debug = Configure::read();
        $path = LIBS . 'basics.php';

        return up(__('Post', true));
    }
}
PHP);

        $this->_run('all');

        $this->assertSame(<<<'PHP'
<?php
class Legacy {
    function title() {
        $debug = Configure::read('debug');
        $path = CAKE . 'basics.php';

        return strtoupper(__('Post'));
    }
}
PHP, $this->_read('Lib/Legacy.php'));
        $this->assertFileDoesNotExist($this->_path . 'libs' . DS . 'legacy.php');
        foreach (['tests', 'locations', 'i18n', 'helpers', 'basics', 'request', 'configure', 'constants', 'controller_redirects', 'components', 'exceptions'] as $command) {
            $this->assertStringContainsString('Running ' . $command, $this->out->output);
        }
        $this->assertStringNotContainsString('Running rector', $this->out->output);
        $this->assertStringNotContainsString('Running layout', $this->out->output);
        $this->assertStringNotContainsString('Running namespace', $this->out->output);
    }

    /**
     * The rules of variadic-arguments, run by Rector on a copy of the fixtures
     *
     * @return void
     */
    public function testRector(): void
    {
        $fixtures = dirname(__DIR__, 3) . DS . 'fixtures' . DS . 'rector' . DS;
        $dir = $this->_root . 'rector' . DS;
        new Folder($dir, true);
        foreach (glob($fixtures . 'variadic' . DS . '*.php') ?: [] as $file) {
            copy($file, $dir . basename($file));
        }

        ob_start();
        $code = $this->Shell->runCommand('rector', ['rector', '--rules', 'variadic-arguments', $dir . 'AppModel.php', $dir . 'Post.php', $dir . 'PostsController.php', $dir . 'Unrelated.php']);
        $output = (string)ob_get_clean();

        $this->assertSame(0, $code, $output);
        foreach (glob($fixtures . 'variadic-expected' . DS . '*.php') ?: [] as $expected) {
            $this->assertFileEquals($expected, $dir . basename($expected));
        }
    }

    /**
     * The rules of namespaced-classes, run by Rector on a copy of the fixtures
     *
     * @return void
     */
    public function testRectorNamespacedClasses(): void
    {
        $fixtures = dirname(__DIR__, 3) . DS . 'fixtures' . DS . 'rector' . DS;
        $dir = $this->_root . 'rector' . DS;
        new Folder($dir, true);
        foreach (glob($fixtures . 'namespaced-classes' . DS . '*.php') ?: [] as $file) {
            copy($file, $dir . basename($file));
        }

        ob_start();
        $code = $this->Shell->runCommand('rector', ['rector', '--rules', 'namespaced-classes', $dir . 'PostsController.php', $dir . 'Conflicts.php']);
        $output = (string)ob_get_clean();

        $this->assertSame(0, $code, $output);
        foreach (glob($fixtures . 'namespaced-classes-expected' . DS . '*.php') ?: [] as $expected) {
            $this->assertFileEquals($expected, $dir . basename($expected));
        }
        // The comparisons that stay comparisons, as the classes have subclasses
        $this->assertStringContainsString('Review these places:', $this->out->output);
        foreach ([11 => 'CakeResponse', 17 => 'Model', 20 => 'SessionComponent', 24 => 'SessionComponent', 41 => 'BadRequestException'] as $line => $class) {
            $this->assertStringContainsString(
                sprintf('PostsController.php:%d: kept the comparison with %s::class, as %s has subclasses', $line, $class, $class),
                $this->out->output,
            );
        }
        $this->assertStringNotContainsString('kept the comparison with CakeRequest::class', $this->out->output);
    }

    /**
     * The rules of class-name-strings, run by Rector on a copy of the fixtures, with the short names and without
     *
     * @return void
     */
    public function testRectorClassNameStrings(): void
    {
        $fixtures = dirname(__DIR__, 3) . DS . 'fixtures' . DS . 'rector' . DS;
        Configure::write('App.namespace', 'App');
        try {
            foreach (['class-name-strings-expected' => [], 'class-name-strings-no-short-names-expected' => ['--no-short-names']] as $expected => $options) {
                $dir = $this->_root . $expected . DS;
                new Folder($dir, true);
                foreach (glob($fixtures . 'class-name-strings' . DS . '*.php') ?: [] as $file) {
                    copy($file, $dir . basename($file));
                }

                ob_start();
                $code = $this->Shell->runCommand('rector', ['rector', '--rules', 'class-name-strings', ...$options, $dir]);
                $output = (string)ob_get_clean();

                $this->assertSame(0, $code, $output);
                foreach (glob($fixtures . $expected . DS . '*.php') ?: [] as $file) {
                    $this->assertFileEquals($file, $dir . basename($file), $expected . '/' . basename($file));
                }
            }
        } finally {
            Configure::delete('App.namespace');
        }
    }

    /**
     * The rector command passes App.namespace and --no-short-names to the rules in environment variables
     *
     * @return void
     */
    public function testRectorEnvironment(): void
    {
        $this->Shell = $this->getMock('UpgradeShell', ['in', '_stop', 'clear', '_runRector'], [$this->out, $this->out, $this->getMock('ConsoleInput', [], [], '', false)]);
        $env = [];
        $this->Shell->expects($this->exactly(2))->method('_runRector')->willReturnCallback(function (array $command) use (&$env): int {
            $env[] = [getenv('UPGRADE_SHORT_NAMES'), getenv('UPGRADE_APP_NAMESPACE'), is_file((string)getenv('UPGRADE_RECTOR_REPORT'))];

            return 0;
        });

        Configure::write('App.namespace', 'Acme');
        try {
            $this->Shell->runCommand('rector', ['rector', '--rules', 'class-name-strings', 'src']);
            $this->Shell->runCommand('rector', ['rector', '--rules', 'class-name-strings', '--no-short-names', 'src']);
        } finally {
            Configure::delete('App.namespace');
        }

        $this->assertSame([['1', 'Acme', true], ['0', 'Acme', true]], $env);
        $this->assertFalse(getenv('UPGRADE_SHORT_NAMES'));
        $this->assertFalse(getenv('UPGRADE_RECTOR_REPORT'));
    }

    /**
     * The command of Rector: the paths, the configuration of the rules and --dry-run
     *
     * @return void
     */
    public function testRectorCommand(): void
    {
        $this->Shell = $this->getMock('UpgradeShell', ['in', '_stop', 'clear', '_runRector'], [$this->out, $this->out, $this->getMock('ConsoleInput', [], [], '', false)]);
        $commands = [];
        $this->Shell->expects($this->once())->method('_runRector')->willReturnCallback(function (array $command) use (&$commands): int {
            $commands[] = $command;

            return 0;
        });

        $this->Shell->runCommand('rector', ['rector', 'src', 'tests', '--dry-run']);

        $command = $commands[0];
        $this->assertSame(PHP_BINARY, $command[0]);
        $this->assertStringEndsWith(DS . 'bin' . DS . 'rector', $command[1]);
        $this->assertSame(['process', 'src', 'tests', '--config'], array_slice($command, 2, 4));
        $this->assertStringEndsWith(DS . 'config' . DS . 'rector' . DS . 'variadic-arguments.php', $command[6]);
        $this->assertFileExists($command[6]);
        $this->assertSame('--dry-run', $command[7]);
    }

    /**
     * An unknown set of rules, or no path, is an error
     *
     * @return void
     */
    public function testRectorErrors(): void
    {
        $this->Shell = $this->getMock('UpgradeShell', ['in', '_stop', 'clear', '_runRector', 'error'], [$this->out, $this->out, $this->getMock('ConsoleInput', [], [], '', false)]);
        $this->Shell->expects($this->never())->method('_runRector');
        $errors = [];
        $this->Shell->expects($this->exactly(2))->method('error')->willReturnCallback(function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->Shell->runCommand('rector', ['rector', '--rules', 'nothing', 'src']);
        $this->Shell->args = [];
        $this->Shell->params['rules'] = 'variadic-arguments';
        $this->Shell->rector();

        $this->assertSame('Unknown rules: nothing. Available: class-name-strings, namespaced-classes, variadic-arguments', $errors[0]);
        $this->assertSame('Give the paths to process, e.g. src tests.', $errors[1]);
    }
}
