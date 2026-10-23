<?php
/**
 * EntryFilesTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\EntryFiles;

/**
 * The entry files of the layout of pieceofcake2/app
 */
class EntryFilesTest extends CakeTestCase
{
    /**
     * An entry file of the CakePHP 2 layout defines its paths and loads no define.php
     *
     * @return void
     */
    public function testIsOld(): void
    {
        $this->assertTrue(EntryFiles::isOld("<?php\ndefine('APP_DIR', basename(dirname(dirname(__FILE__))));\n"));
        $this->assertTrue(EntryFiles::isOld("<?php\ndefine(\"CAKE_CORE_INCLUDE_PATH\", ROOT . DS . 'lib');\n"));
        $this->assertFalse(EntryFiles::isOld("<?php\nrequire dirname(__DIR__) . DS . 'Config' . DS . 'define.php';\nif (getenv('LAMBDA_TASK_ROOT') !== false) {\n    define('TMP', '/tmp/');\n}\n"));
        $this->assertFalse(EntryFiles::isOld("<?php\nif (!defined('APP_DIR')) {\n    define('APP_DIR', 'app');\n}\nrequire dirname(__DIR__) . '/config/define.php';\n"));
    }

    /**
     * A define.php in the old form defines CAKE_CORE_INCLUDE_PATH, or ROOT and APP_DIR for the CakePHP 2 layout
     *
     * @return void
     */
    public function testIsOldDefine(): void
    {
        $skeleton = (string)file_get_contents(dirname(__DIR__, 3) . DS . 'resources' . DS . 'skeleton' . DS . 'config' . DS . 'define.php');
        $this->assertFalse(EntryFiles::isOldDefine($skeleton));
        $this->assertFalse(EntryFiles::isOldDefine("<?php\nif (getenv('LAMBDA_TASK_ROOT') !== false) {\n    define('TMP', '/tmp/');\n}\n"));
        $this->assertTrue(EntryFiles::isOldDefine("<?php\ndefine('ROOT', dirname(__DIR__, 2));\ndefine('APP_DIR', basename(dirname(__DIR__)));\n"));
        $this->assertTrue(EntryFiles::isOldDefine(str_replace("'src'", "'app'", $skeleton)));
        $this->assertTrue(EntryFiles::isOldDefine("<?php\ndefine('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'lib');\n"));
    }

    /**
     * The paths of the moved directories in the strings of a kept entry file
     *
     * @return void
     */
    public function testMovePaths(): void
    {
        $code = "<?php\nrequire_once dirname(__DIR__) . DS . 'Config' . DS . 'define.php';\nrequire \"Config/bootstrap_cli.php\";\n\$x = 'Configure';\n\$y = 'vendor';\n";

        [$moved, $changes] = EntryFiles::movePaths($code, ['Config' => 'config', 'Locale' => 'resources/locales', 'Plugin' => 'plugins']);

        $this->assertSame("<?php\nrequire_once dirname(__DIR__) . DS . 'config' . DS . 'define.php';\nrequire \"config/bootstrap_cli.php\";\n\$x = 'Configure';\n\$y = 'vendor';\n", $moved);
        $this->assertSame(["'Config' => 'config'", '"Config/bootstrap_cli.php" => "config/bootstrap_cli.php"'], $changes);
        $this->assertSame(["RewriteRule ^ Config [L]\n", []], EntryFiles::movePaths("RewriteRule ^ Config [L]\n", ['Config' => 'config']));
    }

    /**
     * What to check in a kept config/define.php
     *
     * @return void
     */
    public function testDefineNotice(): void
    {
        $skeleton = (string)file_get_contents(dirname(__DIR__, 3) . DS . 'resources' . DS . 'skeleton' . DS . 'config' . DS . 'define.php');
        $this->assertNull(EntryFiles::defineNotice($skeleton));
        $notice = (string)EntryFiles::defineNotice(str_replace("'src'", 'basename(dirname(__DIR__))', $skeleton));
        $this->assertStringContainsString("as it defines APP_DIR with other values than the new layout (ROOT is the root of the project, APP_DIR is 'src').", $notice);
        $this->assertStringNotContainsString('need', $notice);

        $notice = (string)EntryFiles::defineNotice("<?php\ndefine('WWW_ROOT', '/srv/www/');\n");
        $this->assertStringNotContainsString('it defines', $notice);
        $this->assertStringContainsString('the entry files of pieceofcake2/app need ROOT, APP_DIR, WEBROOT_DIR, VENDORS', $notice);
    }
}
