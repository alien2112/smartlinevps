<?php $__env->startSection('title', 'Terms & Conditions'); ?>


<?php $__env->startSection('content'); ?>
    <!-- Page Header Start -->
    <div class="container pt-3">
        <section class="page-header bg__img"
                 data-img="<?php echo e($data?->value['image'] ? asset('storage/app/public/business/pages/'.$data?->value['image']) : asset('public/landing-page/assets/img/page-header.png')); ?>"
                 style="background-image: url(<?php echo e($data?->value['image'] ? asset('storage/app/public/business/pages/'.$data?->value['image']) : asset('public/landing-page/assets/img/page-header.png')); ?>);">
            <h1 class="title"><?php echo e(translate('Terms & Condition')); ?></h1>
            <p class="mt-2">
                <?php echo e($data?->value['short_description'] ?? ""); ?>

            </p>
        </section>
    </div>
    <!-- Page Header End -->
    <section class="terms-section py-5">
        <div class="container">
            <?php echo $data?->value['long_description']; ?>

        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('landing-page.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\terms.blade.php ENDPATH**/ ?>