@extends('adminmodule::layouts.master')

@section('title', translate('add_new_notification'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Title -->
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img width="20" src="{{asset('/public/assets/back-end/img/push_notification.png')}}" alt="">
                {{translate('send_notification')}}
            </h2>
        </div>
        <!-- End Page Title -->

        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.store')}}" method="post"
                              style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};"
                              enctype="multipart/form-data">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group mb-4">
                                        <label class="title-color text-capitalize"
                                               for="exampleFormControlInput1">{{translate('title')}} </label>
                                        <input type="text" name="title" class="form-control"
                                               placeholder="{{translate('new_notification')}}"
                                               required>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="title-color text-capitalize"
                                               for="exampleFormControlInput1">{{translate('description')}} </label>
                                        <textarea name="description" class="form-control" required></textarea>
                                    </div>
                                    <div class="form-group mb-4">
                                        <label class="title-color text-capitalize" for="send_to">{{ translate('sent_to') }}</label>
                                        <select name="sent_to" id="send_to" class="form-control" required>
                                            <option value="" disabled selected>{{ translate('choose') }}</option>
                                            <option value="customer">{{ translate('customer') }}</option>
                                            <option value="captin">{{ translate('captin') }}</option>
                                        </select>
                                    </div>
                                   
                                </div>
                                <!--<div class="col-md-6">-->
                                <!--    <div class="form-group">-->
                                <!--        <center>-->
                                <!--            <img class="upload-img-view mb-4" id="viewer"-->
                                <!--                 onerror="this.src='{{asset('public/assets/front-end/img/image-place-holder.png')}}'"-->
                                <!--                 src="{{asset('public/assets/admin/img/900x400/img1.jpg')}}"-->
                                <!--                 alt="image"/>-->
                                <!--        </center>-->
                                <!--        <label-->
                                <!--            class="title-color text-capitalize">{{translate('image')}} </label>-->
                                <!--        <span class="text-info">({{translate('ratio')}} 1:1)</span>-->
                                <!--        <div class="custom-file text-left">-->
                                <!--            <input type="file" name="image" id="customFileEg1" class="custom-file-input"-->
                                <!--                   accept=".jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">-->
                                <!--            <label class="custom-file-label"-->
                                <!--                   for="customFileEg1">{{translate('choose_File')}}</label>-->
                                <!--        </div>-->
                                <!--    </div>-->
                                <!--</div>-->
                                <div class="col-lg-4">
                                    <div class="d-flex flex-column justify-content-around gap-3">
                                        <h5 class="text-center">{{ translate('Image') }}</h5>
                                
                                        <div class="d-flex justify-content-center">
                                            <div class="upload-file auto profile-image-upload-file">
                                                <input type="file" name="image" class="upload-file__input"
                                                       accept=".jpg, .jpeg, .png, .webp, .gif, .bmp, .tif, .tiff" required>
                                                <div class="upload-file__img border-gray d-flex justify-content-center align-items-center w-300 h-300 p-0">
                                                    <div class="upload-file__textbox text-center">
                                                        <img width="250" height="250"
                                                             src="{{ asset('public/assets/admin-module/img/document-upload.png') }}" alt="" class="svg">
                                                        <h6 class="mt-2 fw-semibold">
                                                            <span class="text-info">{{ translate('Click to upload') }}</span><br>
                                                            {{ translate('or drag and drop') }}
                                                        </h6>
                                                    </div>
                                                    <img class="upload-file__img__img h-100" width="300" height="300" loading="lazy" alt="">
                                                </div>
                                                <a href="javascript:void(0)" class="remove-img-icon d-none"><i class="tio-clear"></i></a>
                                            </div>
                                        </div>
                                
                                        <p class="opacity-75 mx-auto max-w220">{{ translate('JPG, JPEG, PNG, WEBP. Less Than 1MB') }}</p>
                                        <span class="text-info">({{ translate('ratio') }} 1:1)</span>
                                    </div>
                                </div>

                            </div>
                            <div class="d-flex justify-content-end gap-3">
                                <button type="reset" class="btn btn-secondary">{{translate('reset')}} </button>
                                <button type="submit" class="btn btn--primary">{{translate('send_Notification')}}  </button>
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
                                    {{ translate('push_notification_table')}}
                                    <span
                                        class="radius-50 fz-12 ml-1">{{ $notifications->total() }}</span>
                                </h5>
                            </div>
                            <div class="col-sm-8 col-md-6 col-lg-4">
                                <form action="{{ url()->current() }}" method="GET">
                                    <div class="input-group input-group-merge input-group-custom">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <i class="tio-search"></i>
                                            </div>
                                        </div>
                                        <input id="datatableSearch_" type="search" name="search" class="form-control"
                                               placeholder="{{translate('search_by_title')}}"
                                               aria-label="Search orders" value="{{ $search }}" required>
                                        <button type="submit"
                                                class="btn btn--primary">{{translate('search')}}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive datatable-custom">
                        <table style="text-align: {{Session::get('direction') === "rtl" ? 'right' : 'left'}};"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                            <thead class="thead-light thead-50 text-capitalize">
                            <tr>
                                <th>{{translate('SL')}} </th>
                                <th>{{translate('title')}} </th>
                                <th>{{translate('description')}} </th>
                                <th>{{translate('sent_to')}} </th>
                                <th>{{translate('image')}} </th>
                                <th>{{translate('notification_count')}} </th>
                                <th>{{translate('status')}} </th>
                                <th>{{translate('resend')}} </th>
                                <th class="text-center">{{translate('action')}} </th>
                            </tr>

                            </thead>

                            <tbody>
                            @foreach($notifications as $key=>$notification)
                                <tr>
                                    <td>{{$notifications->firstItem()+ $key}}</td>
                                    <td>
                                        <span class="d-block">
                                            {{\Illuminate\Support\Str::limit($notification['title'],30)}}
                                        </span>
                                    </td>
                                    <td>
                                        {{\Illuminate\Support\Str::limit($notification['description'],40)}}
                                    </td>
                                    <td>
                                        {{\Illuminate\Support\Str::limit($notification['sent_to'],40)}}
                                    </td>
                                    <td>
                                        <img class="min-w-75" width="75" height="75"
                                             onerror="this.src='{{asset('public/assets/back-end/img/160x160/img2.jpg')}}'"
                                             src="{{asset('storage/app/public/notification')}}/{{$notification['image']}}">
                                    </td>
                                    <td id="count-{{$notification->id}}">{{ $notification['notification_count'] }}</td>
                                    <!--<td>-->
                                    <!--    <form action="{{route('admin.status')}}" method="post" id="notification_status{{$notification['id']}}_form" class="notification_status_form">-->
                                    <!--        @csrf-->
                                    <!--        <input type="hidden" name="id" value="{{$notification['id']}}">-->
                                    <!--        <label class="switcher mx-auto">-->
                                    <!--            <input type="checkbox" class="switcher_input" id="notification_status{{$notification['id']}}" name="status" value="1" {{ $notification['status'] == 1 ? 'checked':'' }} onclick="toogleStatusModal(event,'notification_status{{$notification['id']}}','notification-on.png','notification-off.png','{{translate('Want_to_Turn_ON_Notification_Status')}}','{{translate('Want_to_Turn_OFF_Notification_Status')}}',`<p>{{translate('if_enabled_customers_will_receive_notifications_on_their_devices')}}</p>`,`<p>{{translate('if_disabled_customers_will_not_receive_notifications_on_their_devices')}}</p>`)">-->
                                    <!--            <span class="switcher_control"></span>-->
                                    <!--        </label>-->
                                    <!--    </form>-->
                                    <!--</td>-->
                                    <td>
                                        <form action="{{ route('admin.status') }}" method="POST" id="notification_status{{$notification['id']}}_form">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $notification['id'] }}">
                                            <input type="hidden" name="status" value="0"> {{-- هذا سيُرسل في حالة إلغاء التفعيل --}}
                                            
                                            <label class="switcher mx-auto">
                                                <input type="checkbox"
                                                       class="switcher_input"
                                                       name="status"
                                                       value="1"
                                                       {{ $notification['status'] == 1 ? 'checked' : '' }}
                                                       onchange="this.form.submit()"> {{-- هذا سيُرسل الطلب تلقائياً عند التغيير --}}
                                                <span class="switcher_control"></span>
                                            </label>
                                        </form>
                                    </td>

                                    <!--<td>-->
                                    <!--    <a href="javascript:void(0)" class="btn btn-outline-success square-btn btn-sm"-->
                                    <!--       onclick="resendNotification(this)" data-id="{{ $notification->id }}">-->
                                    <!--        <i class="tio-refresh"></i>-->
                                    <!--    </a>-->
                                    <!--</td>-->
                                    <td>
                                        <button data-id="resend-{{ $notification['id'] }}"
                                                data-message="{{ translate('want_to_resend_this_notification?') }}"
                                                data-type="resend"
                                                type="button"
                                                class="btn btn-outline-success btn-sm form-alert">
                                            <i class="tio-refresh"></i>
                                        </button>
                                    
                                        <!-- فورم إعادة الإرسال -->
                                        <form action="{{ route('admin.resend-notification') }}" method="POST" id="resend-{{ $notification['id'] }}" style="display: none;">
                                            @csrf
                                            <input type="hidden" name="id" value="{{ $notification['id'] }}">
                                        </form>
                                    </td>

                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            <a class="btn btn-outline--primary btn-sm edit square-btn"
                                               title="{{translate('edit')}}"
                                               href="{{route('admin.edit',[$notification['id']])}}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <!--<a class="btn btn-outline-danger btn-sm delete"-->
                                            <!--   title="{{translate('delete')}}"-->
                                            <!--   href="javascript:"-->
                                            <!--   id="{{$notification['id']}}')">-->
                                            <!--    <i class="tio-delete"></i>-->
                                            <!--</a>-->
                                            <!--<form action="{{ route('admin.delete') }}" method="POST" style="display: inline;">-->
                                            <!--    @csrf-->
                                            <!--    <input type="hidden" name="id" value="{{ $notification['id'] }}">-->
                                            <!--    <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ translate('delete') }}">-->
                                            <!--        <i class="tio-delete"></i>-->
                                            <!--    </button>-->
                                            <!--</form>-->
                                            <button data-id="delete-{{ $notification['id'] }}"
                                                    data-message="{{ translate('want_to_delete_this_notification?') }}"
                                                    data-type="notification"
                                                    type="button"
                                                    class="btn btn-outline-danger btn-sm form-alert">
                                                <i class="tio-delete"></i>
                                            </button>
                                            
                                            <!-- فورم الحذف -->
                                            <form action="{{ route('admin.delete') }}" method="POST" id="delete-{{ $notification['id'] }}" style="display: none;">
                                                @csrf
                                                <input type="hidden" name="id" value="{{ $notification['id'] }}">
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <table class="mt-4">
                            <tfoot>
                            {!! $notifications->links() !!}
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <!-- End Table -->
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        document.querySelectorAll('.form-alert').forEach(function(button) {
    button.addEventListener('click', function () {
        const formId = this.getAttribute('data-id');
        const message = this.getAttribute('data-message') || 'Are you sure?';
        if (confirm(message)) {
            document.getElementById(formId).submit();
        }
    });
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
                title: '{{translate("are_you_sure")}}?',
                text: '{{translate("resend_notification")}}',
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#161853',
                cancelButtonText: '{{translate("no")}}',
                confirmButtonText: '{{translate("yes")}}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        url: '{{ route("admin.resend-notification") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
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
@endpush
