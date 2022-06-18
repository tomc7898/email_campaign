<?php
namespace Drupal\email_campaigner\Form;

use Drupal\Core\Form\FormStateInterface;

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
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailCampaignList extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId()
    {
        return 'email_campaign_list';
    }

    public function buildForm(array $form, FormStateInterface $form_state){
        \Drupal::service('page_cache_kill_switch')->trigger();
        DrupalFormWrapper::initiate()->wrapperId('campaign_list_container')
            ->generate($form['campaign_list_container']);

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

        $container = &$form['campaign_list_container'];
        $campaignList = $this->dbQuery('SELECT * FROM `email_campaign`')->fetchAll();
        $options_active_days = [1=>'SUN',2=>'MON',4=>'TUE',8=>'WED',16=>'THU',32=>'FRI',64=>'SAT'];

        $option_status = [1=>'Active',2=>'Suspended',4=>'Deleted'];
        $badge_status = [1=>'success',2=>'warning',4=>'danger'];

        $rows = [];

        foreach($campaignList as $campaign) {
            $default_active_days = [];
            if(!empty($campaign->active_days)){
                $campaign_active_days = $campaign->active_days;
                $default_active_days = array_filter($options_active_days, function($k) use ($campaign_active_days) { 
                    return $campaign_active_days & $k; 
                }, \ARRAY_FILTER_USE_KEY);
            }

            $rows[$campaign->campaign_id] = [
                'campaign_name' => new FormattableMarkup('
                    <div>@campaign_name</div>
                    <div><span class="badge bg-@badge_status">@campaign_status</span></div>',
                    ['@campaign_name' => $campaign->campaign_name,
                    '@badge_status' => $badge_status[$campaign->status],
                    '@campaign_status'=> "{$option_status[$campaign->status]} [{$campaign->status}]"]
                ), 
                'campaign_details' => new FormattableMarkup("
                        <div>@sender_name</div>
                        <div>@campaign_email_address</div>
                        <hr/>
                        <div>Active Days/সক্রিয় দিন/सक्रिय दिन</div>
                        <div>@campaign_active_days</div>
                        <div><a class='btn btn-sm btn-outline-yellow' href='/config/campaign/email/@campaign_id/variables'>Add/Edit Variables\n\rভেরিয়েবল যোগ/সম্পাদনা করুন\n\rचर जोड़ें/संपादित करें</a></div>
                        <div><a class='btn btn-sm btn-outline-yellow' href='/config/campaign/email/@campaign_id/template/line'>Add/Edit Lines</a></div>
                        <div><a class='btn btn-sm btn-outline-yellow' href='/config/campaign/email/campaign/@campaign_id/preview'>Preview Email</a></div>
                        <div><a class='btn btn-sm btn-outline-yellow' href='/config/campaign/email/lead/upload'>Load Customer Leads\n\rকাস্টমার লিড লোড করুন\n\rग्राहक लीड लोड करें</a></div>
                    ",
                    [
                    '@campaign_id' => $campaign->campaign_id,
                    '@sender_name' => $campaign->sender_name,
                    '@campaign_email_address' => $campaign->campaign_email_address,
                    '@campaign_active_days' => implode(',',$default_active_days)]
                ),   
            ];
        }

        $container ['campaign_list_table'] = array(
            '#type' => 'tableselect',
            '#header' => [
                'campaign_name'=>t('Campaign Name/ক্যাম্পেইনের নাম/अभियान का नाम'),
                'campaign_details'=>t('Campaign Details/ক্যাম্পেইনের বিশদ বিবরণ/अभियान विवरण')
            ],
            '#multiple'=> false,
            '#options' => $rows
        );

        DrupalFormSubmit::initiate()
            ->value("Activate the selected Campaign")
            ->submit(['::activateCampaignSubmitForm'])
            ->generate($container['tools']['campaign_activate_submit']);
        DrupalFormSubmit::initiate()
            ->value("Suspend the selected Campaign")
            ->submit(['::suspendCampaignSubmitForm'])
            ->generate($container['tools']['campaign_suspend_submit']);
        DrupalFormSubmit::initiate()
            ->value("Edit the selected Campaign")
            ->submit(['::editCampaignSubmitForm'])
            ->generate($container['tools']['campaign_edit_submit']);
        
        $form['#attached']['library'][] = 'email_campaigner/email-campaigner';
        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state){}

    public function editCampaignSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        if(!empty($values['campaign_list_table'])){
            $response = new RedirectResponse("/config/campaign/email/edit/{$values['campaign_list_table']}", 302);
            $response->send();
        }
    }

    public function activateCampaignSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $this->changeCampaignState($values, 1);
    }

    public function suspendCampaignSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $this->changeCampaignState($values, 2);
    }

    public function sendSampleEmailSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $this->changeCampaignState($values, 1);
    }

    public function changeCampaignState($values, $status){
        try{
            if(!empty($values['campaign_list_table'])){
                $this->getDBConnection()->update('email_campaign')
                    ->fields(['status'=>$status])
                    ->condition('campaign_id', $values['campaign_list_table'], '=')
                    ->execute();
                $this->setMessage('Campaign updated successfully.');
            }
        } catch (\Exception $e) {
            $this->setMessage('Unable to change status ofcampaign. Please try again later.');
        }
    }

}


