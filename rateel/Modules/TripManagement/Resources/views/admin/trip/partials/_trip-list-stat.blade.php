<div class="d-flex flex-wrap gap-2 justify-content-center">
    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car1.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2 text-capitalize">{{translate(PENDING)}}.</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', PENDING)?->total_records + 0}}</h3>
        </div>
    </div>

    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car2.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2 text-capitalize">{{translate(ACCEPTED)}}.</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', ACCEPTED)?->total_records + 0}}</h3>
        </div>
    </div>

    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car3.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2">{{translate(ONGOING)}}</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', ONGOING)?->total_records + 0}}</h3>
        </div>
    </div>

    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car4.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2">{{translate(COMPLETED)}}</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', COMPLETED)?->total_records + 0}}</h3>
        </div>
    </div>

    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car5.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2">{{translate(CANCELLED)}}</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', CANCELLED)?->total_records + 0}}</h3>
        </div>
    </div>
    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car3.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2">{{translate(RETURNING)}}</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', RETURNING)?->total_records + 0}}</h3>
        </div>
    </div>
    <div class="card border analytical_data flex-grow-1">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <img width="40" src="{{asset('public/assets/admin-module/img/media/car4.png')}}" class="dark-support" alt="">
            </div>
            <h6 class="text-primary mb-2">{{translate(RETURNED)}}</h6>
            <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', RETURNED)?->total_records + 0}}</h3>
        </div>
    </div>
</div>
