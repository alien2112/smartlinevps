<!DOCTYPE html>
<html lang="en">
    <?php ($preloader = getSession('preloader')); ?>
    <?php ($favicon = getSession('favicon')); ?>
    <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo $__env->yieldContent('title'); ?></title>

    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/animate.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/line-awesome.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/odometer.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/owl.min.css" />
    <link rel="stylesheet" href="<?php echo e(asset('public/landing-page')); ?>/assets/css/main.css" />
        <?php echo $__env->make('landing-page.layouts.css', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <link rel="shortcut icon" href="<?php echo e($favicon ? asset("storage/app/public/business/".$favicon) : asset('public/landing-page/assets/img/favicon.png')); ?>" type="image/x-icon" />
</head>

<body>

    <div class="preloader" id="preloader">
        <?php if($preloader): ?>
            <img class="preloader-img" width="160" loading="eager"
                src="<?php echo e($preloader ? asset('storage/app/public/business/' . $preloader) : ''); ?>" alt="">
        <?php else: ?>
            <div class="spinner-grow" role="status">
                <span class="visually-hidden"><?php echo e(translate('Loading...')); ?></span>
            </div>
        <?php endif; ?>
    </div>

<?php echo $__env->make('landing-page.partials._header', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

<?php echo $__env->yieldContent('content'); ?>

<!-- Footer Section Start -->
<?php echo $__env->make('landing-page.partials._footer', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
<!-- Footer Section End -->


<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/bootstrap.min.js"></script>
<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/viewport.jquery.js"></script>
<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/wow.min.js"></script>
<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/owl.min.js"></script>
<script src="<?php echo e(asset('public/landing-page')); ?>/assets/js/main.js"></script>

<?php echo $__env->yieldPushContent('script'); ?>
</body>

</html>
<?php /**PATH D:\smartline-copy\smart-line.space\resources\views/landing-page/layouts/master.blade.php ENDPATH**/ ?>