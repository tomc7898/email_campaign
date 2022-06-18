<?php

namespace Drupal\email_campaigner\API\Email;

class EmailLog {
    protected $account_id;
    protected $database;

    public function __construct($account_id){
        $this->account_id = $account_id;
        $this->database = \Drupal::database();
    }

    public function setLog(){
        $transaction = $this->database->startTransaction();
        try{
            $log = $this->database->query(
                'select * from email_campaign__accounts_log where account_id = :account_id and date = :date limit 1',
                [':account_id'=> $this->account_id, ':date' => date('Y-m-d')],
            )->fetch();
            $mail_count = 0;
            if(!empty($log) && !empty($log->account_id)){
                $mail_count = $log->mail_count;
            }
            $mail_count++;
            $this->database
                ->merge('email_campaign__accounts_log')
                ->keys(['account_id'=>$this->account_id, 'date'=> date('Y-m-d')])
                ->fields(['mail_count'=>$mail_count,'last_mail_sent'=>date("Y-m-d H:i:s")])
                ->execute();
        } catch (\Exception $e) {
            \Drupal::messenger()->addMessage($e->getMessage());
            $transaction->rollBack();
        }
    }

    public function setInactive(){
        $this->database
                ->merge('email_campaign__accounts')
                ->keys(['account_id'=>$this->account_id])
                ->fields(['active' => 0])
                ->execute();
    }

    public function genericLog($message){
        $this->database
            ->insert('email_campaign__accounts_error_log')
            ->fields(
                [
                    'account_id'=>$this->account_id,
                    'error_message'=>$message
                ]
            )->execute();
    }
}