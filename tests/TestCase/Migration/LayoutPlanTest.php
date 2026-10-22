<?php
/**
 * LayoutPlanTest file
 *
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

use PieceOfCake2\Upgrade\Migration\LayoutPlan;

/**
 * The names of the templates in templates/
 */
class LayoutPlanTest extends CakeTestCase
{
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
