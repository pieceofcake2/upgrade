<?php
use Cake\Utility\CakeTime;
?>
<?php echo $this->element('Blog.sidebar'); ?>
<?php foreach ($posts as $post): ?>
<h2><?php echo h($post['Post']['title']); ?></h2>
<p><?php echo CakeTime::nice($post['Post']['created']); ?></p>
<?php endforeach; ?>
