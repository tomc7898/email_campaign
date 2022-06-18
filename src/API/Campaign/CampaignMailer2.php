<?php 

namespace Drupal\email_campaigner\API\Campaign;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

use Drupal\email_campaigner\API\MessageGenerator\MessageParameterGenertor;
use Drupal\email_campaigner\API\MessageGenerator\MessageGeneratorPCWorld;

use Drupal\email_campaigner\API\Email\EmailTracker;
use Drupal\email_campaigner\API\Email\Emailer;
use Drupal\email_campaigner\API\Email\EmailLog;
 
use Faker;

class CampaignMailer
{

    protected $dbConnection = [];
    protected $context = [];
    protected $leadContext = [];
    protected $accounts = [];
    protected $mailConfig = null;
    protected $smtpConnection = null;
    protected $replaceFrom = 1;
    protected $replaceReplyTo = 1;

    public function __construct($campaign_id)
    {
        global $base_url;
        $this->dbConnection = \Drupal::database();
        $this->campaignId = $campaign_id;
        $config = $this->dbConnection->query(
            'select * from email_campaign where campaign_id = :campaign_id',
            [':campaign_id' => $campaign_id]
        )->fetchAssoc();

        $this->context = [
            'campaign_id' => $config['campaign_id'],
            'campaign_name' => $config['campaign_name'],
            'sender_name' => $config['sender_name'],
            'campaign_email_address' => $config['campaign_email_address'],
            'website' => $config['website'],
            'unsubscribe_email' => $config['unsubscribe_email'],
            'status' => $config['status'],
            'active_days' => $config['active_days'] 
        ];
    }

    public function getEmailAccount()
    {
        $query = 'SELECT ma.*
            FROM mx_accounts ma
            LEFT JOIN mx_accounts_log mal
            ON ma.username = mal.username AND mal.date = CURRENT_DATE
            WHERE emailid = :emailid
                AND active = 1
                AND (
                    date IS NULL
                    OR (
                        mail_count < max_email_count
                        AND date = CURRENT_DATE
                        AND TIMESTAMPDIFF(MINUTE,last_mail_sent,NOW()) > min_time_diff
                    )
                )
            ORDER BY last_mail_sent ASC LIMIT 1';
        $this->mailConfig = $this->dbConnection->query($query, [':emailid' => $this->context['campaign_email_address']])->fetch();
        \Drupal::messenger()->addMessage('Mail Config - '.print_r($this->mailConfig,1));
    }

