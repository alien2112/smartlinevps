@section('title', translate('Onboarding_Setup'))

@extends('adminmodule::layouts.master')

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 text-capitalize mb-3">{{ translate('App_Onboarding_Screens') }}</h2>

            <form action="{{ route('admin.business.onboarding.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Manage_Screens') }}</h5>
                        <p class="fs-12 text-muted mb-0">{{ translate('Configure_the_intro_screens_shown_to_new_users_in_the_app.') }}</p>
                    </div>
                    <div class="card-body">
                        <div id="screens-container">
                            @forelse($screens as $key => $screen)
                                <div class="row border-bottom pb-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            @if(isset($screen['image']))
                                                <img src="{{ asset('storage/app/public/onboarding/'.$screen['image']) }}" class="img-fluid rounded border mb-2" style="max-height: 200px">
                                                <input type="hidden" name="screens[{{$key}}][existing_image]" value="{{ $screen['image'] }}">
                                            @endif
                                            <div class="mb-3">
                                                <label class="form-label">{{ translate('Image') }}</label>
                                                <input type="file" name="screens[{{$key}}][image]" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="mb-3">
                                            <label class="form-label">{{ translate('Title') }}</label>
                                            <input type="text" name="screens[{{$key}}][title]" class="form-control" value="{{ $screen['title'] ?? '' }}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ translate('Description') }}</label>
                                            <textarea name="screens[{{$key}}][description]" class="form-control" rows="3">{{ $screen['description'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                {{-- Default empty state if no screens exist --}}
                                @for($i=0; $i<3; $i++)
                                <div class="row border-bottom pb-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">{{ translate('Image') }}</label>
                                            <input type="file" name="screens[{{$i}}][image]" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-9">
                                        <div class="mb-3">
                                            <label class="form-label">{{ translate('Title') }}</label>
                                            <input type="text" name="screens[{{$i}}][title]" class="form-control" placeholder="{{ translate('Welcome_Screen_').($i+1) }}">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ translate('Description') }}</label>
                                            <textarea name="screens[{{$i}}][description]" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                </div>
                                @endfor
                            @endforelse
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">{{ translate('Save_Onboarding') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
