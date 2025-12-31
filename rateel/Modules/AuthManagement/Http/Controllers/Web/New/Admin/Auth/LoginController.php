<?php

namespace Modules\AuthManagement\Http\Controllers\Web\New\Admin\Auth;

use App\Http\Controllers\BaseController;
use App\Service\BaseServiceInterface;
use Brian2694\Toastr\Facades\Toastr;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Modules\BusinessManagement\Service\Interface\ExternalConfigurationServiceInterface;
use Modules\UserManagement\Service\Interface\EmployeeServiceInterface;

class LoginController extends BaseController
{
    protected $employeeService;
    protected $externalConfigurationService;

    public function __construct(EmployeeServiceInterface $employeeService, ExternalConfigurationServiceInterface $externalConfigurationService)
    {
        parent::__construct($employeeService);
        $this->employeeService = $employeeService;
        $this->externalConfigurationService = $externalConfigurationService;
        $this->middleware(function ($request, $next) {
            if (auth()->check()) {
                return redirect(route('admin.dashboard'));
            }
            return $next($request);
        })->except('logout');
    }

    /**
     * @return Renderable
     */

    public function loginView(): Renderable
    {
        return view('authmanagement::login');
    }

    public function login(Request $request)
    {
        try {
            $user = $this->employeeService->findOneBy(criteria: ['email' => $request['email']]);
        } catch (\Exception $e) {
            Toastr::error(NO_DATA_200['message']);
            return back();
        }
        // CAPTCHA LOGIC COMMENTED OUT
        // $recaptcha = businessConfig('recaptcha')?->value;
        // if (isset($recaptcha) && $recaptcha['status'] == 1 && !$request?->set_default_captcha) {
        //     $request->validate([
        //         'g-recaptcha-response' => [
        //             function ($attribute, $value, $fail) {
        //                 $secret_key = businessConfig('recaptcha')?->value['secret_key'];
        //                 $response = $value;

        //                 $gResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
        //                     'secret' => $secret_key,
        //                     'response' => $value,
        //                     'remoteip' => \request()->ip(),
        //                 ]);

        //                 if (!$gResponse->successful()) {
        //                     $fail(translate('ReCaptcha Failed'));
        //                 }
        //             },
        //         ],
        //     ]);
        // } else {
        //     if (strtolower($request->default_captcha_value) != strtolower(Session('default_captcha_code'))) {
        //         Session::forget('default_captcha_code');
        //         return back()->withErrors(translate('Captcha Failed'));
        //     }
        // }

        if (isset($user) && Hash::check($request['password'], $user->password)) {
            if (($user && $user?->role?->is_active) || $user->user_type === 'super-admin') {
                if (auth()->attempt(['email' => $request['email'], 'password' => $request['password']])) {
                    Toastr::success(AUTH_LOGIN_200['message']);
                    return redirect()->route('admin.dashboard');
                }
            }
            Toastr::error(ACCOUNT_DISABLED['message']);
            return back();
        }
        Toastr::error(AUTH_LOGIN_401['message']);
        return back();
    }

    public function externalLoginFromMart(Request $request)
    {
        $martToken = $this->externalConfigurationService->findOneBy(['key' => 'mart_token'])?->value ?? null;
        $systemSelfToken = $this->externalConfigurationService->findOneBy(['key' => 'system_self_token'])?->value ?? null;
        $martBaseUrl = $this->externalConfigurationService->findOneBy(['key' => 'mart_base_url'])?->value ?? null;
        if ($martToken == $request->mart_token && $martBaseUrl == $request->mart_base_url && $systemSelfToken == $request->drivemond_token) {
            $user = $this->employeeService->findOneBy(criteria: ['user_type' => 'super-admin']);
            if (isset($user)) {
                if (($user && $user?->role?->is_active) || $user->user_type === 'super-admin') {
                    if (Auth::loginUsingId($user->id)) {
                        Toastr::success(AUTH_LOGIN_200['message']);
                        return redirect()->route('admin.dashboard');
                    }
                }
                Toastr::error(ACCOUNT_DISABLED['message']);
                return back();
            }
        }
        Toastr::error(AUTH_LOGIN_401['message']);
        return back();
    }

    public function logout()
    {
        if (auth()->user()) {
            auth()->guard('web')->logout();
            Toastr::success(AUTH_LOGOUT_200['message']);
            return redirect(route('admin.auth.login'));
        }
        return redirect()->back();
    }
    public function captcha($tmp): void
    {

        $phrase = new PhraseBuilder;
        $code = $phrase->build(4);
        $builder = new CaptchaBuilder($code, $phrase);
        $builder->setBackgroundColor(220, 210, 230);
        $builder->setMaxAngle(25);
        $builder->setMaxBehindLines(0);
        $builder->setMaxFrontLines(0);
        $builder->build($width = 100, $height = 40, $font = null);
        $phrase = $builder->getPhrase();

        if (Session::has('default_captcha_code')) {
            Session::forget('default_captcha_code');
        }
        Session::put('default_captcha_code', $phrase);
        header("Cache-Control: no-cache, must-revalidate");
        header("Content-Type:image/jpeg");
        $builder->output();
    }
}
