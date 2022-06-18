<?php
namespace Drupal\email_campaigner\API\Campaign;

class EmailSender{

    private $leadsAvailable = true;
    private $campaignMailer = null;
    private $campaignContent = null;
    private $database = null;

    public function __construct($campaign_id){

        $this->database = \Drupal::database();
        $this->campaign = $this->database->query('SELECT * FROM `email_campaign` 
                WHERE campaign_id = :campaign_id',
                [':campaign_id'=>$campaign_id])
            ->fetch();

        $emailerClass = "Drupal\\email_campaigner\\API\\Campaign\\CampaignEmailer";
        $contentClass = "Drupal\\email_campaigner\\API\\Campaign\\EmailContent";
        $this->campaignMailer = new $emailerClass($this->campaign);
        $this->campaignContent = new $contentClass($this->campaign);
        $this->emailAccounts = $this->campaignMailer->fetchAvailableEmailAccount();
        $this->campaignMailer->createSMTPConnection();
        $this->leads = $this->campaignMailer->fetchAvailableLeads();
        $this->leadsCount = count($this->leads);
        $this->leadsIndex = 0;
        $this->setLeadsAvailable();
    }

    protected function setLeadsAvailable(){
        if($this->leadsCount == 0){
            $this->leadsAvailable = false;
        }
    }

    public function getOneLeadfromQueue(){
        if($this->leadsAvailable){
            $lead = $this->leads[$this->leadsIndex];
            $this->leadsCount--;
            $this->leadsIndex++;
            
            $this->setLeadsAvailable();
            return $lead;
        }
        return null;
    }

    public function getPreapredLead($dummy = false){
        $lead = $this->getOneLeadfromQueue();
        if(!empty($lead)){
            $this->campaignContent->setLead($lead);
            $emailContent = $this->campaignContent->saveEmailContent($dummy);

            if(!empty($emailContent)){
                $htmlContent = $emailContent['html'];
                $jsonContent = $emailContent['json'];
                // $subjectContent = "<div class='subject'>".$emailContent['subject']."</div>";

                return $emailContent;
            }
        }
        return null;
    }

    public function sendPreparedLead($dummy = false){
        if (!empty($this->emailAccounts)) {
            $lead = $this->getOneLeadfromQueue();
            if(!empty($lead)){
                $this->campaignContent->setLead($lead);
                $emailContent = $this->campaignContent->saveEmailContent($dummy);
                $mailSentStatus = [];
                if(!$dummy){
                    $mailSentStatus = $this->campaignMailer->sendEmail($lead->email, $lead->firstname, '', $this->campaign->sender_name,  $emailContent['subject'], $emailContent['html']);
                    $this->database
                        ->merge('email_campaign__lead_campaign')
                        ->keys(['lead_id'=>$emailContent['lead_id'], 'campaign_id'=> $emailContent['campaign_id']])
                        ->fields(['message_id'=>$emailContent['message_id'],'mail_sent'=>$mailSentStatus['status']])
                        ->execute();
                }

                return [
                    'lead'=>$lead,
                    'emailContent'=>$emailContent,
                    'mailSentStatus' => $mailSentStatus
                ];
            }
        }
    }
}
