<h2><?php echo h($message); ?></h2>
<?php
if (Configure::read('debug') > 0):
    echo $this->element('exception_stack_trace');
endif;
