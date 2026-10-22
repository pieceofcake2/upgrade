<?php
/**
 * The home page
 *
 * @var View $this
 */
if (!Configure::read('debug')):
    throw new NotFoundException();
endif;

App::uses('Debugger', 'Utility');
?>
<h2><?php echo __d('cake_dev', 'Release Notes for CakePHP %s.', Configure::version()); ?></h2>
<?php Debugger::checkSecurityKeys(); ?>
<p><?php echo h(Slug::normalize(SITE_NAME)); ?></p>
