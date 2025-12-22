<footer>
    <?php ($logo = getSession('header_logo')); ?>
    <?php ($footerLogo = getSession('footer_logo')); ?>
    <?php ($email = getSession('business_contact_email')); ?>
    <?php ($contactNumber = getSession('business_contact_phone')); ?>
    <?php ($businessAddress = getSession('business_address')); ?>
    <?php ($businessName = getSession('business_name')); ?>
    <?php ($cta = getSession('cta')); ?>
    <?php ($links = \Modules\BusinessManagement\Entities\SocialLink::where(['is_active'=>1])->orderBy('name','asc')->get()); ?>
    <div class="footer-top">
        <div class="container">
            <div class="footer__wrapper">
                <div class="footer__wrapper-widget">
                    <div class="cont">
                        <a href="<?php echo e(route('index')); ?>" class="logo">
                            <img src="<?php echo e($footerLogo ? asset("storage/app/public/business/".$footerLogo) : asset('public/landing-page/assets/img/logo.png')); ?>" alt="logo">
                        </a>
                        <p>
                            <?php echo e(translate('Connect with our social media and other sites to keep up to date')); ?>

                        </p>
                        <ul class="social-icons">
                            <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php if($link->name == "facebook"): ?>
                                <li>
                                    <a href="<?php echo e($link->link); ?>" target="_blank">
                                        <img src="<?php echo e(asset('public/landing-page/assets/img/footer/facebook.png')); ?>" alt="img">
                                    </a>
                                </li>
                                <?php elseif($link->name == "instagram"): ?>
                                <li>
                                    <a href="<?php echo e($link->link); ?>"  target="_blank">
                                        <img src="<?php echo e(asset('public/landing-page/assets/img/footer/instagram.png')); ?>" alt="img">
                                    </a>
                                </li>
                                <?php elseif($link->name == "twitter"): ?>
                                <li>
                                    <a href="<?php echo e($link->link); ?>"  target="_blank">
                                        <img src="<?php echo e(asset('public/landing-page/assets/img/footer/twitter.png')); ?>" alt="img">
                                    </a>
                                </li>
                                <?php elseif($link->name == "linkedin"): ?>
                                <li>
                                    <a href="<?php echo e($link->link); ?>"  target="_blank">
                                        <img src="<?php echo e(asset('public/landing-page/assets/img/footer/linkedin.png')); ?>" alt="img">
                                    </a>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        </ul>
                        <div class="app-btns">
                            <div class="me-xl-4">
                                <h6 class="text-white mb-3 font-regular">User App</h6>
                                <div class="d-flex gap-3 flex-column">
                                    <a target="_blank"  type="button" href="<?php echo e($cta && $cta['app_store']['user_download_link'] ? $cta['app_store']['user_download_link'] : ""); ?>">
                                        <img src="<?php echo e(asset('public/landing-page')); ?>/assets/img/app-store.png"
                                             class="w-115px" alt="">
                                    </a>
                                    <a target="_blank" type="button" href="<?php echo e($cta && $cta['play_store']['user_download_link'] ? $cta['play_store']['user_download_link'] : ""); ?>">
                                        <img src="<?php echo e(asset('public/landing-page')); ?>/assets/img/play-store.png"
                                             class="w-115px" alt="">
                                    </a>
                                </div>
                            </div>
                            <div>
                                <h6 class="text-white mb-3 font-regular">Driver App</h6>
                                <div class="d-flex gap-3 flex-column">
                                    <a target="_blank" type="button" href="<?php echo e($cta && $cta['app_store']['driver_download_link'] ? $cta['app_store']['driver_download_link'] : ""); ?>">
                                        <img src="<?php echo e(asset('public/landing-page')); ?>/assets/img/app-store.png"
                                             class="w-115px" alt="">
                                    </a>
                                    <a target="_blank" type="button" href="<?php echo e($cta && $cta['play_store']['driver_download_link'] ? $cta['play_store']['driver_download_link'] : ""); ?>">
                                        <img src="<?php echo e(asset('public/landing-page')); ?>/assets/img/play-store.png"
                                             class="w-115px" alt="">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <ul class="footer__wrapper-link">
                        <li>
                            <a href="<?php echo e(route('index')); ?>"><?php echo e(translate('Home')); ?></a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('about-us')); ?>"><?php echo e(translate('About Us')); ?></a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('contact-us')); ?>"><?php echo e(translate('Contact Us')); ?></a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('privacy')); ?>"><?php echo e(translate('Privacy Policy')); ?></a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('terms')); ?>"><?php echo e(translate('Terms & Condition')); ?></a>
                        </li>
                    </ul>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon" src="<?php echo e(asset('public/landing-page')); ?>/assets/img/footer/mail.png" alt="footer">
                        <h6>
                            <?php echo e(translate('Send us Mail')); ?>

                        </h6>
                        <a href="Mailto:<?php echo e($email ? $email : "contact@example.com"); ?>"><?php echo e($email ? $email : "contact@example.com"); ?></a>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon" src="<?php echo e(asset('public/landing-page')); ?>/assets/img/footer/tel.png" alt="footer">
                        <h6>
                            <?php echo e(translate('Contact Us')); ?>

                        </h6>
                        <div>
                            <a href="Tel:<?php echo e($contactNumber ? $contactNumber : "+90-327-539"); ?>"><?php echo e($contactNumber ? $contactNumber : "+90-327-539"); ?></a>
                        </div>
                        <a href="Mailto:support@example.com"><?php echo e($email ? $email : "support@6amtech.com"); ?></a>
                    </div>
                </div>
                <div class="footer__wrapper-widget">
                    <div class="footer__wrapper-contact">
                        <img class="icon" src="<?php echo e(asset('public/landing-page')); ?>/assets/img/footer/pin.png" alt="footer">
                        <h6>
                            <?php echo e(translate('Send us Mail')); ?>

                        </h6>
                        <div>
                            <?php echo e($businessAddress ? $businessAddress : "510 Kampong Bahru Rd Singapore 099446"); ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom text-center py-3">
        <?php echo e(getSession('copyright_text')); ?>

    </div>
</footer>
<?php /**PATH D:\smartline-copy\smart-line.space\resources\views\landing-page\partials\_footer.blade.php ENDPATH**/ ?>