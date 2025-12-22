<?php $__env->startSection('title', 'Parcel Tracking'); ?>

<?php $__env->startSection('content'); ?>
    <section class="py-5">
        <div class="container">
            <h4 class="text-center mb-4"><?php echo e(translate("Parcel Tracking")); ?></h4>
            <div class="parcel-tracking-wrapper">
                <div class="parcel-tracking-left">
                    <div class="product-media mb-20">
                        <div class="img">
                            <img src="<?php echo e(asset('public/assets/admin-module/img/parcel-box.png')); ?>" alt="">
                            <div class="fs-14"><?php echo e(translate("Parcel")); ?></div>
                        </div>
                        <div class="w-0 flex-grow-1">
                            <div class="d-flex flex-column align-items-start gap-1 fs-14">
                                <div><?php echo e($trip?->parcel?->parcelCategory?->name ?? "N/A"); ?></div>
                                <div class="text-dark leading-19px fw-medium">
                                    <?php echo e(translate("Tracking ID")); ?> #<?php echo e($trip->ref_id); ?>

                                </div>
                                <div class="fs-12"><?php echo e(date('d F Y, h:i a', strtotime($trip->created_at))); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="fs-14 text--base fw-semibold mb-2"><?php echo e(translate("Trip Details")); ?></div>
                    <ul class="trip-details-address mb-20">
                        <li>
                            <img width="18" src="<?php echo e(asset('public/assets/admin-module/img/svg/gps.svg')); ?>" class="svg"
                                 alt="">
                            <span class="w-0 flex-grow-1">
                                <?php echo e($trip->coordinate->pickup_address); ?>

                            </span>
                        </li>
                        <li>
                            <img width="18" src="<?php echo e(asset('public/assets/admin-module/img/svg/map-nav.svg')); ?>"
                                 class="svg" alt="">
                            <span class="w-0 flex-grow-1">
                                <?php echo e($trip->coordinate->destination_address); ?>

                            </span>
                        </li>
                    </ul>
                    <div class="fs-14 text--base fw-semibold mb-2"><?php echo e(translate("Time Line")); ?></div>
                    <div class="timeline">
                        <?php if($trip?->tripStatus?->accepted): ?>
                            <div
                                class="item <?php echo e($trip->current_status == ACCEPTED || $trip->current_status == ONGOING || $trip->current_status == COMPLETED || $trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : ""); ?>">
                                <h6 class="img">
                                    <img class="svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/confirmed.svg')); ?>"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold"><?php echo e(translate("Confirmed")); ?></h6>
                                    <?php if($trip?->tripStatus?->accepted): ?>
                                        <div>
                                            <img class="svg"
                                                 src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                 alt="">
                                            <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->accepted))); ?>

                                        </div>
                                    <?php endif; ?>
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        <?php endif; ?>
                        <?php if($trip?->tripStatus?->ongoing): ?>
                            <div
                                class="item <?php echo e($trip->current_status == ONGOING || $trip->current_status == COMPLETED || $trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : ""); ?>">
                                <h6 class="img">
                                    <img class="svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/on-the-way.svg')); ?>"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold"><?php echo e(translate("On The Way")); ?></h6>
                                    <?php if($trip?->tripStatus?->ongoing): ?>
                                        <div>
                                            <img class="svg"
                                                 src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                 alt="">
                                            <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->ongoing))); ?>

                                        </div>
                                    <?php endif; ?>
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        <?php endif; ?>
                        <?php if($trip->current_status == ACCEPTED || $trip->current_status == ONGOING || $trip->current_status == COMPLETED): ?>
                            <div class="item <?php echo e($trip->current_status == COMPLETED ? "active" : ""); ?>">
                                <h6 class="img">
                                    <img class="svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/delivery.svg')); ?>"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold"><?php echo e(translate("Parcel Delivered")); ?></h6>
                                    <?php if($trip?->tripStatus?->completed): ?>
                                        <div>
                                            <img class="svg"
                                                 src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                 alt="">
                                            <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->completed))); ?>

                                        </div>
                                    <?php endif; ?>
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        <?php endif; ?>
                        <?php if($trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED): ?>
                            <div
                                class="item <?php echo e($trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : ""); ?>">
                                <h6 class="img">
                                    <img class="svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/cancelled.svg')); ?>"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold"><?php echo e(translate("Parcel Cancelled")); ?></h6>
                                    <?php if($trip?->tripStatus?->cancelled): ?>
                                        <div>
                                            <img class="svg"
                                                 src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                 alt="">
                                            <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->cancelled))); ?>

                                        </div>
                                    <?php endif; ?>
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <?php if($trip?->tripStatus?->returning): ?>
                                <div
                                    class="item <?php echo e($trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : ""); ?>">
                                    <h6 class="img">
                                        <img class="svg"
                                             src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/returning.svg')); ?>"
                                             alt="">
                                    </h6>
                                    <div class="w-0 flex-grow-1">
                                        <h6 class="fw-semibold"><?php echo e(translate("Parcel Returning")); ?></h6>
                                        <?php if($trip?->tripStatus?->returning): ?>
                                            <div>
                                                <img class="svg"
                                                     src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                     alt="">
                                                <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->returning))); ?>

                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            <?php endif; ?>
                            <?php if($trip?->tripStatus?->returned): ?>
                                <div class="item <?php echo e($trip->current_status == RETURNED ? "active" : ""); ?>">
                                    <h6 class="img">
                                        <img class="svg"
                                             src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/returned.svg')); ?>"
                                             alt="">
                                    </h6>
                                    <div class="w-0 flex-grow-1">
                                        <h6 class="fw-semibold"><?php echo e(translate("Parcel Returned")); ?></h6>
                                        <?php if($trip?->tripStatus?->returned): ?>
                                            <div>
                                                <img class="svg"
                                                     src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg')); ?>"
                                                     alt="">
                                                <?php echo e(date('h:i a, d F Y', strtotime($trip?->tripStatus?->returned))); ?>

                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="parcel-tracking-right">
                    <div class="parcel-tracking-driver-info mb-20">
                        <div class="fs-14 text--base fw-medium mb-0"><?php echo e(translate("Driver Details")); ?></div>
                        <?php if($trip?->driver): ?>
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-dark"><?php echo e($trip?->driver?->full_name ?? "N/A"); ?></div>
                                    <small class="d-flex align-items-center gap-1"><i
                                            class="bi text--warning bi-star-fill"></i><?php echo e(round($trip?->driver?->received_reviews_avg_rating, 1)); ?>

                                    </small>
                                </div>
                                <div class="col-6">
                                    <div
                                        class="text-dark"><?php echo e($trip?->vehicleCategory?->type? ucwords($trip?->vehicleCategory?->type): "N/A"); ?>

                                        : <?php echo e($trip?->vehicle?->licence_plate_number?? "N/A"); ?></div>
                                    <small><?php echo e($trip?->vehicle?->model?->name?? "N/A"); ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="text-dark"><?php echo e(translate('Driver not available')); ?></div>
                            </div>
                        <?php endif; ?>

                    </div>
                    <div class="parcel-fare-infos text-dark mb-20">
                        <ul>
                            <li>
                                <span class="text--base d-flex gap-2 align-items-center">
                                    <img class="text--base-50 svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/receipt-minus.svg')); ?>"
                                         alt="">
                                    <span class="text-base-dark fs-16 fw-semibold"><?php echo e(translate("Total")); ?></span>
                                </span>
                                <span class="fs-16 fw-semibold total"><?php echo e(set_currency_symbol($trip->paid_fare)); ?></span>
                            </li>
                            <li class="payment-sender-text">
                                <span class="text--base d-flex gap-2 align-items-center">
                                    <img class="text--base-50 svg"
                                         src="<?php echo e(asset('/public/landing-page/assets/img/parcel-tracking/cards.svg')); ?>"
                                         alt="">
                                    <?php echo e(translate("Payment By")); ?> <?php echo e(ucwords($trip?->parcel?->payer)); ?>

                                </span>
                                <span class="text-base-dark"><?php echo e($trip->payment_method); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="parcel-tracking-driver-info">
                        <div class="row">
                            <div class="col-6">
                                <div class="fs-14 text--base fw-medium mb-0"><?php echo e(translate("Sender Details")); ?></div>
                                <div
                                    class="text-dark"><?php echo e($trip?->parcelUserInfo?->firstWhere('user_type',SENDER)?->name ?? "N/A"); ?></div>
                                <small class="d-flex align-items-center gap-1"
                                       dir="ltr"><?php echo e($trip?->parcelUserInfo?->firstWhere('user_type',SENDER)?->contact_number ?? "N/A"); ?></small>
                            </div>
                            <div class="col-6">
                                <div class="fs-14 text--base fw-medium mb-0"><?php echo e(translate("Receiver Details")); ?></div>
                                <div
                                    class="text-dark"><?php echo e($trip?->parcelUserInfo?->firstWhere('user_type',RECEIVER)?->name ?? "N/A"); ?></div>
                                <small class="d-flex align-items-center gap-1"
                                       dir="ltr"><?php echo e($trip?->parcelUserInfo?->firstWhere('user_type',RECEIVER)?->contact_number ?? "N/A"); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('landing-page.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\parcel-tracking.blade.php ENDPATH**/ ?>