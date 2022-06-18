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


class EmailCampaignAddEditVariables extends HexForm
{
    use FormValidators;

    public function __construct(){
        $this->prepareVariables();
    }

    public function getFormId()
    {
        return 'campaign_upload_lead_container';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $campaign_id = null){
        $form['#attached']['library'][] = 'email_campaigner/email-campaigner';
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

        DrupalFormWrapper::initiate()->wrapperId('campaign_add_edit_variables_container')
            ->generate($form['campaign_add_edit_variables_container']);
        $container = &$form['campaign_add_edit_variables_container'];



        if(!empty($campaign_id)){
            $storage = $form_state->getStorage();
            $drupalAjaxCallback = new DrupalAjaxCallback('::containerUpdateCallback', 'campaign_add_edit_variables_container');
            $campaign = $this->dbQuery('SELECT * FROM `email_campaign` 
                    WHERE campaign_id = :campaign_id',
                    [':campaign_id'=>$campaign_id])
                ->fetch();

            $campaignVariables = $this->dbQuery('SELECT * FROM `email_campaign__message_variables` where campaign_id = :campaign_id',[':campaign_id'=> $campaign_id])->fetchAll();

            if(!empty($campaign->campaign_id)){

                $storage['campaign_id'] = $campaign->campaign_id;
                $storage['line_count'] = !empty($storage['line_count']) ? $storage['line_count'] : count($campaignVariables);
                $container['variables'] = ['#tree'=>true];

                for($i=0; $i < $storage['line_count']; $i++){
                    $default_value = $campaignVariables[$i];

                    DrupalFormTextfield::initiate()->title('Variable Name')->required()->default_value($default_value->variable_name)
                        ->generate($container['variables'][$i]['variable_name']);

                    DrupalSelectElements::initiate()->title('Variable Type')->required()->default_value($default_value->variable_type)
                        ->options(['number'=>'Number','text'=>'Text','random'=>'Random from List'])
                        ->generate($container['variables'][$i]['variable_type']);

                    if(!empty($default_value->variable_name)){
                        $container['variables'][$i]['variable_name']['#attributes']['readonly'] = 'readonly';
                        $container['variables'][$i]['variable_type']['#disabled'] = true;
                    }  

                    DrupalFormTextfield::initiate()->title('Variable Values')->required()->default_value($default_value->variable_values)
                        ->generate($container['variables'][$i]['variable_values']);
                    DrupalFormDivider::initiate()->generate($container['variables'][$i]);
                }

                DrupalFormSubmit::initiate()
                    ->value('Add Variables')
                    ->ajax($drupalAjaxCallback)
                    ->submit(['::addEditVariablesSubmitForm'])
                    ->generate($container['tools']['add_edit_variables_line_submit']);

                DrupalFormDivider::initiate()->generate($container['tools']);

                DrupalFormSubmit::initiate()
                    ->value('Save Variables')
                    // ->ajax($drupalAjaxCallback)
                    ->generate($container['tools']['save_variables_submit']);
                
            }

            $form_state->setStorage($storage);
        }
        
        return $form;
    }

    public function containerUpdateCallback(array &$form, FormStateInterface $form_state) {
        return $form['campaign_add_edit_variables_container'];
    }

    public function addEditVariablesSubmitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues();
        $storage = $form_state->getStorage();
        $storage['line_count'] = !empty($storage['line_count']) ? $storage['line_count'] : 1;
        $storage['line_count']++;
        $form_state->setStorage($storage);
        $this->setMessage('Add New Variable Fields!!');
        $form_state->setRebuild();
    }

    public function submitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues(); 
        $storage = $form_state->getStorage();

        if(!empty($values['variables'])){
            foreach($values['variables'] as $variable){
                $variable['campaign_id'] = $storage['campaign_id'];
                $dbKeys = array_intersect_key($variable, array_flip(['variable_name','campaign_id']));
                $this->database->merge('email_campaign__message_variables')->keys($dbKeys)->fields($variable)->execute();
            }
        }
        $form_state->setRebuild();
    }

}


