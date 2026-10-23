<?php
/**
 * HeaderFormatterTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\HeaderFormatter;

/**
 * The order of the docblock of the file, the namespace and the use statements
 */
class HeaderFormatterTest extends CakeTestCase
{
    /**
     * The docblock of the file goes above the namespace, the docblock of the class stays with the class, the use
     * statements are sorted
     *
     * @return void
     */
    public function testFileDocblock(): void
    {
        $code = "<?php\nnamespace App\\Model;\nuse Cake\\Model\\Model;\nuse ArrayObject;\n/**\n * The file\n */\n\n/**\n * The class\n */\nclass Post extends Model\n{\n}\n";

        $this->assertSame(
            "<?php\n/**\n * The file\n */\n\nnamespace App\\Model;\n\nuse ArrayObject;\nuse Cake\\Model\\Model;\n\n/**\n * The class\n */\nclass Post extends Model\n{\n}\n",
            HeaderFormatter::format($code),
        );
    }

    /**
     * The docblock of a file whose first statement is not a class
     *
     * @return void
     */
    public function testDocblockOfOtherStatement(): void
    {
        $code = "<?php\n\n\nnamespace App\\Lib;\n/**\n * Helpers\n */\nconst VERSION = 1;\n\nclass Tool\n{\n}\n";

        $this->assertSame("<?php\n/**\n * Helpers\n */\n\nnamespace App\\Lib;\n\nconst VERSION = 1;\n\nclass Tool\n{\n}\n", HeaderFormatter::format($code));
    }

    /**
     * Without docblock, a blank line after the opening tag; a single docblock of a class stays with it; declare
     * comes before the namespace
     *
     * @return void
     */
    public function testWithoutFileDocblock(): void
    {
        $code = "<?php\ndeclare(strict_types=1);\nnamespace App\\Lib;\n\n\n/**\n * The class\n */\nclass Tool\n{\n}\n";

        $this->assertSame("<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Lib;\n\n/**\n * The class\n */\nclass Tool\n{\n}\n", HeaderFormatter::format($code));
    }

    /**
     * A file without a namespace changes only when it starts with use statements: they go after its docblock
     *
     * @return void
     */
    public function testGlobalFile(): void
    {
        $code = "<?php\nuse Cake\\Error\\ErrorHandler;\n\n/**\n * Core configuration\n */\n\n/**\n * Errors\n */\nConfigure::write('Error', ['handler' => [ErrorHandler::class, 'handleError']]);\n";

        $this->assertSame(
            "<?php\n/**\n * Core configuration\n */\n\nuse Cake\\Error\\ErrorHandler;\n\n/**\n * Errors\n */\nConfigure::write('Error', ['handler' => [ErrorHandler::class, 'handleError']]);\n",
            HeaderFormatter::format($code),
        );

        $code = "<?php\n/**\n * Routes\n */\nRouter::connect('/', []);\n";
        $this->assertSame($code, HeaderFormatter::format($code));
    }

    /**
     * An indented docblock keeps its indentation, as in config/routes.php of CakePHP 2, and the use statements get
     * it, as in config/routes.php of pieceofcake2/app
     *
     * @return void
     */
    public function testIndentedDocblock(): void
    {
        $code = "<?php\nuse Cake\\Routing\\Router;\n    /**\n     * Routes configuration\n     */\n\n    /**\n     * The home page\n     */\n    Router::connect('/', []);\n";

        $this->assertSame(
            "<?php\n    /**\n     * Routes configuration\n     */\n\n    use Cake\\Routing\\Router;\n\n    /**\n     * The home page\n     */\n    Router::connect('/', []);\n",
            HeaderFormatter::format($code),
        );
    }

    /**
     * The use statements are sorted, the original ones with the new ones, as in config/core.php of pieceofcake2/app
     *
     * @return void
     */
    public function testSortedUses(): void
    {
        $code = "<?php
/**
 * Core configuration
 */

use Symfony\Component\Dotenv\Dotenv;
use Cake\Core\Configure;
use Cake\Cache\Cache;

(new Dotenv())->bootEnv(ROOT . '/config/.env');
Configure::write('debug', 0);
";

        $this->assertSame(
            "<?php
/**
 * Core configuration
 */

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(ROOT . '/config/.env');
Configure::write('debug', 0);
",
            HeaderFormatter::format($code),
        );
    }

    /**
     * Files that are formatted already, and files that cannot be formatted: templates, braces, invalid code, CRLF
     *
     * @return void
     */
    public function testUnchanged(): void
    {
        $formatted = "<?php\n/**\n * The file\n */\n\nnamespace App\\Model;\n\nuse Cake\\Model\\Model;\n\nclass Post extends Model\n{\n}\n";
        $this->assertSame($formatted, HeaderFormatter::format($formatted));

        $codes = [
            "<h1><?php echo 'title'; ?></h1>\n",
            "<?php\nnamespace App {\n    class A\n    {\n    }\n}\n",
            "<?php\nnamespace A;\nclass A {}\nnamespace B;\nclass B {}\n",
            "<?php\nclass {\n",
            "<?php\nnamespace App;\n",
        ];
        foreach ($codes as $code) {
            $this->assertSame($code, HeaderFormatter::format($code));
        }

        $this->assertSame(
            "<?php\r\n\r\nnamespace App;\r\n\r\nuse A;\r\n\r\nclass B\r\n{\r\n}\r\n",
            HeaderFormatter::format("<?php\r\nnamespace App;\r\nuse A;\r\nclass B\r\n{\r\n}\r\n"),
        );
    }
}
