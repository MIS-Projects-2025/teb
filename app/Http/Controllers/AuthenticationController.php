<?php
//Template
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticationController extends Controller
{
    private function resolveToken(Request $request): ?string
    {
        return session('emp_data.token')
            ?? $request->cookie(env('SSO_COOKIE_NAME', 'sso_token'));
    }

    public function logout(Request $request)
    {
        $token   = $this->resolveToken($request);
        // Template has no independent identity from Authify — logging out
        // of Template always tears down the whole SSO session, matching
        // what happens when logging out of Telford directly.
        $cascade = '1';

        session()->forget('emp_data');
        session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookieName    = env('SSO_COOKIE_NAME', 'sso_token');
        $expiredCookie = cookie()->forget($cookieName);

        $redirectUrl = urlencode(route('dashboard'));
        $portalId    = config('services.authify.portal_id');

        return redirect(
            config('services.authify.base_url') . "/logout?token={$token}&redirect={$redirectUrl}&cascade={$cascade}&portal_id={$portalId}"
        )->withCookie($expiredCookie);
    }

    public function siblings(Request $request)
    {
        $token = $this->resolveToken($request);
        if (!$token) {
            return response()->json(['siblings' => []]);
        }

        $row = DB::connection('authify')->table('authify_sessions')->where('token', $token)->first();
        if (!$row) {
            return response()->json(['siblings' => []]);
        }

        $siblings = DB::connection('authify')->table('authify_sessions')
            ->where('emp_id', $row->emp_id)
            ->where('portal_id', '!=', $row->portal_id)
            ->pluck('portal_id');

        return response()->json(['siblings' => $siblings]);
    }
}