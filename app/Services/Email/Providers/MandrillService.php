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
    protected string $sendTemplateUrl = 'https://mandrillapp.com/api/1.0/messages/send-template.json';

    public function __construct(IntegrationService $integrationService)
    {
        $credentials = $integrationService->getCredentials('mandrill');
        $this->apiKey = $credentials['secret'] ?? null;
        $this->webhookKey = $credentials['webhook_key'] ?? null;
    }

    public function send(string $recipientEmail, string $subject, ?string $htmlContent, string $fromEmail, string $fromName, array $metadata = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('Mandrill API key is not set.');
            return null;
        }

        $templateId = $metadata['template_id'] ?? null;
        
        if ($templateId) {
            $payload = [
                'key' => $this->apiKey,
                'template_name' => $templateId,
                'template_content' => [],
                'message' => [
                    'subject' => $subject,
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'to' => [['email' => $recipientEmail, 'type' => 'to']],
                    'track_opens' => true,
                    'track_clicks' => true,
                    'global_merge_vars' => $metadata['global_merge_vars'] ?? [],
                    'merge_vars' => $metadata['merge_vars'] ?? [],
                ],
            ];
            $response = Http::post($this->sendTemplateUrl, $payload);
        } else {
            $payload = [
                'key' => $this->apiKey,
                'message' => [
                    'html' => $htmlContent,
                    'subject' => $subject,
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'to' => [['email' => $recipientEmail, 'type' => 'to']],
                    'track_opens' => true,
                    'track_clicks' => true,
                ],
            ];
            $response = Http::post($this->apiUrl, $payload);
        }

        if ($response->successful() && isset($response->json()[0]['_id'])) {
            return $response->json()[0]['_id'];
        }

        Log::error('Mandrill send failed', ['response' => $response->body()]);
        return null;
    }

    private function sendWithTemplate(string $recipientEmail, string $subject, string $fromEmail, string $fromName, array $metadata): ?string
    {
        $response = Http::post($this->sendTemplateUrl, [
            'key' => $this->apiKey,
            'template_name' => $metadata['template_id'],
            'template_content' => [], // Dejar vacío para usar el contenido de Mandrill
            'message' => [
                'subject' => $subject,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to' => [['email' => $recipientEmail, 'type' => 'to']],
                'track_opens' => true,
                'track_clicks' => true,
                'merge_vars' => $metadata['merge_vars'] ?? [], // Aquí pasamos las variables
                'global_merge_vars' => $metadata['global_merge_vars'] ?? [],
            ],
        ]);
        
        if ($response->successful() && isset($response->json()[0]['_id'])) {
            return $response->json()[0]['_id'];
        }

        Log::error('Mandrill send-template failed', ['response' => $response->body()]);
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

    public function getTemplateInfo(string $templateName): ?array
    {
        if (empty($this->apiKey)) return null;

        $response = Http::post('https://mandrillapp.com/api/1.0/templates/info.json', [
            'key' => $this->apiKey,
            'name' => $templateName,
        ]);

        if ($response->successful()) {
            return $response->json(); // Devuelve la info completa de la plantilla
        }

        Log::error('Mandrill getTemplateInfo failed', ['response' => $response->body()]);
        return null;
    }
}