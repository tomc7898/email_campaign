<?php
namespace Drupal\email_campaigner\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

use Drupal\hexutils\Traits\FormValidators;
use Drupal\hexutils\FormUtils\HexForm;
use Drupal\hexutils\FormElements\DrupalFormTextfield;
use Drupal\hexutils\FormElements\DrupalFormSubmit;
use Drupal\hexutils\FormElements\DrupalSelectElements;
use Drupal\hexutils\FormElements\DrupalAjaxCallback;
use Drupal\hexutils\FormElements\DrupalBootstrapFormGrid;
use Drupal\hexutils\FormElements\DrupalFormCheckBoxes;
use Drupal\hexutils\FormElements\DrupalFormRadios;
use Drupal\hexutils\FormElements\DrupalFormWrapper;
use Drupal\hexutils\FormElements\DrupalFormDivider;
use Drupal\hexutils\FormElements\DrupalManagedFile;
use League\Csv\Reader;
use League\Csv\Statement;


class EmailCampaignLoadLead extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId()
    {
        return 'campaign_load_lead_container';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $fid = 0){

        $form = [
            '#prefix'=>'<div class="card"><div class="card-body">',
            '#suffix'=>'</div>
                <div class="card-arrow">
                    <div class="card-arrow-top-left"></div>
                    <div class="card-arrow-top-right"></div>
                    <div class="card-arrow-bottom-left"></div>
                    <div class="card-arrow-bottom-right"></div>
                </div>
            </div>'
        ];
        $form['#attached']['library'][] = 'email_campaigner/email-campaigner';
        
        DrupalFormWrapper::initiate()->wrapperId('campaign_load_lead_container')
            ->generate($form['campaign_load_lead_container']);
        $container = &$form['campaign_load_lead_container'];

        $uploadedFile = $this->dbQuery('SELECT * FROM file_managed f JOIN email_campaign__lead_files_upload_log lfl USING(fid) 
        LEFT JOIN email_campaign ec USING(campaign_id) where fid = :fid;',[':fid'=>$fid])->fetch();

        $values = $form_state->getValues();
        $storage = $form_state->getStorage();

 
        $formGrid = DrupalBootstrapFormGrid::initiate();

        $dbFields = [null=>'No Match','fullname' => 'Full Name', 'firstname' => 'First Name','lastname' => 'Last Name',
        'email'=>'Email','gender'=>'Gender','birth_month' => 'Birth Month',
        'dob'=> 'Date of Birth', 'age' => 'Age','estimated_income'=>'Estimated Income',
        'address' => 'Address','city' => 'City','state' => 'State','county' =>'County',
        'zip' => 'Zip','telephone' => 'Telephone'];

        $container['csv_field']['#tree'] = true;

        if(!empty($uploadedFile->uri)){

            $storage['fid'] = $fid;
            $storage['uri'] = $uploadedFile->uri;
            $storage['campaign_id'] = $uploadedFile->campaign_id;
    
            $csv = Reader::createFromPath($uploadedFile->uri, 'r');
            $headers = $csv->fetchOne();
            $sample_row = $csv->fetchOne(1);

            $storage['headers'] = $headers;
            $storage['sample_row'] = $sample_row;

            $itemCount = 0;
            foreach($headers as $index => $header){
                DrupalSelectElements::initiate()->title("$header [{$sample_row[$index]}]")->description($sample_row[$index])
                    ->options($dbFields)->generate($container['csv_field'][$header]);
            }
            $formGrid->generate($form);     
            DrupalFormDivider::initiate()->generate($container);

            DrupalFormSubmit::initiate()
                ->value('Load Lead')
                ->generate($container['tools']['load_lead_submit']);    
            $form_state->setStorage($storage);      
        }
        
        return $form;
    }

    public function containerUpdateCallback(array &$form, FormStateInterface $form_state) {
        return $form['campaign_load_lead_container'];
    }

    public function submitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $storage = $form_state->getStorage();

        if(!empty($values['csv_field'])){
            $values['csv_field'] = array_filter($values['csv_field']);
            $dbKeyMaps = array_flip($values['csv_field']);
            if(!empty($dbKeyMaps['email'])){
                $csv = Reader::createFromPath($storage['uri'], 'r');
                $csv->setHeaderOffset(0);
                $records = $csv->getRecords();

                $insertKeys = array_keys($dbKeyMaps);
                array_push($insertKeys,'campaign_id','fid');
              
                $dbValues = [];
                foreach ($records as $record) {
                    $dbValue = [
                        'campaign_id' =>  $storage['campaign_id'],
                        'fid' =>  $storage['fid']
                    ];
                    foreach($dbKeyMaps as $dbKey => $csvField){
                        $dbValue[$dbKey] = $record[$csvField];
                    }
                    $dbValues[] = $dbValue;
                }

                $transaction = $this->database->startTransaction();

                try {

                    $insertDB = $this->database->insert('email_campaign__leads')->fields($insertKeys);
                    foreach($dbValues as $dbValue){
                        $insertDB->values($dbValue);
                    }
                    $insertDB->execute();

                    $updateLeadFileFields = [
                        'load_status' => 3, 
                        'match_count' => count($dbKeyMaps), 
                        'match_fields' => json_encode($values['csv_field']), 
                        'load_count' => count($dbValues)
                    ];

                    $this->setMessage($updateLeadFileFields);

                    $this->database->update('email_campaign__lead_files_upload_log')
                    ->condition('fid', $storage['fid'])
                    ->fields($updateLeadFileFields)
                    ->execute();

                    
                    
                }
                catch (Exception $e) {
                    $transaction->rollBack();
                }
                  
                unset($transaction);

            } else {
                $this->setMessage('Match to email field not found !!');
            }
            
        } else {
            $this->setMessage('Match not completed !!');
        }

        // $form_state->setRebuild();
    }

}


