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
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;
use League\Csv\Reader;


class EmailCampaignUploadLeadFile extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId()
    {
        return 'campaign_upload_lead_container';
    }

    public function buildForm(array $form, FormStateInterface $form_state){

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

        

        $campaignList = $this->dbQuery('SELECT campaign_id, campaign_name FROM `email_campaign`')->fetchAllKeyed();
        $uploadedFileList = $this->dbQuery('SELECT * FROM file_managed f JOIN email_campaign__lead_files_upload_log lfl  USING(fid) 
        LEFT JOIN email_campaign ec USING(campaign_id) ORDER BY lfl.upload_time;')->fetchAll();

        $dir = 'public://leads';
        $fileUploader = new DrupalManagedFile();
        $fileUploader
            ->prefix('<div id="pdf_file">')
            ->suffix('</div>')
            ->description('Valid file extensions for document uploads: CSV. Maximum size is  2MB.')
            ->file_validate_extensions('csv txt')
            ->file_validate_size('20480000')
            // ->multiple()
            ->upload_location($dir)
            ->generate($container['fileupload']['uploaded_file']);

        // array_unshift($campaignList , 'Generic');
        DrupalSelectElements::initiate()->title('Camapaign')->required()->options($campaignList)->generate($container['fileupload']['campaign_id']);

        DrupalFormDivider::initiate()->generate($container);

        DrupalFormSubmit::initiate()
            ->value('Upload Leads')
            ->generate($container['tools']['load_lead_submit']);

        $load_status = [1=>'Uploaded',2=>'Lead Load Completed',4=>'Partial Lead Loaded',5=>'File not compatible'];

        $rows = [];
        foreach($uploadedFileList as $fileDetails) {
            $rows[$fileDetails->fid] = [
                'campaign_name' => new FormattableMarkup('
                    <div>@filename (@filetype)</div>
                    <div>@uri</div>',
                    ['@filename' => $fileDetails->filename,'@uri'=>$fileDetails->uri,'@filetype'=>$fileDetails->filemime]
                ), 
                'campaign_details' => new FormattableMarkup('
                        <div>@campaign_name</div>
                        <div>@timestamp</div>
                        <hr/>
                        <div>@load_status</div>
                        <div><a class="btn btn-sm btn-outline-yellow" href="/config/campaign/email/lead/load/@fid">Load Leads</a></div>
                    ',
                    ['@campaign_name'=> !empty($fileDetails->campaign_name) ? $fileDetails->campaign_name : 'Generic','@fid'=>$fileDetails->fid,
                    '@timestamp' => $fileDetails->upload_time,'@load_status'=>$load_status[$fileDetails->load_status]]
                ) 
            ];
        }

        DrupalFormDivider::initiate()->generate($container);
        $container ['uploaded_file_list_table'] = array(
            '#type' => 'tableselect',
            '#header' => [
                'campaign_name'=>t('Campaign Name'),
                'campaign_details'=>t('Campaign Details')
            ],
            '#multiple'=> false,
            '#options' => $rows
        );

        return $form;
    }

    public function containerUpdateCallback(array &$form, FormStateInterface $form_state) {
        return $form['campaign_load_lead_container'];
    }

    public function submitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();

        

        $file = \Drupal\file\Entity\File::load($values['uploaded_file'][0]);
        if(!empty( $values['uploaded_file'][0])){
            $fid = $values['uploaded_file'][0];
            $file = \Drupal\file\Entity\File::load($fid);
  
            $file->setPermanent();
            $file->save();
            $fields = [
                'fid'=> $fid,
                'campaign_id'=> !empty($values['campaign_id']) ? $values['campaign_id'] : 0
            ];

            try{
                $this->dbInsert('email_campaign__lead_files_upload_log')->fields($fields)->execute();
            } catch (\Exception $e) {
                $this->setMessage($e->getMessage());
            }

        //     return $file;
        }
    }

}


