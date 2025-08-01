<?php

namespace App\Http\Resources\User\Session;

use Illuminate\Http\Resources\Json\JsonResource;

class UserSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $currentToken = $request->user()?->currentAccessToken();
        $isCurrent = $currentToken ? (int)$this->token_id === (int)$currentToken->id : false;


        return [
            'id' => $this->id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'device_type' => $this->device_type,
            'platform' => $this->platform,
            'browser' => $this->browser,
            'browser_version' => $this->browser_version,
            'is_mobile' => (bool) $this->is_mobile,
            'is_desktop' => (bool) $this->is_desktop,
            'last_activity_at' => $this->last_activity_at,
            'is_current' => $isCurrent,
            'location' => [
                'country' => $this->location_country,
                'region' => $this->location_region,
                'city' => $this->location_city,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
        ];
    }
}
