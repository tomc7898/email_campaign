<?php 

namespace Drupal\email_campaigner\API\Email;

class EmailTracker{
    public function __construct($messageId, $campaignId){
        $this->database = \Drupal::database();
        $this->messageId = $messageId;
        $this->campaignId = $campaignId;
    }

    public function getImageId()
    {
        $this->generateImageId();
        return $this->imageId;
    }

    public function generateImageId()
    {
        $imageInformation = [
            'messageId' => $this->messageId,
            'campaignId' => $this->campaignId,
            'date' => date('Y-m-d H:i:s')
        ];
        $this->imageId = base64url_encode(json_encode($imageInformation));
    }

    public function decodeImageId()
    {
        if (!empty($this->imageId)) {
            $mailPartJSON = base64url_decode($this->imageId);
            $imageInformation = json_decode($mailPartJSON, 1);
            $this->messageId = $imageInformation['messageId'];
            $this->campaignId = isset($imageInformation['campaignId']) ? $imageInformation['campaignId'] : 0;
            $this->date = isset($imageInformation['date']) ? $imageInformation['date'] : '2022-04-24 12:00:00';

            if(!empty($this->messageId) && !empty($this->campaignId)){
                $this->database
                ->insert('email_campaign__message_view_log')
                ->fields([
                    'message_id' => $this->messageId,
                    'campaign_id' => $this->campaignId,
                    'sent_date' => $this->date,
                    'ip' => get_client_ip(),
                    'referrer' => json_encode([])
                ])
                ->execute();
            }
        }
    }

    public function setImageId($image)
    {
        $imageParts = pathinfo($image);
        if ($imageParts['extension'] == 'png') {
            $this->imageId = $imageParts['filename'];
            $this->decodeImageId();
        }
    }

    public function serveImage()
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
    }
}