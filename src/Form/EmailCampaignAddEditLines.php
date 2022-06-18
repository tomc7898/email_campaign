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
use Drupal\hexutils\FormElements\DrupalInputElements;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpFoundation\RedirectResponse;
use League\Csv\Reader;


class EmailCampaignAddEditLines extends HexForm
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

            $campaignVariables = $this->dbQuery('SELECT * FROM `email_campaign__message_line_templates` where line_campaign_id = :campaign_id',[':campaign_id'=> $campaign_id])->fetchAll();

            if(!empty($campaign->campaign_id)){

                $storage['campaign_id'] = $campaign->campaign_id;
                $storage['line_count'] = !empty($storage['line_count']) ? $storage['line_count'] : count($campaignVariables);
                $container['line_templates'] = ['#tree'=>true];

                for($i=0; $i < $storage['line_count']; $i++){
                    $default_value = $campaignVariables[$i];

                    if(!empty($default_value->line_campaign_id)){
                        $container['line_templates'][$i]['line_campaign_id'] = array(
                            '#type' => 'hidden',
                            '#value' => $default_value->line_campaign_id,
                        );
                        $container['line_templates'][$i]['line_id'] = array(
                            '#type' => 'hidden',
                            '#value' => $default_value->line_id,
                        );
                    } else {
                        $container['line_templates'][$i]['new_entry'] = array(
                            '#type' => 'hidden',
                            '#value' => true,
                        );
                    }

                    DrupalFormTextfield::initiate()->title('Line Category')->required()->default_value($default_value->line_catergory)
                        ->generate($container['line_templates'][$i]['line_catergory']);

                    DrupalSelectElements::initiate()->title('Line Type')->required()->default_value($default_value->line_type)
                        ->options(['personalized'=>'Personalized','template'=>'Template','html_template'=>'Html Template','generic'=>'Generic'])
                        ->generate($container['line_templates'][$i]['line_type']);

                    DrupalSelectElements::initiate()->title('Line Position')->required()->default_value($default_value->line_position)
                        ->options(['prefix'=>'Prefix','suffix'=>'Suffix','none'=>'None'])
                        ->generate($container['line_templates'][$i]['line_position']);

                    // if(!empty($default_value->line_text)){
                    //     $container['line_templates'][$i]['variable_name']['#attributes']['readonly'] = 'readonly';
                    //     $container['line_templates'][$i]['variable_type']['#disabled'] = true;
                    // }  

                    DrupalFormTextfield::initiate()->title('Line Text')->required()->default_value($default_value->line_text)
                        ->generate($container['line_templates'][$i]['line_text']);
                    DrupalFormDivider::initiate()->generate($container['line_templates'][$i]);
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
        $storage['line_count'] = !empty($storage['line_count']) ? $storage['line_count'] : 0;
        $storage['line_count']++;
        $form_state->setStorage($storage);
        $this->setMessage('Add New Variable Fields!!');
        $form_state->setRebuild();
    }

    public function submitForm(array &$form, FormStateInterface $form_state){
        $values = $form_state->getValues(); 
        $storage = $form_state->getStorage();

        $this->setMessage($values);
        if(!empty($values['line_templates'])){
            foreach($values['line_templates'] as $line_templates){
                if(!empty($line_templates['new_entry'])){
                    unset($line_templates['new_entry']);
                    $line_templates['line_campaign_id'] = $storage['campaign_id'];
                    $this->dbInsert('email_campaign__message_line_templates')->fields($line_templates)->execute();
                } else {
                    $dbKeys = array_intersect_key($line_templates, array_flip(['line_id','line_campaign_id']));
                    $this->database->merge('email_campaign__message_line_templates')->keys($dbKeys)->fields($line_templates)->execute();
                }
            }
        }
        $form_state->setRebuild();
    }

}


