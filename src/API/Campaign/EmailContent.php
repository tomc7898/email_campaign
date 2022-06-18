<?php 

namespace Drupal\email_campaigner\API\Campaign;

use Drupal\email_campaigner\API\Email\EmailTracker;

class EmailContent{

    public function __construct($campaign){
        $this->database = \Drupal::database();
        $this->campaign = $campaign;
        $this->variables = [];
        $this->lead = []; 
        $this->fetchCustomVariables();
        $this->prepareVariables();
        $this->message = [];
    }

    public function fetchCustomVariables(){
        $this->customVariables = $this->database->query('SELECT * 
            FROM email_campaign__message_variables 
            WHERE campaign_id = :campaign_id',
            [':campaign_id' => $this->campaign->campaign_id])
        ->fetchAll();
    }

    public function prepareCustomVariables(){
        foreach($this->customVariables as $customVariable){
            switch($customVariable->variable_type){
                case 'uuid':
                    $value = strtoupper(uniqid($customVariable->variable_values));
                    break;
                case 'random':
                    $parts = explode('|',$customVariable->variable_values);
                    $start = 0;
                    $end = $parts[0];
                    $prefix = '';
                    $format = false;
                    switch(count($parts)){
                        case 4:
                            $start = $parts[0];
                            $end = $parts[1];
                            $prefix = $parts[2];
                            $format = true;
                            break;
                        case 3:
                            $start = $parts[0];
                            $end = $parts[1];
                            $prefix = $parts[2];
                            break;
                        case 2:
                            $start = $parts[0];
                            $end = $parts[1];
                            break;
                        case 1;
                            $end = $parts[0];
                            break;
                        default:
                            $end = 999;
                    }
                    $value = rand($start,$end);
                    $value = $prefix.($format ? number_format($value,2) : $value);
                    break;
                default:
                    $value = $customVariable->variable_values;
            }
            $this->variables["::$customVariable->variable_name"] = $value;
        }
    }

    public function prepareVariables(){
        foreach($this->campaign as $key => $value){
            $this->variables["::$key"] = $value;
        }
        $this->prepareCustomVariables();
    }

    public function setLead($lead){
        $this->lead = $lead;
        $this->message = [];
        foreach($this->lead as $key => $value){
            $this->variables["::$key"] = $value;
        }
    }

    public function setCustomVariable($key, $value){
        $this->variables["::$key"] = $value;
    }

    public function fetchLineTemplate($templateType, $campaignSpecificOnly = false){
            return $this->database->query('SELECT * FROM email_campaign__message_line_templates 
            WHERE line_catergory = :template_type
                AND (line_campaign_id = :campaign_id
                OR (:campaing_specific AND line_campaign_id = 0))
            ORDER BY RAND() LIMIT 1;',
            [':template_type' =>$templateType, ':campaign_id' => $this->campaign->campaign_id, ':campaing_specific' => !$campaignSpecificOnly])
        ->fetch();
    }

    public function convertTemplateToText($templateType){
        $textTemplate = $this->fetchLineTemplate($templateType);
        $convertedObject = new \stdClass();
        if(!empty($textTemplate->line_text)){
            if($textTemplate->line_type != 'generic'){
                $convertedObject->text = str_replace(array_keys($this->variables),array_values($this->variables), $textTemplate->line_text);
            } else {
                $convertedObject->text = $textTemplate->line_text;
            }
            $convertedObject->type = $textTemplate->line_type;
        }
        return $convertedObject;
    }

    public function fetchFooter(){
        $footer = [];
        $keys = ['legal_disclaimer','content_accuracy_disclaimer','mail_receive_reason','unsubscribe_link'];
        foreach($keys as $key){
            $footer[$key] = $this->convertTemplateToText($key);
        }
        return $footer;
    }

    public function fetchBody(){
        $body = [];
        $keys = ['logo','salutation','opening_product_line','renewal_order_info','debit_line','cancel_line','question_query'];
        foreach($keys as $key){
            $body[$key] = $this->convertTemplateToText($key);
        }
        return $body;
    }

    public function fetchSubject(){
        return $this->convertTemplateToText('email_subject');
    }

    public function getVariables(){
        return $this->variables;
    }

    public function saveEmailContent($dummy = false){

        $templateName = 'base_email.html';
        $templateFolderPath = DRUPAL_ROOT.'/'.drupal_get_path('module','email_campaigner').'/src/API/Template';
        $templateLocation = $templateFolderPath.'/'.$templateName;
        $templateContent = file_get_contents($templateLocation);

        if(!$dummy){
            $message_id = $this->database->insert('email_campaign__message')
                ->fields([
                        'to_user'=>$this->lead->email,
                        'to_name'=>$this->lead->firstname.(!empty($this->lead->lastname) ? ' '.$this->lead->lastname : ''),
                        'from_user' => $this->campaign->campaign_email_address,
                        'body'=>json_encode([]),
                        'html_template' => $templateName
                    ])
                ->execute();
        } else {
            $message_id = -1;
        }

        if(!empty($message_id)){
            $this->message['message_id'] = $message_id;

            $emailTracker = new EmailTracker($message_id, $this->campaign->campaign_id);
            $emailTracker->generateImageId();
            $trackingImageId = $emailTracker->getImageId();
            $trackingImage = $trackingImageId. '.png';
            $trackerImageLink = rtrim($this->campaign->tracking_website,"/")."/".'pixie/'.$trackingImage;


            $bodys = $this->fetchBody();
            $footers = $this->fetchFooter();

            $jsonContent = json_encode([
                'body' => $bodys,
                'footer' => $footers
            ]);

            $bodyHTML = [];
            foreach($bodys as $body){
                // $footerHTML[] = new FormattableMarkup("<div>@content</div>",['@content'=>$footer->text]);
                $bodyHTML[] = '<tr style="margin: 0;padding: 0;font-family: &quot;Helvetica Neue&quot;, &quot;Helvetica&quot;, Helvetica, Arial, sans-serif;box-sizing: border-box;font-size: 14px;">
                <td class="content-block" style="margin: 0;padding: 0 0 20px;font-family: &quot;Helvetica Neue&quot;, &quot;Helvetica&quot;, Helvetica, Arial, sans-serif;box-sizing: border-box;font-size: 14px;vertical-align: top;">
                    '.$body->text.'
                </td>
            </tr>';
            }

            $footerHTML = [];
            foreach($footers as $footer){
                $footerHTML[] = "<div>$footer->text</div>";
            }


            $htmlContent = str_replace(['::body','::footer','::tracker_image'],
                [
                    implode('',$bodyHTML), 
                    "<div style=\"font-size:10px;\">".implode('',$footerHTML)."</div>",
                    "<img src='$trackerImageLink' alt='Image Pixie'/>"
                ],$templateContent);
            
            $content = ['html' => $htmlContent, 'json' => $jsonContent];
            $subject = $this->fetchSubject();
            $subjectContent = $subject->text;

            $this->database->merge('email_campaign__message')
            ->key(['message_id'=>$message_id])
            ->fields([
                    'to_user'=>$this->lead->email,
                    'from_user' => $this->campaign->campaign_email_address,
                    'body'=>json_encode($content['json']),
                    'subject' => $subjectContent,
                    'tracking_link' => $trackingImageId,
                    'html_template' => $templateName
                ])
            ->execute();

            $content['subject'] = $subjectContent;
            $content['dummy'] = $dummy;
            $content['message_id'] = $message_id;
            $content['lead_id'] = $this->lead->lead_id;
            $content['campaign_id'] = $this->campaign->campaign_id;

            return $content;
        }

    }

}