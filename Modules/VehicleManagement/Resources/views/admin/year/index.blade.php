@section('title', translate('Add_Vehicle_Year'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row g-4">
            @include('vehiclemanagement::admin.partials._attribute_header')

            @can('vehicle_add')
                <div class="col-12">
                    <form action="{{ route('admin.vehicle.attribute-setup.year.store') }}"
                          enctype="multipart/form-data" method="POST">
                        @csrf
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('add_new_year') }}</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="mb-4">
                                                    <label for="year"
                                                           class="mb-2">{{ translate('year') }}</label>
                                                    <input type="number" id="year" name="year"
                                                           class="form-control" placeholder="e.g. 2023"
                                                           value="{{ old('year') }}" required min="1900" max="{{ date('Y') }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-3">
                                    <button type="submit"
                                            class="btn btn-primary">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @endcan

            <div class="col-12">
                <h2 class="fs-22 text-capitalize">{{ translate('year_list') }}</h2>

                <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                    <!--<ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">-->
                    <!--    <li class="nav-item" role="presentation">-->
                    <!--        <a class="nav-link {{!request()->has('status') || request()->get('status')=='all'?'active':''}}"-->
                    <!--           href="{{url()->current()}}?status=all">-->
                    <!--            {{ translate('all') }}-->
                    <!--        </a>-->
                    <!--    </li>-->
                    <!--    <li class="nav-item" role="presentation">-->
                    <!--        <a class="nav-link {{request()->get('status')=='active'?'active':''}}"-->
                    <!--           href="{{url()->current()}}?status=active">-->
                    <!--            {{ translate('active') }}-->
                    <!--        </a>-->
                    <!--    </li>-->
                    <!--    <li class="nav-item" role="presentation">-->
                    <!--        <a class="nav-link {{request()->get('status')=='inactive'?'active':''}}"-->
                    <!--           href="{{url()->current()}}?status=inactive">-->
                    <!--            {{ translate('inactive') }}-->
                    <!--        </a>-->
                    <!--    </li>-->
                    <!--</ul>-->

                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted text-capitalize">{{ translate('total_years') }} :</span>
                        <span class="text-primary fs-16 fw-bold" id="total_record_count">{{ $years->total() }}</span>
                    </div>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade active show" id="all-tab-pane" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                    <form action="javascript:;" class="search-form search-form_style-two" method="GET">
                                        <div class="input-group search-form__input_group">
                                            <span class="search-form__icon">
                                                <i class="bi bi-search"></i>
                                            </span>
                                            <input type="search" class="theme-input-style search-form__input"
                                                   value="{{request()->get('search')}}" name="search" id="search"
                                                   placeholder="{{translate('search_here_by_Year')}}">
                                        </div>
                                        <button type="submit"
                                                class="btn btn-primary search-submit" data-url="{{ url()->full() }}">
                                            {{ translate('search') }}
                                        </button>
                                    </form>

                                    <!--<div class="d-flex flex-wrap gap-3">-->
                                    <!--    @can('super-admin')-->
                                    <!--        <a href="{{ route('admin.vehicle.attribute-setup.year.index', ['status' => request('status')]) }}"-->
                                    <!--           class="btn btn-outline-primary px-3" data-bs-toggle="tooltip" data-bs-title="{{ translate('refresh') }}">-->
                                    <!--            <i class="bi bi-arrow-repeat"></i>-->
                                    <!--        </a>-->

                                    <!--        <a href="{{ route('admin.vehicle.attribute-setup.year.trashed') }}"-->
                                    <!--           class="btn btn-outline-primary px-3" data-bs-toggle="tooltip" data-bs-title="{{ translate('manage_Trashed_Data') }}">-->
                                    <!--            <i class="bi bi-recycle"></i>-->
                                    <!--        </a>-->
                                    <!--    @endcan-->
                                    <!--</div>-->
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-borderless align-middle">
                                        <thead class="table-light align-middle">
                                            <tr>
                                                <th>{{ translate('SL') }}</th>
                                                <th class="text-capitalize">{{ translate('year') }}</th>
                                                <!--@can('vehicle_edit')-->
                                                <!--    <th class="status">{{ translate('status') }}</th>-->
                                                <!--@endcan-->
                                                <th class="text-center action">{{ translate('action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @forelse ($years as $year)
                                            <tr id="hide-row-{{$year->id}}" class="record-row">
                                                <td>{{ $loop->index + 1 }}</td>
                                                <td class="year">{{ $year->year }}</td>
                                                <!--@can('vehicle_edit')-->
                                                <!--    <td class="status">-->
                                                <!--        <label class="switcher">-->
                                                <!--            <input class="switcher_input status-change"-->
                                                <!--                   data-url={{ route('admin.vehicle.attribute-setup.year.status') }}-->
                                                <!--                   id="{{ $year->id }}" type="checkbox" {{ $year->is_active ? 'checked' : '' }}>-->
                                                <!--            <span class="switcher_control"></span>-->
                                                <!--        </label>-->
                                                <!--    </td>-->
                                                <!--@endcan-->
                                                <td class="action">
                                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                                        <!--@can('vehicle_edit')-->
                                                        <!--    <a href="{{ route('admin.vehicle.attribute-setup.year.edit', ['id'=>$year->id]) }}"-->
                                                        <!--       class="btn btn-outline-info btn-action">-->
                                                        <!--        <i class="bi bi-pencil-fill"></i>-->
                                                        <!--    </a>-->
                                                        <!--@endcan-->

                                                        @can('vehicle_delete')
                                                            <button data-id="delete-{{ $year->id }}"
                                                                    data-message="{{ translate('want_to_delete_this_year?') }}"
                                                                    type="button"
                                                                    class="btn btn-outline-danger btn-action form-alert">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>

                                                            <form action="{{ route('admin.vehicle.attribute-setup.year.delete', ['id'=>$year->id]) }}"
                                                                  id="delete-{{ $year->id }}" method="post">
                                                                @csrf
                                                                @method('delete')
                                                            </form>
                                                        @endcan
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="14">
                                                    <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                                        <img src="{{ asset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="" width="100">
                                                        <p class="text-center">{{translate('no_data_available')}}</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end">
                                    {!! $years->links() !!}
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
@endsection
