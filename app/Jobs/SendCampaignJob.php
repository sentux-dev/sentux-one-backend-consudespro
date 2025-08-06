<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\Campaign;
use App\Models\Crm\Contact;
use App\Services\Email\EmailProviderManager;
use Illuminate\Support\Collection;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(public Campaign $campaign, public Collection $contacts, public bool $isTest = false) {}

    public function handle(EmailProviderManager $emailManager): void
    {
        foreach ($this->contacts as $contact) {
            $htmlContent = null;
            $metadata = [];
            $variables = $this->prepareAllVariables($contact);
            
            if ($this->campaign->template_id) {
                $providerVars = $this->prepareProviderMergeVars($contact, $variables);
                $metadata = [
                    'template_id' => $this->campaign->template_id,
                    'global_merge_vars' => $providerVars['global'],
                    'merge_vars' => $providerVars['contact_specific'],
                ];
            } else {
                $htmlContent = $this->campaign->content_html;
                foreach ($variables as $key => $value) {
                    $htmlContent = str_replace("*|{$key}|*", $value, $htmlContent);
                }
            }
            
            $log = null;
            if (!$this->isTest) {
                $log = $this->campaign->emailLogs()->create(['contact_id' => $contact->id, 'status' => 'enviado']);
            }

            $messageId = $emailManager->driver()->send(
                $contact->email,
                $this->campaign->subject,
                $htmlContent,
                $this->campaign->from_email,
                $this->campaign->from_name,
                array_merge(['log_id' => $log->id ?? null], $metadata)
            );
            
            if ($messageId && !$this->isTest) {
                $log->update(['provider_message_id' => $messageId]);
            }
        }
    }

    private function prepareAllVariables(Contact $contact): array
    {
        $systemVars = [
            'CURRENT_YEAR' => now()->year,
            'SUBJECT' => $this->campaign->subject,
            'UNSUB' => url("/marketing/unsubscribe/{$contact->uuid}"),
            'UPDATE_PROFILE' => url("/marketing/update-profile/{$contact->uuid}"),
        ];
        $userDefinedVars = $this->campaign->global_merge_vars ?? [];
        $contactVars = [
            'FNAME' => $contact->first_name,
            'LNAME' => $contact->last_name,
            'EMAIL' => $contact->email,
        ];
        return array_merge($systemVars, $userDefinedVars, $contactVars);
    }
    
    private function prepareProviderMergeVars(Contact $contact, array $allVariables): array
    {
        $contactSpecific = [];
        $global = [];
        foreach ($allVariables as $name => $content) {
            if (in_array($name, ['FNAME', 'LNAME', 'EMAIL', 'UNSUB', 'UPDATE_PROFILE'])) {
                $contactSpecific[] = ['name' => $name, 'content' => $content];
            } else {
                $global[] = ['name' => $name, 'content' => $content];
            }
        }
        return ['global' => $global, 'contact_specific' => [['rcpt' => $contact->email, 'vars' => $contactSpecific]]];
    }
}