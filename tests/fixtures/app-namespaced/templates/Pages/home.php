<?php
/**
 * The home page
 *
 * @var View $this
 */

use App\Utility\Slug;
use Cake\Core\Configure;
use Cake\Error\NotFoundException;
use Cake\Utility\Debugger;
use Cake\View\View;

if (!Configure::read('debug')):
    throw new NotFoundException();
endif;
?>
<h2><?php echo __d('cake_dev', 'Release Notes for CakePHP %s.', Configure::version()); ?></h2>
<?php Debugger::checkSecurityKeys(); ?>
<p><?php echo h(Slug::normalize(SITE_NAME)); ?></p>
<p><?php echo __d('cake_dev', 'To change its layout, edit: %s.', '<code>View/Layouts/default.ctp</code>'); ?></p>
