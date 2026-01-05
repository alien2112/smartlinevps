<div class="tab-pane fade active show" role="tabpanel">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="d-flex align-items-center gap-2 text-primary text-capitalize">
                        <i class="bi bi-person-fill-gear"></i>
                        {{translate('driver_details')}}
                    </h5>

                    <div class=" my-4">
                        <ul class="nav nav--tabs justify-content-around bg-white" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#trip-tab-pane"
                                        aria-selected="true"
                                        role="tab">{{translate('trip')}}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link text-capitalize" data-bs-toggle="tab"
                                        data-bs-target="#duty_review-tab-pane" aria-selected="false"
                                        role="tab" tabindex="-1">{{translate('duty_&_review')}}</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#wallet-tab-pane"
                                        aria-selected="false" role="tab"
                                        tabindex="-1">{{translate('wallet')}}</button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="trip-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{translate('total_completed_trip')}}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{$commonData['completed_trips']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{translate('total_cancel_trip')}}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{$commonData['cancelled_trips']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{translate('lowest_price_trip')}}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{getCurrencyFormat($otherData['driver_lowest_fare'])}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{translate('highest_price_trip')}}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{getCurrencyFormat($otherData['driver_highest_fare'])}}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="duty_review-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{ translate('Total Review Given') }}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{$commonData['driver']->givenReviews()->count()}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{ translate('Total Active Hour') }}</div>
                                        <span class="badge badge-primary fs-14">
                                            {{ $otherData['total_active_hours'] }}h
                                        </span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="wallet-tab-pane" role="tabpanel">
                            <ul class="list-unstyled d-flex flex-column gap-3 text-dark mb-0">
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{ translate('Total Level Point') }} <span class="text-muted">( {{$commonData['driver']?->level?->name}} - {{$otherData['targeted_review_point'] + $otherData['targeted_cancel_point'] + $otherData['targeted_amount_point'] + $otherData['targeted_ride_point']}}/{{$otherData['driver_level_point_goal'] ?? 0}} )</span>
                                        </div>
                                        <span
                                            class="badge badge-primary fs-14">{{$otherData['targeted_review_point'] + $otherData['targeted_cancel_point'] + $otherData['targeted_amount_point'] + $otherData['targeted_ride_point']}}</span>
                                    </div>
                                </li>
                                <li>
                                    <div class="d-flex gap-3 justify-content-between">
                                        <div class="text-capitalize">{{translate('Wallet Money')}}</div>
                                        <span
                                            class="badge badge-primary fs-14">{{getCurrencyFormat($commonData['driver']->userAccount()->value('wallet_balance')??0)}}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="text-primary mb-3 d-flex align-items-center gap-2"><i class="bi bi-paperclip"></i>
                        {{ translate('Attached Documents') }}
                    </h5>
                    @php
                        $driver = $commonData['driver'];
                        $has_docs = false;
                    @endphp

                    {{-- Identification Images --}}
                    @if(!empty($driver->identification_image) && is_array($driver->identification_image))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('identity_images') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @foreach ($driver->identification_image as $doc)
                                <div class="mb-2">
                                    <a href="{{ getMediaUrl($doc, 'driver/identity') }}"
                                       download="{{ basename($doc) }}"
                                       target="_blank"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ basename($doc) }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Driving License --}}
                    @if(!empty($driver->driving_license) && is_array($driver->driving_license))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('driving_license') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @foreach ($driver->driving_license as $doc)
                                <div class="mb-2">
                                    <a href="{{ getMediaUrl($doc, 'driver/license') }}"
                                       download="{{ basename($doc) }}"
                                       target="_blank"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ basename($doc) }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Vehicle License --}}
                    @if(!empty($driver->vehicle_license) && is_array($driver->vehicle_license))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('vehicle_license') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @foreach ($driver->vehicle_license as $doc)
                                <div class="mb-2">
                                    <a href="{{ getMediaUrl($doc, 'driver/vehicle') }}"
                                       download="{{ basename($doc) }}"
                                       target="_blank"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ basename($doc) }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Criminal Record --}}
                    @if(!empty($driver->criminal_record) && is_array($driver->criminal_record))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('criminal_record') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @foreach ($driver->criminal_record as $doc)
                                <div class="mb-2">
                                    <a href="{{ getMediaUrl($doc, 'driver/record') }}"
                                       download="{{ basename($doc) }}"
                                       target="_blank"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ basename($doc) }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Car Images --}}
                    @if((!empty($driver->car_front_image) && is_array($driver->car_front_image)) || (!empty($driver->car_back_image) && is_array($driver->car_back_image)))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('car_images') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @if(!empty($driver->car_front_image) && is_array($driver->car_front_image))
                                @foreach ($driver->car_front_image as $doc)
                                    <div class="mb-2">
                                        <a href="{{ getMediaUrl($doc, 'driver/car') }}"
                                           download="{{ basename($doc) }}"
                                           target="_blank"
                                           class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <img class="w-30px aspect-1"
                                                     src="{{ getExtensionIcon($doc) }}"
                                                     alt="">
                                                <h6 class="fs-12">{{ translate('front') }}: {{ basename($doc) }}</h6>
                                            </div>
                                            <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                        </a>
                                    </div>
                                @endforeach
                            @endif
                            @if(!empty($driver->car_back_image) && is_array($driver->car_back_image))
                                @foreach ($driver->car_back_image as $doc)
                                    <div class="mb-2">
                                        <a href="{{ getMediaUrl($doc, 'driver/car') }}"
                                           download="{{ basename($doc) }}"
                                           target="_blank"
                                           class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <img class="w-30px aspect-1"
                                                     src="{{ getExtensionIcon($doc) }}"
                                                     alt="">
                                                <h6 class="fs-12">{{ translate('back') }}: {{ basename($doc) }}</h6>
                                            </div>
                                            <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                        </a>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endif

                    {{-- Other Documents --}}
                    @if(!empty($driver->other_documents) && is_array($driver->other_documents))
                        @php $has_docs = true; @endphp
                        <h6 class="mb-2">{{ translate('other_documents') }}</h6>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                            @foreach ($driver->other_documents as $doc)
                                <div class="mb-2">
                                    <a href="{{ getMediaUrl($doc, 'driver/document') }}"
                                       download="{{ basename($doc) }}"
                                       target="_blank"
                                       class="border border-C5D2D2 rounded p-3 d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img class="w-30px aspect-1"
                                                 src="{{ getExtensionIcon($doc) }}"
                                                 alt="">
                                            <h6 class="fs-12">{{ basename($doc) }}</h6>
                                        </div>
                                        <i class="bi bi-arrow-down-circle-fill fs-20 text-primary"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!$has_docs)
                        <p class="text-capitalize">{{translate('no_documents_found')}}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
