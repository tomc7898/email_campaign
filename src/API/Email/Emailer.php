<?php

namespace Drupal\email_campaigner\API\Email;

use Drupal\email_campaigner\API\Email\EmailTracker;


class EMailer
{
    protected $connection;
    protected $to = null;
    protected $from = null;
    protected $messageId = null;
    protected $fromName = null;
    protected $toName = null;
    protected $subject = null;
    protected $body = null;
    protected $attachments = [];

    public function __construct($to, $from)
    {
        $this->connection = \Drupal::database();
        $this->to = $to;
        $this->from = $from;
        $this->messageId = null;
        $this->trackerImageId = null;
    }

    public function saveMessage()
    {
        $this->messageId = $this->connection->insert('email_message')
            ->fields([
                'to_user' => $this->to,
                'from_user' => $this->from,
            ])->execute();
        $tracker = new EmailTracker($this->to, $this->from, $this->messageId);
        $this->trackerImageId = $tracker->getImageId();
    }

    public function getMessageId()
    {
        return $this->messageId;
    }

    public function getTrackerImageId()
    {
        return $this->trackerImageId;
    }

    public function setSubject($subject)
    {
        if (!empty($this->messageId)) {
            $this->subject = $subject;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                ['subject' => $this->subject]
            )->execute();
        }
    }

    public function setBody($body)
    {
        if (!empty($this->messageId)) {
            $this->body = $body;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                ['body' => $this->body]
            )->execute();
        }
    }

    public function getBody(){
        return $this->body;
    }

    public function setAttachments($attachments)
    {
        if (!empty($this->messageId)) {
            $this->attachments = $attachments;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                ['attachments' => json_encode($this->attachments)]
            )->execute();
        }
    }

    public function setMailStatus($status, $error = '')
    {
        if (!empty($this->messageId)) {
            $this->status = $status;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                [
                    'mail_sent' => $this->status,
                    'error_message' => $error
                ]
            )->execute();
        }
    }

    public function getMailStatus(){
        return $this->status;
    }

    public function setFromName($fromName)
    {
        if (!empty($this->messageId)) {
            $this->fromName = $fromName;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                [
                    'from_name' => $this->fromName
                ]
            )->execute();
        }
    }

    public function setToName($toName)
    {
        if (!empty($this->messageId)) {
            $this->toName = $toName;
            $this->connection->merge('email_message')->keys(
                ['message_id' => $this->messageId]
            )->fields(
                [
                    'to_name' => $this->toName
                ]
            )->execute();
        }
    }
    // public function setBody

    public function getMessage()
    {
        $message = [];
        $message['from'] = ['name' => $this->fromName, 'address' => $this->from];
        $message['to'] = ['name' => $this->toName, 'address' => $this->to];
        $message['subject'] = $this->subject;
        $message['body'] = $this->body;
        $message['attachments'] = $this->attachments;
        return $message;
    }
}