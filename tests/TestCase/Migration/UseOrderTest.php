<?php
/**
 * UseOrderTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PhpParser\ParserFactory;
use PieceOfCake2\Upgrade\Migration\UseOrder;

/**
 * The order of the use statements of the CakePHP coding standard
 */
class UseOrderTest extends CakeTestCase
{
    /**
     * Sorts the use statements of the code
     *
     * @param string $code The use statements
     * @return array<string>
     */
    protected function _sort(string $code): array
    {
        $stmts = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php\n" . $code) ?? [];
        $code = "<?php\n" . $code;

        return UseOrder::sort(array_map(static fn($stmt): array => [$stmt, substr($code, $stmt->getStartFilePos(), $stmt->getEndFilePos() - $stmt->getStartFilePos() + 1)], $stmts));
    }

    /**
     * By name, part by part and without case; classes, then functions, then constants
     *
     * @return void
     */
    public function testSort(): void
    {
        $code = implode("\n", [
            'use const Cake\VERSION;',
            'use function Cake\h;',
            'use Symfony\Component\Dotenv\Dotenv;',
            'use Cake\Core\Configure;',
            'use Cake\Core\App;',
            'use ArrayObject;',
            'use App\Model\Post;',
            'use Cake\Core\CakePlugin;',
            'use Cake\Cache\Engine\FileEngine;',
            'use Cake\Cache\Cache;',
            'use Cake\Controller\Component\Acl\DbAcl;',
            'use Cake\Controller\Controller;',
            'use App\Controller\{AppController, PostsController};',
        ]);

        $this->assertSame([
            'use App\Controller\{AppController, PostsController};',
            'use App\Model\Post;',
            'use ArrayObject;',
            'use Cake\Cache\Cache;',
            'use Cake\Cache\Engine\FileEngine;',
            'use Cake\Controller\Component\Acl\DbAcl;',
            'use Cake\Controller\Controller;',
            'use Cake\Core\App;',
            'use Cake\Core\CakePlugin;',
            'use Cake\Core\Configure;',
            'use Symfony\Component\Dotenv\Dotenv;',
            'use function Cake\h;',
            'use const Cake\VERSION;',
        ], $this->_sort($code));
    }
}
