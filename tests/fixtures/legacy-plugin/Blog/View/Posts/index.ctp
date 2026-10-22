<?php echo $this->element('Blog.sidebar'); ?>
<?php foreach ($posts as $post): ?>
<h2><?php echo h($post['Post']['title']); ?></h2>
<?php endforeach; ?>
