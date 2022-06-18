<?php

namespace Drupal\email_campaigner\API\Campaign;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Drupal\email_campaigner\API\Email\EmailLog;


class createSMTPConnection{

    private $availableMailAccount = null;
    private $smtpConnection = null;
    private $replaceFrom = null;
    private $replaceReplyTo = null;

    public function __construct($account){
        $this->database = \Drupal::database();
        $this->availableMailAccount = $account;
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
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;

            switch($this->availableMailAccount->provider){
                case 'gmail':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 587;
                    break;
            }
    
            $mail->SMTPAuth = true;
            $mail->setFrom($this->availableMailAccount->username);
            $mail->Username = $this->availableMailAccount->username;
            $mail->Password = $this->availableMailAccount->password;
            $this->replaceFrom = $this->availableMailAccount->replace_from;
            $this->replaceReplyTo = $this->availableMailAccount->replace_replyto;

            $this->smtpConnection = $mail;
        } 
    }

    private function getMailLogger(){
        return new EmailLog($this->availableMailAccount->account_id);
    }

    public function sendEmail($to, $toName, $from, $fromName, $subject, $body){
        $status = ['status' => 0, 'msg' => 'SMTP Connection not available'];
        if(!empty($this->smtpConnection)){
            $mailLog = $this->getMailLogger();
            $this->smtpConnection->addAddress($to, $toName);
            if(!empty($from) && !empty($fromName) ){
                $this->smtpConnection->setFrom($from, $fromName);
            }
            $this->smtpConnection->isHTML(true);                                  //Set email format to HTML
            $this->smtpConnection->Subject = $subject;
            $this->smtpConnection->Body = $body;

            if(!$this->smtpConnection->send()) {
                \Drupal::messenger()->addMessage('Error Sending Email, Error -'.$this->smtpConnection->ErrorInfo);
                $status = ['status' => 0, 'msg' => $this->smtpConnection->ErrorInfo];
                $mailLog->setInactive();
                $mailLog->genericLog($this->smtpConnection->ErrorInfo);

            } else {
                $status = ['status' => 1, 'msg' => 'Email Sent'];
            }
            $this->smtpConnection->clearAddresses();
            $this->smtpConnection->clearAttachments();
        } 
        return $status;
    }
}