<?php $__env->startSection('title', translate('hit count')); ?>




<?php $__env->startSection('content'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">

                <div class="col-12">
                    <h2 class="fs-22 text-capitalize pb-2"><?php echo e(translate('function wise routes api hit count')); ?></h2>

                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="all-tab-pane" role="tabpanel">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">

                                        <div class="d-flex flex-wrap gap-3">

                                            <div class="dropdown">
                                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('SL')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" type="checkbox"
                                                                       checked="checked" name="sl_no">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('area_name')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="area_name" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('trip_request_volume')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="trip_request_volume" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('running_promotion')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="running_promotion" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('total_customer')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="total_customer" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('status')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="status" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                    <li class="dropdown-item py-2">
                                                        <div
                                                            class="d-flex align-items-center gap-4 justify-content-between">
                                                            <span><?php echo e(translate('action')); ?></span>
                                                            <label class="switcher">
                                                                <input class="switcher_input table-column" name="action" type="checkbox"
                                                                       checked="checked">
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="table-responsive mt-3">
                                        <table class="table table-borderless align-middle">
                                            <thead class="table-light align-middle">
                                            <tr>
                                                <th class="sl_no"><?php echo e(translate('SL')); ?></th>
                                                <th class="sl_no"><?php echo e(translate('function_name')); ?></th>
                                                <th class="text-capitalize area_name"><?php echo e(translate('count')); ?></th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php $__empty_1 = true; $__currentLoopData = $count; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $area): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

                                                <tr>
                                                    <td class="sl_no"><?php echo e($loop->index+1); ?></td>
                                                    <td class="area_name"><?php echo e($area->function_name); ?></td>
                                                    <td class="area_name"><?php echo e($area->count); ?></td>

                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                                <tr>
                                                    <td colspan="14"><p class="text-center"><?php echo e(translate('no_data_available')); ?></p></td>
                                                </tr>
                                            <?php endif; ?>

                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <?php echo $count->links(); ?>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
<?php $__env->stopSection(); ?>

<?php echo $__env->make('adminmodule::layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\intercept.blade.php ENDPATH**/ ?>