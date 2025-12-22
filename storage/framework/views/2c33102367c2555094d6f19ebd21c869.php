<div class="inline-page-menu">
    <ul class="list-unstyled">
        <li class="<?php echo e(Request::is('admin/notification/push') ?'active':''); ?>">
            <a href="<?php echo e(route('admin.notification.push')); ?>">
                <i class="tio-notifications-on-outlined"></i>
                <?php echo e(translate('Push_Notification')); ?>

            </a>
        </li>
        <li class="<?php echo e(Request::is('admin/business-settings/fcm-index') ?'active':''); ?>">
            <a href="<?php echo e(route('admin.business-settings.fcm-index')); ?>">
                <i class="tio-cloud-outlined"></i>
                <?php echo e(translate('Firebase Configuration')); ?>

            </a>
        </li>
    </ul>
</div>
<?php /**PATH D:\smartline-copy\smart-line.space\resources\views\notification\notification-inline-menu.blade.php ENDPATH**/ ?>