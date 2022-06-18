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
use Drupal\email_campaigner\API\Campaign\CreateSMTPConnection;


class EmailAccountList extends HexForm
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
        $accountList = $this->dbQuery('SELECT * FROM `email_campaign__accounts`')->fetchAll();
        $option_status = [1=>'Active',2=>'Suspended',4=>'Deleted'];
        $badge_status = [1=>'success',2=>'warning',4=>'danger'];

        $rows = [];

        foreach($accountList as $account) {
            // $default_active_days = [];
            // if(!empty($campaign->active_days)){
            //     $campaign_active_days = $campaign->active_days;
            //     $default_active_days = array_filter($options_active_days, function($k) use ($campaign_active_days) { 
            //         return $campaign_active_days & $k; 
            //     }, \ARRAY_FILTER_USE_KEY);
            // }

            $rows[$account->account_id] = [
                'username' => $account->username, 
                'email' => $account->email, 
                'provider' => $account->provider, 
                'active' => $account->active, 
                  
            ];
        }
        DrupalFormTextfield::initiate()->title('Test Email Address')
        ->generate($container['test_email_address']);

        $container ['email_list_table'] = array(
            '#type' => 'tableselect',
            '#header' => [
                'username' => 'Username', 
                'email' => 'Email', 
                'provider' => 'Provider', 
                'active' => 'Active', 
            ],
            '#multiple'=> false,
            '#options' => $rows
        );

        DrupalFormSubmit::initiate()
            ->value("Send Test Email")
            ->submit(['::sendSampleEmailSubmitForm'])
            ->generate($container['tools']['campaign_activate_submit']);
        DrupalFormSubmit::initiate()
            ->value("Activate the selected Account")
            ->submit(['::activateAccountSubmitForm'])
            ->generate($container['tools']['campaign_edit_submit']);
        DrupalFormSubmit::initiate()
            ->value("Suspend the selected Account")
            ->submit(['::suspendAccountSubmitForm'])
            ->generate($container['tools']['campaign_suspend_submit']);

        
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

    public function activateAccountSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $this->changeAccountState($values, 1);
    }

    public function suspendAccountSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $this->changeAccountState($values, 0);
    }

    public function sendSampleEmailSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        if(!empty($values['test_email_address'])){
            if(!empty($values['email_list_table'])){
                $account = $this->dbQuery('SELECT * FROM email_campaign__accounts where account_id = :account_id',[':account_id'=>$values['email_list_table']])->fetch();
                if(!empty($account->account_id)){
                    $smtpConnection = new CreateSMTPConnection($account);
                    $smtpConnection->createSMTPConnection();
                    $status = $smtpConnection->sendEmail($values['test_email_address'], 'Test User', null, null, 'Test Email', 'Test Email from Emailer System');
                    $this->setMessage($status['msg']);
                    if($status['status'] == 1){
                        $this->changeAccountState($values, 1);
                    }
                }
                
            }
        } else {
            $this->setMessage('Destination Email Id for Test Email is not set.');
        }
    }

    public function changeAccountState($values, $status){
        try{
            if(!empty($values['email_list_table'])){
                $this->getDBConnection()->update('email_campaign__accounts')
                    ->fields(['active'=>$status])
                    ->condition('account_id', $values['email_list_table'], '=')
                    ->execute();
                $this->setMessage('Account Status updated successfully.');
            }
        } catch (\Exception $e) {
            $this->setMessage('Unable to change status of Account. Please try again later.');
        }
    }

}


