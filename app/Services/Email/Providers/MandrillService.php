<?php

namespace App\Services\Email\Providers;

use App\Services\Email\EmailProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\IntegrationService;

class MandrillService implements EmailProviderInterface
{
    protected string $apiKey;
    protected string $webhookKey;
    protected string $apiUrl = 'https://mandrillapp.com/api/1.0/messages/send.json';

    public function __construct(IntegrationService $integrationService)
    {
        $credentials = $integrationService->getCredentials('mandrill');
        $this->apiKey = $credentials['secret'] ?? null;
        $this->webhookKey = $credentials['webhook_key'] ?? null;
    }

    public function send(string $recipientEmail, string $subject, string $htmlContent, string $fromEmail, string $fromName, array $metadata = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('Mandrill API key is not set.');
            return null;
        }

        $response = Http::post($this->apiUrl, [
            'key' => $this->apiKey,
            'message' => [
                'html' => $htmlContent,
                'subject' => $subject,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to' => [['email' => $recipientEmail, 'type' => 'to']],
                'track_opens' => true,
                'track_clicks' => true,
                'metadata' => $metadata,
            ],
            'async' => false,
        ]);

        if ($response->successful() && isset($response->json()[0]['_id'])) {
            return $response->json()[0]['_id']; // Devuelve el ID del mensaje de Mandrill
        }

        Log::error('Mandrill send failed', ['response' => $response->body()]);
        return null;
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $url = url($request->path());
        $postData = $request->all();
        ksort($postData);

        $signedData = $url;
        foreach ($postData as $key => $value) {
            $signedData .= $key . $value;
        }

        $generatedSignature = base64_encode(hash_hmac('sha1', $signedData, $this->webhookKey, true));

        return hash_equals($request->header('X-Mandrill-Signature'), $generatedSignature);
    }
}