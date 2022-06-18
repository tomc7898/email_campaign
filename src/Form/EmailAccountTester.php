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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class EmailAccountTester extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    private $emailSettings = [
        'gmail' => [
            'smtp_server'=>'smtp.gmail.com',
            'port'=>'587',
            'protocol'=>'tls'
        ],
        'yandex' => [
            'smtp_server'=>'smtp.yandex.com',
            'port'=>'465',
            'protocol'=>'ssl'
        ],
        'outlook' => [
            'smtp_server'=>'smtp.office365.com',
            'port'=>'587',
            'protocol'=>'tls'
        ],
        'rediff' => [
            'smtp_server'=>'smtp.rediffmail.com',
            'port'=>'25',
            'encryption'=> 'false'
        ],
        'yahoo' => [
            'smtp_server'=>'smtp.mail.yahoo.com',
            'port'=>'465',
            'protocol'=>'ssl'
        ],
        'aol' => [
            'smtp_server'=>'smtp.aol.com',
            'port'=>'465',
            'protocol'=>'ssl'
        ],
    ];

  public function getFormId() {
    return 'email_account_tester';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $userInputs = $form_state->getUserInput();
    $smtpSettings = !empty($userInputs['smtp_settings']) ? $userInputs['smtp_settings'] : '';
    $defaultValues = !empty($this->emailSettings[$smtpSettings]) ? $this->emailSettings[$smtpSettings] : [];

    // $this->setMessage($defaultValues);

    $drupalAjaxCallback = new DrupalAjaxCallback('::containerUpdateCallback', 'email_account_test_container');

    DrupalFormWrapper::initiate()->wrapperId('email_account_test_container')
        ->generate($form['email_account_test_container']);
    $container = &$form['email_account_test_container'];

    $debugType = [
        SMTP::DEBUG_OFF =>'Off',
        SMTP::DEBUG_CLIENT =>'Client Messages',
        SMTP::DEBUG_SERVER =>'Client and Server Messages'
    ];

    DrupalSelectElements::initiate()->title('Debug Type')
        ->options($debugType)->generate($container['debug_type']);

    DrupalFormTextfield::initiate()->title('Your username')->required()
        ->ajax($drupalAjaxCallback)->generate($container['username']);
    DrupalFormTextfield::initiate()->title('Your password')->required()
        ->generate($container['password']);

    $emailTypeProvider = [
        'gmail'=>'Gmail',
        'yahoo'=>'Yahoo',
        'yandex'=>'Yandex',
        'aol'=>'AOL',
        'hotmail'=>'Hotmail',
        'outlook'=>'Outlook.com',
        'msn'=>'MSN',
        'rediff'=>'Rediff Mail'];

    DrupalSelectElements::initiate()->title('Email Providers')
        ->ajax($drupalAjaxCallback)->required()
        ->options($emailTypeProvider)->generate($container['smtp_settings']);

    DrupalFormTextfield::initiate()->title('SMTP Server')->required()
        ->default_value(!empty($defaultValues['smtp_server']) ? $defaultValues['smtp_server'] : '')
        ->generate($container['smtp_server']);

    DrupalFormTextfield::initiate()->title('Port')->required()
        ->default_value(!empty($defaultValues['port']) ? $defaultValues['port'] : '')
        ->generate($container['port']);

    DrupalFormCheckBox::initiate()->title('Enable Encryption?')
        ->option('Enable Encryption?')->generate($container['encryption']);

    DrupalSelectElements::initiate()->title('Protocol')
        // ->ajax($drupalAjaxCallback)
        ->required()
        ->default_value(!empty($defaultValues['protocol']) ? $defaultValues['protocol'] : false)
        ->options([
            'tls' =>'TLS',
            'ssl' =>'SSL',
            false => 'No Encryption'])
        ->generate($container['protocol']);

    DrupalFormTextfield::initiate()->title('From Email Address')
        ->generate($container['from_email']);

    DrupalFormTextfield::initiate()->title('Reply to Address')
        ->generate($container['reply_to']);

    DrupalFormTextfield::initiate()->title('To Email Address')
        ->generate($container['to_email']);

    DrupalFormTextfield::initiate()->title('Subject')
        ->default_value('This is a Test Subject')
        ->generate($container['subject']);

    DrupalFormTextfield::initiate()->title('Body')
        ->default_value('This is a Test Body')
        ->generate($container['body']);

    DrupalFormSubmit::initiate()
        ->value('Test Email Account')
        ->generate($container['tools']['test_email_submit']);

    return $form;
  }

  public function containerUpdateCallback(array &$form, FormStateInterface $form_state) {

        // $form['container']['smtp_server']['#value'] = '';
        $smtpSettings = $form_state->getValue('smtp_settings');
        $defaultValues = !empty($this->emailSettings[$smtpSettings]) ? $this->emailSettings[$smtpSettings] : [];
        $form['email_account_test_container']['smtp_server']['#value'] = !empty($defaultValues['smtp_server']) ? $defaultValues['smtp_server'] : '';
        $form['email_account_test_container']['protocol']['#value'] = !empty($defaultValues['protocol']) ? $defaultValues['protocol'] : false;
        $form['email_account_test_container']['port']['#value'] = !empty($defaultValues['port']) ? $defaultValues['port'] : '';
        $form['email_account_test_container']['encryption']['#checked'] = !empty($defaultValues['encryption']) && $defaultValues['encryption'] == 'false' ? false : true;
        $username = $form_state->getValue('username');
        $form['email_account_test_container']['from_email']['#value'] = !empty($username) ? $username : '';
        $form['email_account_test_container']['reply_to']['#value'] = !empty($username) ? $username : '';
        return $form['email_account_test_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid_email_regex = "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,10})$^";
    $fields = ['username','password','smtp_server','port','to_email','subject','body'];
    $values = $form_state->getValues();
    foreach($fields as $fieldKey){
        if(empty($values[$fieldKey])){
            $form_state->setErrorByName($fieldKey, $this->t('The '.$fieldKey.' field is empty.'));
        }
    }

    if(!preg_match($valid_email_regex, $values['username'])){
        if(!preg_match($valid_email_regex, $values['from_email'])){
            $form_state->setErrorByName($fieldKey, $this->t('Please enter valid from email address.'));
        }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // \Drupal::messenger()->addMessage(print_r($values,1));

    $mail = new PHPMailer;
    $mail->isSMTP();
    // SMTP::DEBUG_OFF = off (for production use)
    // SMTP::DEBUG_CLIENT = client messages
    // SMTP::DEBUG_SERVER = client and server messages
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Host = $values['smtp_server'];
    $mail->Port = $values['port'];

    if(!$values['encryption']){
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = false;
    } else {
        $mail->SMTPSecure = $values['protocol'];
    }
    // $mail->SMTPKeepAlive = true;
    $mail->SMTPAuth = true;

    $mail->Username = $values['username'];
    $mail->Password = $values['password'];

    $mail->setFrom($values['from_email']);
    $mail->addReplyTo($values['reply_to']);
    $mail->addAddress($values['to_email']);
    $mail->Subject = $values['subject'];
    $mail->Body = $values['body'];

    if (!$mail->send()) {
        \Drupal::messenger()->addError($mail->ErrorInfo);
    } else {
        \Drupal::messenger()->addMessage('Mail Sent Successfully');
    }
    $form_state->setRebuild(true);
    // $mail->send();
    // $this->messenger()->addStatus($this->t('Your phone number is @number', ['@number' => $form_state->getValue('phone_number')]));
  }

}