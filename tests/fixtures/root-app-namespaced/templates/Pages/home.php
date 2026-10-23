<?php
/**
 * The home page
 *
 * @var View $this
 */

use Cake\Core\Configure;
use Cake\View\View;

?>
<h2>Acme Shop on CakePHP <?php echo h(Configure::version()); ?></h2>
<?php echo $this->element('price', ['cents' => 1200]); ?>
