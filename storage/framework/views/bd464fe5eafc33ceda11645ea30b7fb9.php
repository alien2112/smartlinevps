<?php $__env->startSection('title', 'Contact Us'); ?>


<?php $__env->startSection('content'); ?>
    <?php ($email = getSession('business_contact_email')); ?>
    <?php ($contactNumber = getSession('business_contact_phone')); ?>
    <?php ($businessAddress = getSession('business_address')); ?>
    <!-- Page Header Start -->
    <div class="container pt-3">
        <section class="page-header">
            <h3 class="title"><?php echo e(translate('Contact Us')); ?></h3>
{
        </section>
    </div>
    <!-- Page Header End -->
    <section class="contact-section pb-60 pt-30">
        <div class="container">
            <div class="text-center mb-3">
                <img src="./assets/img/contact.png" alt="">
            </div>

            <!-- Contact Information -->
            <div class="contact-info-wrapper">
                <div class="item">
                    <div class="icon">
                        <i class="las la-envelope"></i>
                    </div>
                    <div class="cont">
                        <h6 class="subtitle"><?php echo e(translate('My Email')); ?>:</h6>
                        <a href="mailto:<?php echo e($email ? $email : "contact@example.com"); ?>" class="txt"><?php echo e($email ? $email : "contact@example.com"); ?></a>
                    </div>
                </div>
                <div class="item">
                    <div class="icon">
                        <i class="las la-phone"></i>
                    </div>
                    <div class="cont">
                        <h6 class="subtitle"><?php echo e(translate('Call Me Now')); ?>:</h6>
                        <a href="tel:<?php echo e($contactNumber ? $contactNumber : "+90-327-539"); ?>" class="txt"><?php echo e($contactNumber ? $contactNumber : "+90-327-539"); ?></a>
                    </div>
                </div>
                <div class="item">
                    <div class="icon">
                        <i class="las la-map-marker-alt"></i>
                    </div>
                    <div class="cont">
                        <h6 class="subtitle"><?php echo e(translate('Address')); ?>:</h6>
                        <div class="txt"><?php echo e($businessAddress ? $businessAddress : "510 Kampong Bahru Rd Singapore 099446"); ?></div>
                    </div>
                </div>
            </div>
            <!-- Contact Information -->

        </div>
    </section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('landing-page.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\contact.blade.php ENDPATH**/ ?>