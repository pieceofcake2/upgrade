<?php foreach ($products as $product): ?>
    <p><?php echo $this->element('price', ['cents' => $product['Product']['price']]); ?></p>
<?php endforeach; ?>
