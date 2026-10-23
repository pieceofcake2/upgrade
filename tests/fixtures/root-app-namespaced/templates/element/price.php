<?php
/**
 * @var View $this
 * @var int $cents
 */

use App\Utility\Price;
use Cake\View\View;

?>
<span class="price"><?php echo h(Price::format($cents)); ?></span>
