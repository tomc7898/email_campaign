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

class EmailCampaignCreate extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId()
    {
        return 'email_campaign_create';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $campaign_id = null){
        $storage = &$form_state->getStorage();

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

        if(!empty($campaign_id)){
            $campaign = $this->dbQuery('SELECT * FROM `email_campaign` 
                    WHERE campaign_id = :campaign_id',
                    [':campaign_id'=>$campaign_id])
                ->fetch();

            if(!empty($campaign->campaign_id)){
                $storage['campaign_id'] = $campaign->campaign_id;
                $storage['operation'] = 'edit';
            }
        }
        DrupalFormWrapper::initiate()->wrapperId('campaign_container')
            ->generate($form['campaign_container']);
        $container = &$form['campaign_container'];

        DrupalFormTextfield::initiate()->title('Campaign Name')->required()->default_value(!empty($campaign->campaign_name) ? $campaign->campaign_name : '')
            ->description("<span class='english'>Please write a distinct email campaign name. This name will be not visible to Receiver.</span> \n\n कृपया एक विशिष्ट ईमेल अभियान नाम लिखें। यह नाम रिसीवर को दिखाई नहीं देगा।")
            ->generate($container['settings']['campaign_name']);

        DrupalFormTextfield::initiate()->title('Sender Name')->required()->default_value(!empty($campaign->sender_name) ? $campaign->sender_name : '')
            ->description("Please Email Sender name, this name will be visible to user. <br/> कृपया प्रेषक का नाम ईमेल करें, यह नाम उपयोगकर्ता को दिखाई देगा।")
            ->generate($container['settings']['sender_name']);
        DrupalFormTextfield::initiate()->title('Sending Email Address')->default_value(!empty($campaign->campaign_email_address) ? $campaign->campaign_email_address : '')
            ->required()->validate('::validateEmail')
            ->generate($container['settings']['campaign_email_address']);
       
        DrupalFormDivider::initiate()->generate($container);

        DrupalFormTextfield::initiate()->title('Website')->required()->default_value(!empty($campaign->website) ? $campaign->website : '')
            ->generate($container['settings-2']['website']);

        DrupalFormTextfield::initiate()->title('Unsubscribe Link')->required()->default_value(!empty($campaign->unsubscribe_email) ? $campaign->unsubscribe_email : '')
            ->generate($container['settings-2']['unsubscribe_email']);

        DrupalFormDivider::initiate()->generate($container);

        DrupalFormRadios::initiate()->title('Status')->required()->default_value(!empty($campaign->status) ? $campaign->status : '')
            ->options([1=>'Active',2=>'Suspended',4=>'Deleted'])
            ->generate($container['settings-3']['status']);

        $default_active_days = [];
        $options_active_days = [1=>'SUN',2=>'MON',4=>'TUE',8=>'WED',16=>'THU',32=>'FRI',64=>'SAT'];

        if(!empty($campaign->active_days)){
            $campaign_active_days = $campaign->active_days;
            $default_active_days = array_filter($options_active_days, function($k) use ($campaign_active_days) { 
                return $campaign_active_days & $k; 
            }, \ARRAY_FILTER_USE_KEY);
        }

        DrupalFormCheckBoxes::initiate()->title('Active Days')->required()
            ->prefix('<div class="form-check form-check-inline">')
            ->suffix('</div>')
            ->options($options_active_days)
            ->description("Please Email Sender name, this name will be visible to user. <br/> कृपया प्रेषक का नाम ईमेल करें, यह नाम उपयोगकर्ता को दिखाई देगा।")
            ->generate($container['settings-3']['active_days']);
            
        $container['settings-3']['active_days']['#default_value'] = array_keys($default_active_days);

        DrupalFormDivider::initiate()->generate($container);

        DrupalFormSubmit::initiate()
            ->value('Submit')
            ->generate($container['tools']['campaign_create_submit']);

        DrupalBootstrapFormGrid::initiate()
            ->row(['campaign_container','settings'])
                ->column(['campaign_container','settings','campaign_name'], '4')
                ->column(['campaign_container','settings','sender_name'], '4')
                ->column(['campaign_container','settings','campaign_email_address'], '4')
            ->row(['campaign_container','settings-2'])
                ->column(['campaign_container','settings-2','website'], '4')
                ->column(['campaign_container','settings-2','unsubscribe_email'], '4')
            ->row(['campaign_container','settings-3'])
                ->column(['campaign_container','settings-3','status'], '4')
                ->column(['campaign_container','settings-3','active_days'], '4')
            ->row(['campaign_container','tools'])
                ->column(['campaign_container','tools','campaign_create_submit'],'6')
            ->generate($form);

        $form_state->setStorage($storage);
        $form['#attached']['library'][] = 'email_campaigner/email-campaigner';
        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $storage = &$form_state->getStorage();

        $dbFields = ['campaign_name','sender_name','campaign_email_address',
            'website','unsubscribe_email','status','active_days'];
        $fields = [];
        $values['active_days'] = array_reduce($values['active_days'], function($a, $b) { return $a | $b; });
        foreach($dbFields as $dbField){
            $fields[$dbField] = $values[$dbField];
        }
        try{
            if(empty($storage['campaign_id'])){
                $this->dbInsert('email_campaign')->fields($fields)->execute();
                $this->setMessage('New Campaign created successfully.');
            } else {
                $this->getDBConnection()->update('email_campaign')
                    ->fields($fields)
                    ->condition('campaign_id', $storage['campaign_id'], '=')
                    ->execute();
                $this->setMessage('Campaign updated successfully.');
            }

        } catch (\Exception $e) {
            $this->setMessage('Unable to create new campaign. Please check all the required fields.');
        }
    }

}