    public function createSMTPConnection()
    {
        if (!empty($this->mailConfig)) {
            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Timeout = 15;

            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->Host = !empty($this->mailConfig->hostname) ? $this->mailConfig->hostname : 'smtp.gmail.com';
            $mail->Port = !empty($this->mailConfig->port) ? $this->mailConfig->port : 587;
            
            if(empty($this->mailConfig->encryption)){
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = false;
            } else {
                $mail->SMTPSecure = !empty($this->mailConfig->ssltype) && $this->mailConfig->ssltype == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->SMTPAuth = true;
            $mail->setFrom($this->mailConfig->username);
            $mail->Username = $this->mailConfig->username;
            $mail->Password = $this->mailConfig->password;
            $this->replaceFrom = $this->mailConfig->replace_from;
            $this->replaceReplyTo = $this->mailConfig->replace_replyto;

            $this->smtpConnection = $mail;
            \Drupal::messenger()->addMessage(print_r($mail, 1));
        } else {
            \Drupal::messenger()->addMessage('No Mail Config Available.');
            \Drupal::logger('Campaign')->notice('No Mail Config Available for '.$this->campaignId);
        }
    }

    public function smtpTestEmail($testEmailAddress){
        $emailer = new Emailer($testEmailAddress, $this->context['campaign_email_address']);
        $emailer->saveMessage();
        $emailer->setFromName('Test Bot');
        $emailer->setSubject('Email Delivery Test');
        $emailer->setBody('This is a Test Email from SMTP. Connection Test Email.');
        $this->sendMail($emailer);
    }

    public function getMailLogger($username){
        return new EmailLog($username);
    }

    public function sendMail($emailer){
        $mail = $this->smtpConnection;
        if(!empty($mail)){
            $mailLog = $this->getMailLogger($this->mailConfig->username);
            $message = $emailer->getMessage();
            if($this->replaceFrom){
                $mail->setFrom($message['from']['address'],$message['from']['name']);
            }
            if($this->replaceReplyTo){
                $mail->addReplyTo($message['from']['address'], $message['from']['name']);
            }
            $mail->addAddress($message['to']['address'], $message['to']['name']);
            $mail->Subject =$message['subject'];
            $mail->msgHTML($message['body']);
            $mail->AltBody = $message['subject'];
            
            foreach($message['attachments'] as $attachment){
                $attachmentPath = \Drupal::service('file_system')->realpath($attachment);
                $mail->addAttachment($attachmentPath);
                \Drupal::messenger()->addMessage($attachmentPath);
            }
            //send the message, check for errors
            if (!$mail->send()) {
                \Drupal::messenger()->addMessage('Error Sending Email, Error -'.$mail->ErrorInfo);
                \Drupal::logger('Campaign')->notice('Campaing Mail Error :: '.$this->campaignId.' :: Error Sending Email, Error -'.$mail->ErrorInfo);
                $emailer->setMailStatus(0, $mail->ErrorInfo);
                $mailLog->setInactive();
                $mailLog->genericLog($message['from']['address'], $mail->ErrorInfo);
            } else {
                \Drupal::messenger()->addMessage('Email Sent');
                $emailer->setMailStatus(1);
            }
            $mailLog->setLog();
            $mail->clearAddresses();
            $mail->clearAttachments();
        } else {
            \Drupal::messenger()->addMessage('No Mail Client Available.');
            \Drupal::logger('Campaign')->notice('No Mail Client Available for '.$this->campaignId);
        }
    }

    public function loadLead()
    {
        if ($this->smtpConnection) {
            $limit = $this->mailConfig->max_mail_per_batch;
            $query = 'SELECT * FROM leadcampaign join leads using(lead_id) where campaign_id = :campaignId and mail_sent = 0';
            $leadsList = $this->dbConnection->queryRange($query, 0, $limit, [':campaignId'=>$this->campaignId])->fetchAll();
            // $leadsArray = json_decode(json_encode($leadsList), true);
            
            if(!empty($this->smtpConnection)){
                \Drupal::logger('Campaign')->notice('Loading Lead for Campaign :: '.$this->campaignId.', Available Lead => '.count($leadsList));
                foreach ($leadsList as $lead) {
                    $leadId = $lead->lead_id;
                    $to = $lead->email;
                    $toName = null;
                    if(!empty($lead->firstname) || $lead->firstname == 'Customer'){
                        $toName = "$lead->firstname $lead->lastname";
                    }
                    $emailer = $this->generateMailBody($to, $toName);
                    $messageId = $emailer->getMessageId();
                    $status = $emailer->getMailStatus();
                    $this->dbConnection
                    ->merge('leadcampaign')
                    ->keys(['campaign_id'=>$this->campaignId,'lead_id'=>$leadId])
                    ->fields([
                        'message_id'=>$messageId,
                        'mail_sent'=>empty($status) ? 0 : $status
                    ])->execute();
                    \Drupal::logger('Campaign')->notice('Campaign Id :: '.$this->campaignId.' Sent Message '.$messageId.' with Status -'.$status);
                }
            } else {
                \Drupal::messenger()->addMessage('No Lead : No Mail Client Available.');
                \Drupal::logger('Campaign')->notice('No Lead : No Mail Config Available for '.$this->campaignId);
                
            }
        }
    }

    // public function createAttachmentIfNotGenerated(){
    //     if(!$this->isAttachmentGenerated()){
    //         $this->createAttachment();
    //     }
    // }

    // public function isAttachmentGenerated(){
    //     $context = $this->leadContext;
    //     if(!file_exists($context['attachmentFilePDFLocation'])){
    //         return false;
    //     } else {
    //         return true;
    //     }
    // }

    public function generateMailBody($to, $toName = null, $dummy = false)
    {
        $emailer = new Emailer( preg_replace('/\s+/', '_', $to), $this->context['siteEmail']);
        if(!$dummy){
            $emailer->saveMessage();
        }
        $trackerImage = $emailer->getTrackerImageId() . '.png';
        $trackerImageLink = $this->context['trackerLink'] . $trackerImage;

        $configs = new MessageParameterGenertor();
        $invoiceId = strtoupper($configs->generate_invid());
        $paymentProfileId = strtoupper($configs->generate_uuid());
        $customerId = strtoupper($configs->generate_uuid());
        $amount = $configs->generate_amount();
        $chargeTimeline = $configs->generate_timeline();
        $executiveName = $configs->generate_executive_name();
        $orderTimeLine = $configs->generate_order_timeline();

        $organizationName = $this->context['siteName'];
        $phoneno = $this->context['customerCareNo'][array_rand($this->context['customerCareNo'], 1)];
        $supportlink = $this->context['websiteURL'] . '/support/';
        $organizationAddress =  $this->context['siteAddress'];
        $organizationLogo = $this->context['logoUrl']; //"https://att-eservice.com/img/att_logo.png";
        $organizationCSEMail = $this->context['customerCareEmail'];
        $product = $this->context['products'][array_rand($this->context['products'], 1)];
        $customer = "Customer";

        // if(!empty($toName)){
        //     $customer = $toName;
        //     $emailer->setToName($customer);
        // }
        $emailer->setFromName("$executiveName");

        $messageGenerator = new MessageGeneratorPCWorld();
        $salution = $messageGenerator->getSalutation();
        
        $subject =   str_replace('::organization', $organizationName, $messageGenerator->generateSubject());
        $subject =   str_replace('::amount', $amount, $subject);
        $subject =   str_replace('::invoice_id', $invoiceId, $subject);

        $emailer->setSubject($subject);

        $greetings = $salution['text'];
        if ($salution['type'] == 'personalized') {
            $greetings = "$greetings $customer";
        }

        $faker = Faker\Factory::create();

        $templates = [];
        $emailLines = [];
        $data = [];

        $templatesLines = [
            ['OPENING_LINE','THANKYOU_LINE'],
            ['ORDERCONFIRM_LINE','CHARGE_LINE','SUPPORT_LINE'],
            ['DISCLAIMER_LINE']
        ];

        $configs = new MessageParameterGenertor();

        $data['::order_no'] = strtoupper($configs->generate_invid());
        $data['::order_date'] = date('d-m-Y');
        $data['::organization'] = $this->context['siteName'];
        $data['::product_name'] = $product;
        $data['::timeline'] = $configs->generate_timeline();
        $data['::amount'] = $configs->generate_amount();
        $data['::userAgent'] = $faker->userAgent;
        $data['::executive_name'] = "$faker->firstName $faker->lastName";
        $data['::department'] = $faker->jobTitle;
        $data['::address'] = $this->context['siteAddress'];
        $data['::website'] = $this->context['websiteURL'];
        $data['::tracker_image'] = $trackerImageLink;
        $data['::subject'] = $subject;
        $data['::link'] = $supportlink;
        $data['::phone'] = "<b>$phoneno</b>";
        $data['::email'] = $this->context['customerCareEmail'];
        $data['::logo_url'] = $this->context['logoUrl'];

        foreach($templatesLines as $lineIndex => $templateLine){
            $templates[$lineIndex] =  isset($templates[$lineIndex]) ? $templates[$lineIndex] : [];
            $emailLines[$lineIndex] =  isset($emailLines[$lineIndex]) ? $emailLines[$lineIndex] : [];
            foreach($templateLine as $dbTemplate){
                $templateResult = $this->dbConnection->query("select * from email__message_template_lines 
                where type = :type order by RAND() limit 1",[':type'=>$dbTemplate])->fetch();
                if(!empty($templateResult->template)){
                    $templates[$lineIndex][$dbTemplate] = $templateResult;
                    $emailLines[$lineIndex][]= trim($templateResult->template,'.');
                }
            }
        }

        $emailLines[][] = 'Important : Due to developments related to COVID-19, some of our support centres are currently unavailable. Our teams are working to respond to all incoming requests as soon as possible. We apologise for any inconvenience and appreciate your patience.';

        $emailParagraphs = [];
        foreach($emailLines as $emailLine){
            $emailParagraphs[] = '<p>'.implode('. ', $emailLine).'.</p>';
        }
        $email = implode('', $emailParagraphs);

        $templateName = 'emailTemplate2.html';
        $templateFolderPath = DRUPAL_ROOT.'/'.drupal_get_path('module','controlpanel').'/src/EmailTemplates';
        $templateLocation = $templateFolderPath.'/'.$templateName;

        $templateContent = file_get_contents($templateLocation);

        $email = $templateContent;
        $body = strtr($email, $data);
        $emailer->setBody($body);
        if(!$dummy){
            $this->sendMail($emailer);
            return $emailer;
        } else {
            return $body;
        }
        
    }
}

