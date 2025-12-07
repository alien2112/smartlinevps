<form action="{{ route('admin.business.setup.safety-precaution.precaution.update',  $safetyPrecaution?->id) }}"
      method="post" id="updateForm" class="">
    @csrf
    <div class="modal-header border-0">
        <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                aria-label="Close">
        </button>
    </div>
    <div class="modal-body pt-0">
        <div class="mb-20">
            <h5 class="mb-2">{{ translate('Edit Safety Precaution') }}</h5>
            <div class="fs-12">
                {{ translate('Here you can set Safety Precaution') }}
            </div>
        </div>
        <div class="p-30 rounded bg-F6F6F6">
            <div class="mb-20">
                <label for="" class="form-label">
                    {{ translate('Title') }}
                    <span class="text-danger">*</span>
                </label>
                <div class="character-count">
                                    <textarea id="topic" name="title" class="form-control character-count-field"
                                              cols="30" rows="1"
                                              placeholder="{{ translate('Type here safety precaution title') }}"
                                              maxlength="81"
                                              data-max-character="81"
                                              required>{{ $safetyPrecaution->title }}</textarea>
                    <span class="d-flex justify-content-end">{{ translate('0/81') }}</span>
                </div>
            </div>
            <div class="mb-20">
                <label for="" class="form-label">
                    {{ translate('Description') }}
                    <span class="text-danger">*</span>
                </label>
                <div class="character-count">
                                    <textarea id="answer" name="description" class="form-control character-count-field"
                                              cols="30" rows="2"
                                              placeholder="{{ translate('Type description here') }}" maxlength="250"
                                              data-max-character="250"
                                              required>{{ $safetyPrecaution->description }}</textarea>
                    <span class="d-flex justify-content-end">{{ translate('0/250') }}</span>
                </div>
            </div>
            <div class="mb-20">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="inlineCheckbox1"
                           value="{{ CUSTOMER }}" name="for_whom[]"
                        {{ in_array(CUSTOMER, $safetyPrecaution->for_whom ?? []) ? 'checked' : '' }}
                    >
                    <label class="form-check-label"
                           for="inlineCheckbox1">{{ translate('For Customer') }}</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="inlineCheckbox2"
                           value="{{ DRIVER }}" name="for_whom[]"
                        {{ in_array(DRIVER, $safetyPrecaution->for_whom ?? []) ? 'checked' : '' }}
                    >
                    <label class="form-check-label"
                           for="inlineCheckbox2">{{ translate('For Driver') }}</label>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <div class="d-flex gap-3 justify-content-end">
            <button class="btn btn-light h-40px min-w-100px justify-content-center fw-semibold"
                    data-bs-dismiss="modal" type="button">{{ translate('Cancel') }}</button>
            <button type="submit"
                    class="btn btn-primary h-40px min-w-100px justify-content-center fw-semibold">{{ translate('Update') }}</button>
        </div>
    </div>
</form>
