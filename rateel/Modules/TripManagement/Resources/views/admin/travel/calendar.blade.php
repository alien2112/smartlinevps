@section('title', translate('Travel_Calendar'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 text-capitalize mb-3">{{ translate('Scheduled_Travels_Calendar') }}</h2>

            <div class="card">
                <div class="card-body">
                    <div class="calendar-container">
                        {{-- Simple Grid View for Calendar --}}
                        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-3">
                            @foreach($calendarData as $date => $trips)
                                <div class="col">
                                    <div class="border rounded p-3 h-100 {{ Carbon\Carbon::parse($date)->isToday() ? 'border-primary bg-light' : '' }}">
                                        <h5 class="fw-bold mb-3 text-center border-bottom pb-2">
                                            {{ Carbon\Carbon::parse($date)->format('D, M dY') }}
                                        </h5>
                                        
                                        <div class="d-flex flex-column gap-2">
                                            @foreach($trips as $trip)
                                                <a href="{{ route('admin.travel.show', $trip->id) }}" class="text-decoration-none">
                                                    <div class="card p-2 shadow-sm border-0 border-start border-4 {{ $trip->driver_id ? 'border-success' : 'border-warning' }}">
                                                        <div class="d-flex justify-content-between">
                                                            <span class="fs-12 fw-bold text-dark">{{ Carbon\Carbon::parse($trip->scheduled_at)->format('H:i') }}</span>
                                                            <span class="badge {{ $trip->driver_id ? 'badge-soft-success' : 'badge-soft-warning' }}">
                                                                {{ $trip->driver_id ? translate('Assigned') : translate('Pending') }}
                                                            </span>
                                                        </div>
                                                        <div class="fs-12 text-muted text-truncate mt-1" title="{{ $trip->destination_address }}">
                                                            <i class="tio-map-marker"></i> {{ $trip->destination_address }}
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                                            <span class="fs-11 text-dark">
                                                                <i class="tio-user"></i> {{ $trip->seats_requested }} {{ translate('seats') }}
                                                            </span>
                                                            <span class="fs-11 fw-bold text-primary">{{ $trip->offer_price }}</span>
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if(count($calendarData) == 0)
                                <div class="col-12 text-center py-5">
                                    <img src="{{ asset('public/assets/admin-module/img/empty-state.png') }}" class="w-25 mb-3">
                                    <h5 class="text-muted">{{ translate('No_scheduled_travels_found') }}</h5>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
