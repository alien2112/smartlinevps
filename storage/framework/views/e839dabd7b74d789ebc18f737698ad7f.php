<?php $__env->startSection('title', 'First Step'); ?>
<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('installation.layouts.title', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>


    <!-- Progress -->
    <div class="pb-2">
        <div class="progress cursor-pointer" role="progressbar" aria-label="DriveMond Software Installation"
             aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" data-bs-toggle="tooltip"
             data-bs-placement="top" data-bs-custom-class="custom-progress-tooltip" data-bs-title="First Step!"
             data-bs-delay='{"hide":1000}'>
            <div class="progress-bar w-d" style="--width: 20%"></div>
        </div>
    </div>

    <!-- Card -->
    <div class="card mt-4">
        <div class="p-4 mb-md-3 mx-xl-4 px-md-5">
            <div class="d-flex justify-content-end mb-2">
            </div>

            <div class="d-flex align-items-center column-gap-3 flex-wrap mb-4">
                <h5 class="fw-bold fs text-uppercase"><?php echo e(translate('Step')); ?> 1. </h5>
                <h5 class="fw-normal"><?php echo e(translate('Check & Verify File Permissions')); ?></h5>
            </div>

            <div class="bg-light p-4 rounded mb-4">
                <h6 class="fw-bold text-uppercase fs m-0 letter-spacing  mb-4 pb-sm-3" style="--fs: 14px">
                    <?php echo e(translate('Required Database Information')); ?>

                </h6>

                <div class="px-xl-2 pb-sm-3">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="d-flex gap-3 align-items-center">
                                <img
                                    src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/php-version.svg"
                                    alt="">
                                <div
                                    class="d-flex align-items-center gap-2 justify-content-between flex-grow-1">
                                    <?php echo e(translate('PHP Version 8.1 +')); ?>


                                    <?php ($phpVersion = number_format((float)phpversion(), 2, '.', '')); ?>
                                    <?php ($phpVersionMatched = $phpVersion >= 8.1); ?>
                                    <?php if($phpVersionMatched): ?>
                                        <img width="20"
                                             src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/check.png"
                                             alt="">
                                    <?php else: ?>
                                        <span class="cursor-pointer" data-bs-toggle="tooltip"
                                              data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                              data-bs-html="true" data-bs-delay='{"hide":1000}'
                                              data-bs-title="Your php version in server is lower than 8.1 version
                                                   <a href='https://support.cpanel.net/hc/en-us/articles/360052624713-How-to-change-the-PHP-version-for-a-domain-in-cPanel-or-WHM'
                                                   class='d-block' target='_blank'>See how to update</a> ">
                                                <img
                                                    src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/info.svg"
                                                    class="svg text-danger" alt="">
                                            </span>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                        <?php $__currentLoopData = $permission; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex gap-3 align-items-center">
                                    <img
                                        src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/curl-enabled.svg"
                                        alt="">
                                    <div
                                        class="d-flex align-items-center gap-2 justify-content-between flex-grow-1">
                                        <?php echo e(translate($key) . ' ' . translate('Enabled')); ?>


                                        <?php if($item): ?>
                                            <img width="20"
                                                 src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/check.png"
                                                 alt="">
                                        <?php else: ?>
                                            <span class="cursor-pointer" data-bs-toggle="tooltip"
                                                  data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                                  data-bs-html="true" data-bs-delay='{"hide":1000}'
                                                  <?php if($key == 'curl'): ?>
                                                      data-bs-title="Curl extension is not enabled in your server. To enable go to PHP version > extensions and select curl."
                                            <?php elseif($key == 'module_file_permission'): ?>
                                                      data-bs-title="Module json file permission is not provide, Please give permission modules_statuses.json file."
                                                  <?php elseif($key == 'env_file_write_perm'): ?>
                                                      data-bs-title="ENV file permission is not provide, Please give permission .env file."
                                                  <?php elseif($key == 'routes_file_write_perm'): ?>
                                                      data-bs-title="Route service provider file permission is not provide, Please give permission app/Providers/RouteServiceProvider.php file."
                                            <?php else: ?>
                                                      data-bs-title="<?php echo e($key); ?> extension is not enabled in your server. To enable go to PHP version > extensions and select curl."

                                                <?php endif; ?>
                                            >
                                                <img
                                                    src="<?php echo e(asset('public/assets/installation')); ?>/assets/img/svg-icons/info.svg"
                                                    class="svg text-danger" alt="">
                                            </span>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>

                <div class="text-center">
                    <p><?php echo e(translate('All the permissions are provided successfully')); ?>?</p>

                    <?php if(array_product($permission) && $phpVersionMatched): ?>
                        <div class="d-flex justify-content-center">
                            <a href="<?php echo e(route('step2',['token'=>bcrypt('step_2')])); ?>"
                               class="btn btn-dark px-sm-5"><?php echo e(translate('Proceed to Next')); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('installation.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\installation\step1.blade.php ENDPATH**/ ?>