<?php
/**
 * The home page
 *
 * @var View $this
 */
use Cake\Core\Configure;
use Cake\Error\NotFoundException;
use Cake\Utility\Debugger;
use App\Utility\Slug;

if (!Configure::read('debug')):
    throw new NotFoundException();
endif;
?>
<h2><?php echo __d('cake_dev', 'Release Notes for CakePHP %s.', Configure::version()); ?></h2>
<?php Debugger::checkSecurityKeys(); ?>
<p><?php echo h(Slug::normalize(SITE_NAME)); ?></p>
