<?php $__env->startSection('title', translate('add_new_notification')); ?>

<?php $__env->startPush('css_or_js'); ?>

<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <div class="content container-fluid">
        <!-- Page Title -->
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="<?php echo e(asset('/public/assets/back-end/img/push_notification.png')); ?>" alt="">
                <?php echo e(translate('send_notification')); ?>

            </h2>
        </div>
        <!-- End Page Title -->

        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-body">
                        <form action="<?php echo e(route('admin.store')); ?>" method="post"
                              style="text-align: <?php echo e(Session::get('direction') === "rtl" ? 'right' : 'left'); ?>;"
                              enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="title-color text-capitalize"
                                               for="exampleFormControlInput1"><?php echo e(translate('title')); ?> </label>
                                        <input type="text" name="title" class="form-control"
                                               placeholder="<?php echo e(translate('new_notification')); ?>"
                                               required>
                                    </div>
                                    <div class="form-group">
                                        <label class="title-color text-capitalize"
                                               for="exampleFormControlInput1"><?php echo e(translate('description')); ?> </label>
                                        <textarea name="description" class="form-control" required></textarea>
                                    </div>
                                   
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <center>
                                            <img class="upload-img-view mb-4" id="viewer"
                                                 onerror="this.src='<?php echo e(asset('public/assets/front-end/img/image-place-holder.png')); ?>'"
                                                 src="<?php echo e(asset('public/assets/admin/img/900x400/img1.jpg')); ?>"
                                                 alt="image"/>
                                        </center>
                                        <label
                                            class="title-color text-capitalize"><?php echo e(translate('image')); ?> </label>
                                        <span class="text-info">(<?php echo e(translate('ratio')); ?> 1:1)</span>
                                        <div class="custom-file text-left">
                                            <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                                   accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                                            <label class="custom-file-label"
                                                   for="customFileEg1"><?php echo e(translate('choose_File')); ?></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-3">
                                <button type="reset" class="btn btn-secondary"><?php echo e(translate('reset')); ?> </button>
                                <button type="submit" class="btn btn--primary"><?php echo e(translate('send_Notification')); ?>  </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="px-3 py-4">
                        <div class="row align-items-center">
                            <div class="col-sm-4 col-md-6 col-lg-8 mb-2 mb-sm-0">
                                <h5 class="mb-0 text-capitalize d-flex align-items-center gap-2">
                                    <?php echo e(translate('push_notification_table')); ?>

                                    <span
                                        class="badge badge-soft-dark radius-50 fz-12 ml-1"><?php echo e($notifications->total()); ?></span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="<?php echo e(url()->current()); ?>" method="GET">
                                    <div class="input-group input-group-merge input-group-custom">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <i class="tio-search"></i>
                                            </div>
                                        </div>
                                        <input id="datatableSearch_" type="search" name="search" class="form-control"
                                               placeholder="<?php echo e(translate('search_by_title')); ?>"
                                               aria-label="Search orders" value="<?php echo e($search); ?>" required>
                                        <button type="submit"
                                                class="btn btn--primary"><?php echo e(translate('search')); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive datatable-custom">
                        <table style="text-align: <?php echo e(Session::get('direction') === "rtl" ? 'right' : 'left'); ?>;"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                            <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th><?php echo e(translate('SL')); ?> </th>
                                <th><?php echo e(translate('title')); ?> </th>
                                <th><?php echo e(translate('description')); ?> </th>
                                <th><?php echo e(translate('image')); ?> </th>
                                <th><?php echo e(translate('notification_count')); ?> </th>
                                <th><?php echo e(translate('status')); ?> </th>
                                <th><?php echo e(translate('resend')); ?> </th>
                                <th class="text-center"><?php echo e(translate('action')); ?> </th>
                            </tr>

                            </thead>

                            <tbody>
                            <?php $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e($notifications->firstItem()+ $key); ?></td>
                                    <td>
                                        <span class="d-block">
                                            <?php echo e(\Illuminate\Support\Str::limit($notification['title'],30)); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <?php echo e(\Illuminate\Support\Str::limit($notification['description'],40)); ?>

                                    </td>
                                    <td>
                                        <img class="min-w-75" width="75" height="75"
                                             onerror="this.src='<?php echo e(asset('public/assets/back-end/img/160x160/img2.jpg')); ?>'"
                                             src="<?php echo e(asset('storage/app/public/notification')); ?>/<?php echo e($notification['image']); ?>">
                                    </td>
                                    <td id="count-<?php echo e($notification->id); ?>"><?php echo e($notification['notification_count']); ?></td>
                                    <td>
                                        <form action="<?php echo e(route('admin.status')); ?>" method="post" id="notification_status<?php echo e($notification['id']); ?>_form" class="notification_status_form">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo e($notification['id']); ?>">
                                            <label class="switcher mx-auto">
                                                <input type="checkbox" class="switcher_input" id="notification_status<?php echo e($notification['id']); ?>" name="status" value="1" <?php echo e($notification['status'] == 1 ? 'checked':''); ?> onclick="toogleStatusModal(event,'notification_status<?php echo e($notification['id']); ?>','notification-on.png','notification-off.png','<?php echo e(translate('Want_to_Turn_ON_Notification_Status')); ?>','<?php echo e(translate('Want_to_Turn_OFF_Notification_Status')); ?>',`<p><?php echo e(translate('if_enabled_customers_will_receive_notifications_on_their_devices')); ?></p>`,`<p><?php echo e(translate('if_disabled_customers_will_not_receive_notifications_on_their_devices')); ?></p>`)">
                                                <span class="switcher_control"></span>
                                            </label>
                                        </form>
                                    </td>
                                    <td>
                                        <a href="javascript:void(0)" class="btn btn-outline-success square-btn btn-sm"
                                           onclick="resendNotification(this)" data-id="<?php echo e($notification->id); ?>">
                                            <i class="tio-refresh"></i>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a class="btn btn-outline--primary btn-sm edit square-btn"
                                               title="<?php echo e(translate('edit')); ?>"
                                               href="<?php echo e(route('admin.edit',[$notification['id']])); ?>">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn btn-outline-danger btn-sm delete"
                                               title="<?php echo e(translate('delete')); ?>"
                                               href="javascript:"
                                               id="<?php echo e($notification['id']); ?>')">
                                                <i class="tio-delete"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>

                        <table class="mt-4">
                            <tfoot>
                            <?php echo $notifications->links(); ?>

                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <!-- End Table -->
        </div>
    </div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('script_2'); ?>
    <script>
        $('.notification_status_form').on('submit', function(event){
            event.preventDefault();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                success: function (data) {
                    toastr.success("<?php echo e(translate('status_updated_successfully')); ?>");
                }
            });
        });

        $(document).on('click', '.delete', function () {
            var id = $(this).attr("id");
            Swal.fire({
                title: '<?php echo e(translate("are_you_sure_delete_this")); ?> ?',
                text: "<?php echo e(translate('you_will_not_be able_to_revert_this')); ?>!",
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '<?php echo e(translate("yes_delete_it")); ?>!',
                cancelButtonText: '<?php echo e(translate("cancel")); ?>',
                type: 'warning',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                        }
                    });
                    $.ajax({
                        url: "<?php echo e(route('admin.delete')); ?>",
                        method: 'POST',
                        data: {id: id},
                        success: function () {
                            toastr.success('<?php echo e(translate("notification_deleted_successfully")); ?>');
                            location.reload();
                        }
                    });
                }
            })
        });
    </script>

    <script>
        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this);
        });

        function resendNotification(t) {
            let id = $(t).data('id');

            Swal.fire({
                title: '<?php echo e(translate("are_you_sure")); ?>?',
                text: '<?php echo e(translate("resend_notification")); ?>',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#161853',
                cancelButtonText: '<?php echo e(translate("no")); ?>',
                confirmButtonText: '<?php echo e(translate("yes")); ?>',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: '<?php echo e(route("admin.resend-notification")); ?>',
                        type: 'POST',
                        data: {
                            _token: '<?php echo e(csrf_token()); ?>',
                            id: id
                        },
                        beforeSend: function () {
                            $('#loading').fadeIn();
                        },
                        success: function (res) {
                            let toasterMessage = res.success ? toastr.success : toastr.info;

                            toasterMessage(res.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                            $('#count-' + id).text(parseInt($('#count-' + id).text()) + 1);
                        },
                        complete: function () {
                            $('#loading').fadeOut();
                        }
                    });
                }
            })
        }
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('adminmodule::layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH D:\smartline-copy\smart-line.space\resources\views\notification\index.blade.php ENDPATH**/ ?>