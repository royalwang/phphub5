<?php namespace App\Http\Controllers\Traits;

use Auth;
use Socialite;
use Illuminate\Http\Request;
use App\Models\User;
use Flash;

trait SocialiteHelper
{
    public function oauth(Request $request)
    {
        $driver = $request->input('driver');
        $driver = !in_array($driver, $this->oauthDriver)
                    ? $this->oauthDriver[0]
                    : $driver;

        if (Auth::check() && Auth::user()->register_source == $driver) {
            return redirect('/');
        }

        return Socialite::driver($driver)->redirect();
    }

    public function callback(Request $request)
    {
        $driver = $request->input('driver');

        if (
            !in_array($driver, $this->oauthDriver)
            || (Auth::check() && Auth::user()->register_source == $driver)
        ) {
            return redirect()->intended('/');
        }

        $oauthUser = Socialite::with($driver)->user();
        $user = User::getByDriver($driver, $oauthUser->id);

        if (Auth::check()) {
            if ($user && $user->id != Auth::id()) {
                Flash::error(lang('Sorry, this socialite account has been registed.', ['driver' => lang($driver)]));
            } else {
                $this->bindSocialiteUser($oauthUser, $driver);
                Flash::success(lang('Bind Successfully!', ['driver' => lang($driver)]));
            }

            return redirect(route('users.edit_social_binding', Auth::id()));
        } else {
            if ($user) {
                return $this->loginUser($user);
            }

            return $this->userNotFound($driver, $oauthUser);
        }
    }

    public function bindSocialiteUser($oauthUser, $driver)
    {
        $currentUser = Auth::user();

        if ($driver == 'github') {
            $currentUser->github_id = $oauthUser->id;
            $currentUser->github_url = $oauthUser->user['url'];
        } elseif ($driver == 'weixin') {
            $currentUser->wechat_openid = $oauthUser->id;
            $currentUser->wechat_unionid = $oauthUser->user['unionid'];
        }

        $currentUser->save();
    }
}
