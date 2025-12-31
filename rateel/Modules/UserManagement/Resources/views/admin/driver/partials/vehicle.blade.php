<div class="tab-pane fade active show" role="tabpanel">
    <h2 class="fs-22 mb-3">{{ translate('vehicle') }}</h2>

    @if($commonData['driver']?->vehicle)
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-4">
                <h5 class="d-flex align-items-center gap-2 text-primary text-capitalize">
                    <i class="bi bi-person-fill-gear"></i>
                    {{ translate('vehicle_info') }}
                </h5>
            </div>
            <div class="row gy-5">
                <div class="col-lg-4">
                    <div class="media flex-wrap gap-3 gap-lg-4">
                        <div class="avatar avatar-135 rounded">
                            <img src="{{ onErrorImage(
                                $commonData['driver']->vehicle->model?->image,
                                asset('storage/app/public/vehicle/model') . '/' . $commonData['driver']->vehicle->model?->image,
                                asset('public/assets/admin-module/img/media/upload-file.png'),
                                'vehicle/model/',
                            ) }}"
                                class="rounded dark-support fit-object-contain" alt="">
                        </div>
                        <div class="media-body">
                            <div class="d-flex flex-column align-items-start gap-1">
                                <h6 class="mb-10">{{ $commonData['driver']->vehicle->brand?->name ?? 'Unknown brand' }}
                                    - {{ $commonData['driver']->vehicle->model?->name ?? 'Unknown model' }}</h6>
                                <ul class="nav text-dark d-flex flex-column gap-2 mb-0">
                                    <li>
                                        <span class="text-muted">{{ translate('owner') }}</span>
                                        <span class="">{{ $commonData['driver']->vehicle->ownership ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                        <span class="text-muted">{{ translate('category') }}</span>
                                        <span class="">
                                            {{ $commonData['driver']->vehicle->category?->name ?? 'Uncategorized' }}</span>
                                    </li>
                                    <li>
                                        <span class="text-muted">{{ translate('brand') }}</span>
                                        <span
                                            class="">{{ $commonData['driver']->vehicle->brand?->name ?? 'Unknown brand' }}</span>
                                    </li>
                                    <li>
                                        <span class="text-muted">{{ translate('model') }}</span>
                                        <span
                                            class="">{{ $commonData['driver']->vehicle->model?->name ?? 'Unknown model' }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="d-flex justify-content-around flex-wrap gap-3">
                        <div class="d-flex align-items-center flex-column gap-3">
                            <div class="circle-progress" data-parsent="{{ $otherData['vehicle_rate'] ?? 0 }}"
                                data-color="#28A745">
                                <div class="content">
                                    <h6 class="persent">{{ $otherData['vehicle_rate'] ?? 0 }}%</h6>
                                </div>
                            </div>
                            <h6 class="fw-semibold fs-12 text-capitalize">
                                {{ translate('trip_rate_for_this_vehicle') }}</h6>
                        </div>

                        <div class="d-flex align-items-center flex-column gap-3">
                            <div class="circle-progress" data-parsent="{{ $otherData['parcel_rate'] ?? 0 }}"
                                data-color="#0073B4">
                                <div class="content">
                                    <h6 class="persent">{{ $otherData['parcel_rate'] ?? 0 }}%</h6>
                                </div>
                            </div>
                            <h6 class="fw-semibold fs-12 text-capitalize">
                                {{ translate('parcel_delivery_rate_for_this_vehicle') }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h5 class="text-primary mb-3 d-flex gap-2 align-items-center"><i class="bi bi-truck-front-fill"></i> Vehicle
                Specification</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-borderX-0 p-lg">
                    <tbody>
                        <tr>
                            <td>{{ translate('viin') }}</td>
                            <td>{{ $commonData['driver']->vehicle->vin_number ?? 'N/A' }}</td>
                            <td>{{ translate('fuel_type') }}</td>
                            <td>{{ $commonData['driver']->vehicle->fuel_type ? translate($commonData['driver']->vehicle->fuel_type) : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td>{{ translate('licence_plate_number') }}</td>
                            <td>{{ $commonData['driver']->vehicle->licence_plate_number ?? 'N/A' }}</td>
                            <td>{{ translate('engine') }}</td>
                            <td>{{ $commonData['driver']->vehicle->model?->engine ?? 'N/A' }} {{ $commonData['driver']->vehicle->model?->engine ? translate('cc') : '' }}</td>
                        </tr>
                        <tr>
                            <td>{{ translate('licence_expire_date') }}</td>
                            <td>{{ $commonData['driver']->vehicle->licence_expire_date ?? 'N/A' }}</td>
                            <td>{{ translate('seat_capacity') }}</td>
                            <td>{{ $commonData['driver']->vehicle->model?->seat_capacity ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td>{{ translate('transmission') }}</td>
                            <td>{{ $commonData['driver']->vehicle->transmission ?? 'N/A' }}</td>
                            <td>{{ translate('hatch_bag_capacity') }}</td>
                            <td>{{ $commonData['driver']->vehicle->model?->hatch_bag_capacity ?? 'N/A' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="text-primary mb-3 d-flex align-items-center gap-2"><i
                    class="bi bi-paperclip"></i>{{ translate('attached_documents') }}</h5>
            
            @php
                $driver = $commonData['driver'];
                $has_docs = false;
            @endphp

            {{-- Vehicle Documents --}}
            @if(!empty($driver->vehicle->documents) && is_array($driver->vehicle->documents))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('vehicle_documents') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->vehicle->documents as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'vehicle/document') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Driver Identity Images --}}
            @if(!empty($driver->identification_image) && is_array($driver->identification_image))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('identity_images') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->identification_image as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'driver/identity') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Driving License --}}
            @if(!empty($driver->driving_license) && is_array($driver->driving_license))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('driving_license') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->driving_license as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'driver/license') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Vehicle License --}}
            @if(!empty($driver->vehicle_license) && is_array($driver->vehicle_license))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('vehicle_license') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->vehicle_license as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'driver/vehicle') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Criminal Record --}}
            @if(!empty($driver->criminal_record) && is_array($driver->criminal_record))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('criminal_record') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->criminal_record as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'driver/record') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Car Images --}}
            @if((!empty($driver->car_front_image) && is_array($driver->car_front_image)) || (!empty($driver->car_back_image) && is_array($driver->car_back_image)))
                @php $has_docs = true; @endphp
                <h6 class="mb-2">{{ translate('car_images') }}</h6>
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @if(!empty($driver->car_front_image) && is_array($driver->car_front_image))
                        @foreach ($driver->car_front_image as $document)
                            <div class="mb-2">
                                <a download="{{ basename($document) }}"
                                    href="{{ getMediaUrl($document, 'driver/car') }}"
                                    target="_blank"
                                    class="border rounded p-3 d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="w-30px aspect-1"
                                             src="{{ getExtensionIcon($document) }}"
                                             alt="">
                                        <h6 class="fs-12">{{ translate('front') }}: {{ basename($document) }}</h6>
                                    </div>
                                    <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                                </a>
                            </div>
                        @endforeach
                    @endif
                    @if(!empty($driver->car_back_image) && is_array($driver->car_back_image))
                        @foreach ($driver->car_back_image as $document)
                            <div class="mb-2">
                                <a download="{{ basename($document) }}"
                                    href="{{ getMediaUrl($document, 'driver/car') }}"
                                    target="_blank"
                                    class="border rounded p-3 d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="w-30px aspect-1"
                                             src="{{ getExtensionIcon($document) }}"
                                             alt="">
                                        <h6 class="fs-12">{{ translate('back') }}: {{ basename($document) }}</h6>
                                    </div>
                                    <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
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
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    @foreach ($driver->other_documents as $document)
                        <div class="mb-2">
                            <a download="{{ basename($document) }}"
                                href="{{ getMediaUrl($document, 'driver/document') }}"
                                target="_blank"
                                class="border rounded p-3 d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <img class="w-30px aspect-1"
                                         src="{{ getExtensionIcon($document) }}"
                                         alt="">
                                    <h6 class="fs-12">{{ basename($document) }}</h6>
                                </div>
                                <i class="bi bi-arrow-down-circle-fill fs-16 text-primary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!$has_docs)
                <p class="text-muted">{{ translate('no_documents_attached') }}</p>
            @endif
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-car-front fs-1 text-muted mb-3"></i>
            <h5 class="text-muted">{{ translate('no_vehicle_assigned') }}</h5>
            <p class="text-muted">{{ translate('this_driver_has_no_vehicle_assigned_yet') }}</p>
        </div>
    </div>
    @endif
</div>
