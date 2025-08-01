<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Http;

class UserSessionService
{
    public function __construct(protected Request $request)
    {
    }

    public function createSession(User $user, ?string $tokenId = null): void
    {
        $agent = new Agent();
        $agent->setUserAgent($this->request->userAgent());

        // Usa la IP pública en entorno de desarrollo
        $ip = app()->environment('local') ? '186.64.223.14' : $this->request->ip();
        $location = $this->getGeoLocation($ip);

        UserSession::create([
            'user_id'          => $user->id,
            'token_id'         => $tokenId,
            'ip_address'       => $ip,
            'user_agent'       => $this->request->userAgent(),
            'location_country' => $location['country'] ?? null,
            'location_region'  => $location['region'] ?? null,
            'location_city'    => $location['city'] ?? null,
            'latitude'         => $location['latitude'] ?? null,
            'longitude'        => $location['longitude'] ?? null,
            'device_type'      => $agent->device(),
            'platform'         => $agent->platform(),
            'browser'          => $agent->browser(),
            'browser_version'  => $agent->version($agent->browser()),
            'is_mobile'        => $agent->isMobile(),
            'is_desktop'       => $agent->isDesktop(),
            'last_activity_at' => now(),
        ]);
    }


    private function getGeoLocation(string $ip): array
    {
        try {
            $response = Http::get("https://ipapi.co/{$ip}/json/");
            if ($response->successful()) {
                return [
                    'country' => $response->json('country_name'),
                    'region'  => $response->json('region'),
                    'city'    => $response->json('city'),
                    'latitude' => $response->json('latitude'),
                    'longitude' => $response->json('longitude'),
                ];
            }
        } catch (\Throwable $e) {
            // Log fallback o ignora errores silenciosamente
        }

        return [];
    }
    
}
// This service handles user session creation, including device and location information.
// It uses the Jenssegers Agent package to detect device and browser details,
// and the ipapi service to get geolocation data based on the user's IP address.