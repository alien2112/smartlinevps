<?php $__env->startSection('title', 'Congratulations!'); ?>
<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('installation.layouts.title', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Card -->
    <div class="card mt-4">
        <div class="p-4 mb-md-3 mx-xl-4 px-md-5">
            <div class="p-4 rounded mb-4 text-center">
                <h5 class="fw-normal pb-5"><?php echo e(translate('Configure the following setting to run the system properly')); ?></h5>

                <ul class="list-group mar-no mar-top bord-no">
                    <li class="list-group-item"><?php echo e(translate('Business Setting')); ?></li>
                    <li class="list-group-item"><?php echo e(translate('MAIL Setting')); ?></li>
                    <li class="list-group-item"><?php echo e(translate('Payment Gateway Configuration')); ?></li>
                    <li class="list-group-item"><?php echo e(translate('SMS Gateway Configuration')); ?></li>
                    <li class="list-group-item"><?php echo e(translate('3rd Party APIs')); ?></li>
                </ul>
            </div>

            <div class="d-flex justify-content-center">
                <a href="<?php echo e(env('APP_URL')); ?>" target="_blank" class="btn btn-secondary px-sm-5 me-2"><?php echo e(translate('Landing Page')); ?></a>
                <a href="<?php echo e(env('APP_URL')); ?>/admin/auth/login" target="_blank" class="btn btn-dark px-sm-5"><?php echo e(translate('Admin Panel')); ?></a>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('installation.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\installation\step6.blade.php ENDPATH**/ ?>