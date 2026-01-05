<ul class="nav d-inline-flex nav--tabs p-1 rounded bg-white">
    <li class="nav-item text-capitalize">
        <a href="{{route('admin.business.setup.info.index')}}"
           class="nav-link {{Request::is('admin/business/setup/info') ? 'active' : ''}}">{{translate('business_info')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.driver.index')}}"
           class="nav-link {{Request::is('admin/business/setup/driver') ? 'active' : ''}}">{{translate('driver')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.customer.index')}}"
           class="nav-link {{Request::is('admin/business/setup/customer') ? 'active' : ''}}">{{translate('customer')}}</a>
    </li>
    <li class="nav-item text-capitalize">
        <a href="{{route('admin.business.setup.trip-fare.penalty')}}"
           class="nav-link {{Request::is('admin/business/setup/trip-fare/penalty') ? 'active' : ''}}">{{translate('fare_&_penalty_settings')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.trip-fare.trips')}}"
           class="nav-link {{Request::is('admin/business/setup/trip-fare/trips') ? 'active' : ''}}">{{translate('trips')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.info.settings')}}"
           class="nav-link {{Request::is('admin/business/setup/info/settings') ? 'active' : ''}}">{{translate('settings')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.parcel.index')}}"
           class="nav-link {{Request::is('admin/business/setup/parcel') ? 'active' : ''}}">{{translate('parcel')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.parcel-refund.index')}}"
           class="nav-link {{Request::is('admin/business/setup/parcel-refund') ? 'active' : ''}}">{{translate('refund')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.safety-precaution.index', SAFETY_ALERT)}}"
           class="nav-link {{Request::is('admin/business/setup/safety-precaution/*') ? 'active' : ''}}">{{translate('safety_&_Precautions')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.referral-earning.index')}}"
           class="nav-link {{Request::is('admin/business/setup/referral-earning') ? 'active' : ''}}">{{translate('referral_earning')}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.chatting-setup.index',DRIVER)}}"
           class="nav-link {{Request::is('admin/business/setup/chatting-setup/*') ? 'active' : ''}}">{{translate('chatting_setup')}}</a>
    </li>
</ul>
