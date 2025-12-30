@section('title', translate('Travel_Analytics'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 text-capitalize mb-3">{{ translate('Travel_Price_Analytics') }}</h2>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h3 class="text-white">{{ number_format($avgPricePerKm, 2) }}</h3>
                            <p class="mb-0">{{ translate('Avg_User_Offer_/_Km') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3 class="text-white">{{ $trips->sum('seats_requested') }}</h3>
                            <p class="mb-0">{{ translate('Total_Seats_Booked') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Offer_Price_Distribution') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Trip_ID') }}</th>
                                    <th>{{ translate('Distance_(Km)') }}</th>
                                    <th>{{ translate('Seats') }}</th>
                                    <th>{{ translate('Offer_Price') }}</th>
                                    <th>{{ translate('Price_/_Km') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trips as $trip)
                                    <tr>
                                        <td>#{{ $trip->id }}</td>
                                        <td>{{ $trip->estimated_distance }}</td>
                                        <td>{{ $trip->seats_requested }}</td>
                                        <td>{{ $trip->offer_price }}</td>
                                        <td>
                                            @if($trip->estimated_distance > 0)
                                                {{ number_format($trip->offer_price / $trip->estimated_distance, 2) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td><span class="badge badge-soft-primary">{{ $trip->current_status ?? 'pending' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
