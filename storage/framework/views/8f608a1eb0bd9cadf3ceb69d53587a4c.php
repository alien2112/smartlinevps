<?php
$color = businessConfig('website_color')?->value;
$text = businessConfig('text_color')?->value;
?>


<?php if(isset($color)): ?>
    <style>
        :root {
            --text-primary: <?php echo e($color['primary'] ?? 'var(--text-primary)'); ?>;
            --text-secondary: <?php echo e($color['secondary'] ?? 'var(--text-secondary)'); ?>;
            --bs-body-bg: <?php echo e($color['background'] ?? 'var(--bs-body-bg)'); ?>;
            --bs-primary: <?php echo e($color['primary'] ?? 'var(--bs-primary)'); ?>;
            --bs-primary-rgb: <?php echo e(hexToRgb($color['primary']) ?? 'var(--bs-primary-rgb)'); ?>;
            --bs-secondary-rgb: <?php echo e(hexToRgb($color['secondary']) ?? 'var(--bs-secondary-rgb)'); ?>;
            --bs-secondary: <?php echo e($color['secondary'] ?? 'var(--bs-secondary)'); ?>;
        }
    </style>
<?php endif; ?>

<?php if(isset($text)): ?>
    <style>
        :root {
            --title-color: <?php echo e($text['primary'] ?? 'var(--title-color)'); ?>;
            --title-color-rgb: <?php echo e(hexToRgb($text['primary']) ?? 'var(--title-color-rgb)'); ?>;
            --secondary-body-color: <?php echo e($text['light'] ?? 'var(--secondary-body-color)'); ?>;
        }
    </style>
<?php endif; ?>
<?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\layouts\css.blade.php ENDPATH**/ ?>