<?php 

namespace Drupal\email_campaigner\API\Campaign;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Drupal\email_campaigner\API\Email\EmailLog;

class CampaignEmailer{

    private $campaign = null;
    private $availableMailAccount = null;
    private $availableLeads = null;
    private $smtpConnection = null;
    private $replaceFrom = null;
    private $replaceReplyTo = null;

    public function __construct($campaign){
        $this->database = \Drupal::database();
        $this->campaign = $campaign;
    }

    public function getSMTPConnection(){
        return $this->smtpConnection;
    }

    public function createSMTPConnection()
    {
        if (!empty($this->availableMailAccount)) {
            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Timeout = 15;
            
            // SMTP::DEBUG_OFF = off (for production use)
            // SMTP::DEBUG_CLIENT = client messages
            // SMTP::DEBUG_SERVER = client and server messages
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            
            // if(empty($this->mailConfig->encryption)){
            //     $mail->SMTPAutoTLS = false;
            //     $mail->SMTPSecure = false;
            // } else {
            //     $mail->SMTPSecure = !empty($this->mailConfig->ssltype) && $this->mailConfig->ssltype == 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            // }
            $mail->SMTPAuth = true;
            $mail->setFrom($this->availableMailAccount->username, $this->campaign->sender_name);
            $mail->Username = $this->availableMailAccount->username;
            $mail->Password = $this->availableMailAccount->password;
            $this->replaceFrom = $this->availableMailAccount->replace_from;
            $this->replaceReplyTo = $this->availableMailAccount->replace_replyto;

            $this->smtpConnection = $mail;
            \Drupal::messenger()->addMessage(print_r($mail, 1));
        } else {
            \Drupal::messenger()->addMessage('No Mail Config Available.');
            \Drupal::logger('Campaign')->notice('No Mail Config Available for '.$this->campaign->campaign_id);
        }
    }

    private function getMailLogger(){
        return new EmailLog($this->availableMailAccount->account_id);
    }

    public function sendDummyEmail($to, $toName, $from, $fromName, $subject, $body){
        return ['status' => 1, 'msg' => 'Email Sent'];
    }

    public function sendEmail($to, $toName, $from, $fromName, $subject, $body){
        $status = ['status' => 2, 'msg' => 'SMTP Connection not available'];
        if(!empty($this->smtpConnection)){
            $mailLog = $this->getMailLogger();

            $this->smtpConnection->addAddress($to, $toName);
            if(!empty($this->replaceReplyTo) && $this->replaceReplyTo == 1){
                $this->smtpConnection->addReplyTo($this->campaign->campaign_email_address, $this->campaign->sender_name);
            }
            if(!empty($this->replaceFrom) && $this->replaceFrom == 1){
                $this->smtpConnection->setFrom($this->campaign->campaign_email_address, $this->campaign->sender_name);
            } else {
                $this->smtpConnection->setFrom($from, $fromName);
            }
    
            $this->smtpConnection->isHTML(true);                                  //Set email format to HTML
            $this->smtpConnection->Subject = $subject;
            $this->smtpConnection->Body = $body;

            if(!$this->smtpConnection->send()) {
                \Drupal::messenger()->addMessage('Error Sending Email, Error -'.$this->smtpConnection->ErrorInfo);
                \Drupal::logger('Campaign')->notice('Campaing Mail Error :: '.$this->campaign->campaign_id.' :: Error Sending Email, Error -'.$this->smtpConnection->ErrorInfo);
 
                $status = ['status' => 0, 'msg' => $this->smtpConnection->ErrorInfo];
                $mailLog->setInactive();
                $mailLog->genericLog($this->smtpConnection->ErrorInfo);
            } else {
                $status = ['status' => 1, 'msg' => 'Email Sent'];
            }
            $mailLog->setLog();
            $this->smtpConnection->clearAddresses();
            $this->smtpConnection->clearAttachments();
        } 
        return $status;
    }

    public function fetchAvailableEmailAccount(){
        $query = 'SELECT accounts.*
        FROM `email_campaign__accounts` accounts
        LEFT JOIN `email_campaign__accounts_log` accounts_log
        ON accounts.account_id = accounts_log.account_id
        AND accounts_log.date = :match_date
        WHERE 
            email = :email_id
            AND active = 1
                AND (
                accounts_log.date IS NULL
                OR (
                    mail_count < max_daily_email_count
                                AND accounts_log.date = :log_date
                                AND TIMESTAMPDIFF(MINUTE,last_mail_sent,NOW()) > min_time_diff
                            )
                        )
        ORDER BY last_mail_sent ASC 
        LIMIT 1;';
        $current_date = date('Y-m-d');
        $this->availableMailAccount = $this->database->query($query,
         [':email_id' => $this->campaign->campaign_email_address,':match_date'=>$current_date, ':log_date'=>$current_date])->fetch();
        return $this->availableMailAccount;
    }

    public function fetchAvailableLeads(){
        $query = 'SELECT * FROM email_campaign__leads LEFT JOIN email_campaign__lead_campaign USING (lead_id, campaign_id) WHERE mail_sent IS NULL';
        $this->availableLeads = $this->database->queryRange($query,0,5)->fetchAll();
        return $this->availableLeads;
    }
}