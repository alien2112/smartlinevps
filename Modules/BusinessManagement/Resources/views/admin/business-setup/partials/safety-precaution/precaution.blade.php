<div class="tab-pane fade {{Request::is('admin/business/setup/safety-precaution/precaution') ? 'show active' : ''}}">
    <form action="{{route('admin.business.setup.safety-precaution.precaution.store')}}" method="POST">
        @csrf
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-md-4">
                        <h6 class="mb-2">{{ translate('Safety Precautions') }}</h6>
                        <p class="fs-12 mb-0">{{ translate('Here you can set the title & description which will displayed in the precaution section as informative content  for the users') }}
                            .</p>
                    </div>
                    <div class="col-md-8">
                        <div class="p-30 rounded bg-F6F6F6">
                            <div class="mb-4">
                                <label class="form-label">
                                    {{ translate('title') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="character-count">
                                                <textarea name="title" id="safety_title"
                                                          class="form-control character-count-field"
                                                          rows="2"
                                                          placeholder="{{ translate('Type here safety precaution title') }}"
                                                          maxlength="80" data-max-character="80"
                                                          required></textarea>
                                    <span
                                        class="d-flex justify-content-end">{{ translate('0/80') }}</span>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">
                                    {{ translate('Description') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="character-count">
                                                <textarea name="description" id="safety_Description"
                                                          class="form-control character-count-field" rows="4"
                                                          placeholder="{{ translate('Description') }}"
                                                          maxlength="250" data-max-character="250"
                                                          required></textarea>
                                    <span
                                        class="d-flex justify-content-end">{{ translate('0/250') }}</span>
                                </div>
                            </div>
                            <div class="d-flex gap-4 align-items-center">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="inlineCheckbox1"
                                           value="{{ CUSTOMER }}" name="for_whom[]">
                                    <label class="form-check-label"
                                           for="inlineCheckbox1">{{ translate('For Customer') }}</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="inlineCheckbox2"
                                           value="{{ DRIVER }}" name="for_whom[]">
                                    <label class="form-check-label"
                                           for="inlineCheckbox2">{{ translate('For Driver') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit"
                            class="btn btn-primary text-uppercase">{{ translate('submit') }}</button>
                </div>
            </div>
        </div>
    </form>
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">{{ translate('Precautions List') }}</h3>
            <div class="table-responsive">
                <table class="table table-borderless align-middle">
                    <thead class="table-light align-middle">
                    <tr>
                        <th class="sl">{{ translate('SL') }}</th>
                        <th class="text-capitalize">{{ translate('For Whom') }}</th>
                        <th class="text-capitalize">{{ translate('Title') }}</th>
                        <th class="text-capitalize">{{ translate('Description') }}</th>
                        <th class="text-capitalize">{{ translate('Status') }}</th>
                        <th class="text-center action">{{ translate('Action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($safetyPrecautions as $key => $safetyPrecaution)
                        <tr>
                            <td class="sl">{{ $key + $safetyPrecautions->firstItem() }}</td>
                            <td>
                                <span
                                    class="text-capitalize min-w30">{{ is_array($safetyPrecaution->for_whom) ? implode(', ', $safetyPrecaution->for_whom) : $safetyPrecaution->for_whom }}</span>
                            </td>
                            <td>
                                <div class="max-i-s-30rem line--limit-2 targetToolTip"
                                     data-reason="{{ $safetyPrecaution->title }}"
                                     data-bs-custom-class="des-tooltip"
                                     data-bs-html="true"
                                     data-bs-placement="bottom"
                                >
                                    {{ $safetyPrecaution->title }}
                                </div>
                            </td>
                            <td>
                                <div class="max-i-s-30rem line--limit-2 targetToolTip"
                                     data-reason="{{ $safetyPrecaution->description }}"
                                     data-bs-custom-class="des-tooltip"
                                     data-bs-html="true"
                                     data-bs-placement="bottom"
                                >
                                    {{ $safetyPrecaution->description }}
                                </div>
                            </td>
                            <td>
                                <label class="switcher">
                                    <input class="switcher_input custom_status_change"
                                           type="checkbox"
                                           id="{{ $safetyPrecaution->id }}"
                                           data-url="{{ route('admin.business.setup.safety-precaution.precaution.status') }}"
                                           data-title="{{$safetyPrecaution->is_active == 1 ? translate('Are you sure to turn off this Safety Precaution') : translate('Are you sure to turn On this Safety Precaution')}}?"
                                           data-sub-title="{{$safetyPrecaution->is_active == 1 ? translate('Once you turn off this Safety Precaution') . ', ' .translate('drivers will no longer see this Safety Precaution.') : translate('Once you turn On this Safety Precaution') . ', ' . translate('drivers will see this Safety Precaution.')}}"
                                           data-confirm-btn="{{$safetyPrecaution->is_active == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                        {{ $safetyPrecaution->is_active == 1 ? "checked": ""  }}
                                    >
                                    <span class="switcher_control"></span>
                                </label>
                            </td>
                            <td>
                                <div
                                    class="d-flex justify-content-center gap-2 align-items-center">
                                    <button
                                        class="btn btn-outline-primary btn-action editSafetyPrecautionData"
                                        data-id="{{$safetyPrecaution->id}}">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button data-id="delete-{{ $safetyPrecaution?->id }}"
                                            data-message="{{ translate('want_to_delete_this_Q&A?') }}"
                                            type="button"
                                            class="btn btn-outline-danger btn-action form-alert">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                    <form
                                        action="{{ route('admin.business.setup.safety-precaution.precaution.delete', ['id' => $safetyPrecaution?->id]) }}"
                                        id="delete-{{ $safetyPrecaution?->id }}" method="post">
                                        @csrf
                                        @method('delete')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div
                                    class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                    <img
                                        src="{{ asset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                        alt="" width="100">
                                    <p class="text-center">{{translate('no_data_available')}}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div
                class="table-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2">
                <p class="mb-0"></p>

                <div
                    class="d-flex flex-wrap align-items-center justify-content-center justify-content-sm-end gap-3 gap-sm-4">
                    <nav>
                        {!! $safetyPrecautions->links() !!}
                    </nav>
                </div>
            </div>
        </div>


    </div>
</div>
<div class="modal fade" id="editSafetyPrecautionModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
        </div>
    </div>
</div>
