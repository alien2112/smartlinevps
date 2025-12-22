<?php $__env->startSection('title', 'Fourth Step'); ?>
<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('installation.layouts.title', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!-- Progress -->
    <div class="pb-2">
        <div class="progress cursor-pointer" role="progressbar" aria-label="DriveMond Software Installation"
             aria-valuenow="80" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip"
             data-bs-placement="top" data-bs-custom-class="custom-progress-tooltip" data-bs-title="Fourth Step!"
             data-bs-delay='{"hide":1000}'>
            <div class="progress-bar w-d" style="--width: 80%"></div>
        </div>
    </div>

    <!-- Card -->
    <div class="card mt-4 position-relative">
        <div class="d-flex justify-content-end mb-2 position-absolute top-end">
            <a href="#" class="d-flex align-items-center gap-1">
                        <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                              data-bs-title="Click on the section to automatically import database">

                            <img src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/info.svg" alt=""
                                 class="svg">
                        </span>
            </a>
        </div>
        <div class="p-4 mb-md-3 mx-xl-4 px-md-5">
            <div class="d-flex align-items-center column-gap-3 flex-wrap">
                <h5 class="fw-bold fs text-uppercase"><?php echo e(translate('Step 4.')); ?> </h5>
                <h5 class="fw-normal"><?php echo e(translate('Import Database')); ?></h5>
            </div>
            <p class="mb-5">
                <?php echo e(translate('Your Database has been connected ! Just click on the section to automatically import database')); ?>

            </p>

            <?php if(session()->has('error')): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-danger">
                            <?php echo e(translate('Your database is not clean, do you want to clean database then import')); ?>?
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center">
                    <a href="<?php echo e(route('force-import-sql')); ?>" class="btn btn-danger px-sm-5 loader-show">
                        <?php echo e(translate('Force Import Database')); ?></a>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-center">
                    <a href="<?php echo e(route('import_sql',['token'=>bcrypt('step_5')])); ?>" class="btn btn-dark px-sm-5 loader-show"><?php echo e(translate('Click Here')); ?></a>
                </div>
            <?php endif; ?>


        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
    <script type="text/javascript">
        "use strict";
        $(".loader-show").on('click',function (){
            $('#loading').fadeIn();
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('installation.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\installation\step4.blade.php ENDPATH**/ ?>