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
use Drupal\hexutils\FormElements\DrupalFormCheckBox;
use Drupal\hexutils\FormElements\DrupalFormRadios;
use Drupal\hexutils\FormElements\DrupalFormWrapper;
use Drupal\hexutils\FormElements\DrupalFormDivider;

class EmailAccountAdd extends HexForm{

    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId() {
        return 'email_account_add';
    }
    
    public function buildForm(array $form, FormStateInterface $form_state) {

        DrupalFormWrapper::initiate()->wrapperId('email_account_add_container')
            ->generate($form['email_account_add_container']);

        $container = &$form['email_account_add_container'];

        DrupalFormTextfield::initiate()->title('Your username')->required()
            ->generate($container['username']);
        DrupalFormTextfield::initiate()->title('Your password')->required()
            ->generate($container['password']);
        DrupalFormTextfield::initiate()->title('Email Address (Keep this field empty if username and email id is same)')
            ->generate($container['email']);

        $emailTypeProvider = [
            'gmail'=>'Gmail',
            'yahoo'=>'Yahoo',
            'yandex'=>'Yandex',
            'aol'=>'AOL',
            'hotmail'=>'Hotmail',
            'outlook'=>'Outlook.com',
            'msn'=>'MSN',
            'rediff'=>'Rediff Mail'];
        
        DrupalSelectElements::initiate()->title('Email Provider')->required()
            ->options($emailTypeProvider)->generate($container['provider']);

        DrupalFormCheckBox::initiate()->title('Active?')
            ->option('Active?')->generate($container['active']);

        DrupalFormCheckBox::initiate()->title('Replace From?')
            ->option('Replace From?')->generate($container['replace_from']);

        DrupalFormCheckBox::initiate()->title('Replace ReplyTo?')
            ->option('Replace ReplyTo?')->generate($container['replace_replyto']);

        DrupalFormTextfield::initiate()->title('Max mail per batch')->required()
            ->default_value(5)
            ->generate($container['max_mail_per_batch']);

        DrupalFormTextfield::initiate()->title('Daily Max Email Count')->required()
            ->default_value(80)
            ->generate($container['max_daily_email_count']);

        DrupalFormTextfield::initiate()->title('Minimum Time Difference between batches (in Minutes)')->required()
            ->default_value(30)
            ->generate($container['min_time_diff']);

        DrupalFormSubmit::initiate()
            ->value('Add Email Account')
            ->generate($container['tools']['add_email_submit']);

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();

        $values = $form_state->getValues();
        $dbFields = ['username','password','email','provider',
            'active','replace_from','replace_replyto','max_mail_per_batch',
            'max_daily_email_count','min_time_diff'
        ];
        $values['email'] = empty($values['email']) ? $values['username'] : $values['email'];
        $fields = [];
        
        foreach($dbFields as $dbField){
            $fields[$dbField] = $values[$dbField];
        }
        try{
            $this->dbInsert('email_campaign__accounts')->fields($fields)->execute();
            $this->setMessage('New Email Account added successfully.');
        } catch (\Exception $e) {
            $this->setMessage($e->getMessage());
            $this->setMessage('Unable to add new Email Account. Please check all the required fields.');
        }
    }

}