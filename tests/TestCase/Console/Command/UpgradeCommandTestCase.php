<?php
/**
 * UpgradeCommandTestCase file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('Folder', 'Utility');
App::uses('UpgradeShell', 'Upgrade.Console/Command');

/**
 * The output of the shell, kept in a string
 */
class UpgradeCommandTestOutput extends ConsoleOutput
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
 * The base of the tests of `upgrade layout` and `upgrade namespace`: copies a fixture of `tests/fixtures/` to a
 * temporary directory outside the Git repository of the plugin, and runs a command in it
 */
abstract class UpgradeCommandTestCase extends CakeTestCase
{
    /**
     * @var UpgradeShell
     */
    public $Shell;

    /**
     * @var UpgradeCommandTestOutput
     */
    public $out;

    /**
     * The temporary directory
     *
     * @var string
     */
    protected $_root;

    /**
     * Working directory before the test
     *
     * @var string
     */
    protected $_cwd;

    /**
     * The directory of the application that the console runs, or null for the working directory
     *
     * @var string|null
     */
    protected $_app;

    /**
     * The errors of the shell
     *
     * @var array<string>
     */
    protected $_errors = [];

    /**
     * setUp
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->_cwd = getcwd();
        $this->_root = rtrim((string)realpath(sys_get_temp_dir()), DS) . DS . 'upgrade-command-test-' . getmypid() . DS;
        (new Folder($this->_root))->delete();
        mkdir($this->_root, 0777, true);
        $this->_errors = [];

        $this->out = new UpgradeCommandTestOutput();
        $in = $this->getMock('ConsoleInput', [], [], '', false);
        $this->Shell = $this->getMock('UpgradeShell', ['in', '_stop', 'clear', 'error', '_appDirectory'], [$this->out, $this->out, $in]);
        $this->Shell->expects($this->any())->method('error')->willReturnCallback(function (string $message): void {
            $this->_errors[] = $message;
        });
        // The console runs the application of the working directory, as Console/cake -root "$PWD" -app "$PWD" does
        $this->_app = null;
        $this->Shell->expects($this->any())->method('_appDirectory')->willReturnCallback(fn(): string => $this->_app ?? (string)getcwd() . DS);
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown(): void
    {
        chdir($this->_cwd);
        foreach (['Blog', 'Shop'] as $plugin) {
            CakePlugin::unload($plugin);
        }
        (new Folder($this->_root))->delete();
        unset($this->Shell, $this->out);

        parent::tearDown();
    }

    /**
     * The directory of the fixtures
     *
     * @param string $name A fixture
     * @return string
     */
    protected function _fixture(string $name): string
    {
        return dirname(__DIR__, 3) . DS . 'fixtures' . DS . str_replace('/', DS, $name);
    }

    /**
     * Copies a fixture to the temporary directory
     *
     * @param string $fixture The fixture, a directory of `tests/fixtures/`
     * @param string $name The name of the copy
     * @return string The path of the copy
     */
    protected function _copy(string $fixture, string $name): string
    {
        $copy = $this->_root . $name;
        (new Folder($this->_fixture($fixture)))->copy(['to' => $copy, 'mode' => 0777]);

        return $copy;
    }

    /**
     * Makes a directory a Git repository with one commit of its files
     *
     * @param string $dir The directory
     * @param array<string> $untracked Paths that Git does not track, relative to the directory
     * @return void
     */
    protected function _commit(string $dir, array $untracked = []): void
    {
        exec('git -C ' . escapeshellarg($dir) . ' init -q 2>&1', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('git is not available');
        }
        $excludes = array_map(static fn(string $path): string => escapeshellarg(':!' . $path), $untracked);
        exec('git -C ' . escapeshellarg($dir) . ' add -A -- . ' . implode(' ', $excludes));
        exec('git -C ' . escapeshellarg($dir) . ' -c user.name=Test -c user.email=test@example.com -c commit.gpgsign=false commit -q -m Legacy');
    }

    /**
     * Runs a command of the shell in a directory, with the output of Rector
     *
     * @param string $dir The working directory
     * @param array<string> $argv The command and its options
     * @return string The output of Rector
     */
    protected function _run(string $dir, array $argv): string
    {
        chdir($dir);
        ob_start();
        try {
            $this->Shell->runCommand($argv[0], $argv);
        } finally {
            $output = (string)ob_get_clean();
            chdir($this->_cwd);
        }

        return $output;
    }

    /**
     * The files of a directory, relative to it, without the ones of Git and Composer (`.git/`, `vendor/`)
     *
     * @param string $dir The directory
     * @return array<string>
     */
    protected function _files(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace(DS, '/', substr($file->getPathname(), strlen($dir) + 1));
            if (!str_starts_with($relative, '.git/') && !str_starts_with($relative, 'vendor/')) {
                $files[] = $relative;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Asserts that two directories hold the same files with the same contents
     *
     * @param string $expected The expected directory
     * @param string $actual The directory
     * @return void
     */
    protected function _assertSameTree(string $expected, string $actual): void
    {
        $this->assertSame($this->_files($expected), $this->_files($actual));
        foreach ($this->_files($expected) as $file) {
            $this->assertFileEquals($expected . DS . $file, $actual . DS . $file, $file);
        }
    }
}
