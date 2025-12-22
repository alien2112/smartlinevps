<header>
    <?php ($logo = getSession('header_logo')); ?>
    <!-- Header Bottom -->
    <div class="navbar-bottom">
        <div class="container">
            <div class="navbar-bottom-wrapper">
                <a href="<?php echo e(route('index')); ?>" class="logo">
                    <img src="<?php echo e($logo ? asset("storage/app/public/business/".$logo) : asset('public/landing-page/assets/img/logo.png')); ?>" alt="">
                </a>
                <ul class="menu me-lg-4">
                    <li>
                        <a href="<?php echo e(route('index')); ?>" class="<?php echo e(Request::is('/')? 'active' :''); ?>"><span>Home</span></a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('privacy')); ?>" class="<?php echo e(Request::is('privacy') ? 'active' :''); ?>"><span><?php echo e(translate('Privacy Policy')); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('terms')); ?>" class="<?php echo e(Request::is('terms')? 'active' :''); ?>"><span><?php echo e(translate('Terms & Condition')); ?></span></a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('about-us')); ?>" class="<?php echo e(Request::is('about-us')? 'active' :''); ?>"><span><?php echo e(translate('About Us')); ?></span></a>
                    </li>
                    <li class="d-sm-none">
                        <a href="<?php echo e(route('contact-us')); ?>" class="cmn--btn px-4 w-unset text-white d-inline-flex <?php echo e(Request::is('contact-us')? 'active' :''); ?>"><span>Contact
                                Us</span></a>
                    </li>
                </ul>
                <div class="nav-toggle d-lg-none ms-auto me-2 me-sm-4">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <a href="<?php echo e(route('contact-us')); ?>" class="cmn--btn d-none d-sm-block <?php echo e(Request::is('contact-us')? 'active' :''); ?>"><?php echo e(translate('Contact Us')); ?></a>
            </div>
        </div>
    </div>
    <!-- Header Bottom -->
</header>
<?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\partials\_header.blade.php ENDPATH**/ ?>