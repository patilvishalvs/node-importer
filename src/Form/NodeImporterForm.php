<?php

/**
 * @file
 * Contains \Drupal\quiz_import\Form\QuizImportForm.
 */

namespace Drupal\node_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class NodeImporterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'resume_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $node_types = \Drupal\node\Entity\NodeType::loadMultiple();
    // If you need to display them in a drop down:
    $options = [];
    foreach ($node_types as $node_type) {
      $options[$node_type->id()] = $node_type->label();
    }
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => t('File'),
      '#required' => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];
    $form['node_bundle'] = [
      '#type' => 'select',
      '#title' => t('Content type'),
      '#required' => TRUE,
      '#options' => $options,
      '#ajax' => [
        'callback' => '::fieldMapper',
        'wrapper' => 'fields-mapper-table',
      ],
    ];
    $form['fields_mapper'] = [
      '#prefix' => '<div id="fields-mapper-table">',
      '#suffix' => '</div>',
      '#type' => 'table',
      '#tree' => true,
      '#caption' => $this->t('Fields mapping'),
      '#header' => array($this->t('CSV header'), $this->t('Field'), $this->t('Separator')),
    ];
    $csv = $form_state->getValue('file');
    if (!empty($csv)) {
      $entity_type = 'node';
      $bundle = $form_state->getValue('node_bundle');
      $fields = [
        'title' => t('Title')
      ];
      foreach (\Drupal::entityManager()->getFieldDefinitions($entity_type, $bundle) as $field_name => $field_definition) {
        if (!empty($field_definition->getTargetBundle())) {
          $target_type = $field_definition->getSetting('target_type');
          $settings = $field_definition->getSettings();
          $field = $field_name;
          if(!empty($target_type)){
            $field .= '|'.$target_type;
            if(!empty($settings['handler_settings']['target_bundles'])){
              $bundle = array_keys($settings['handler_settings']['target_bundles']);
              $field .= '|'.$bundle[0];
            }
          }
          $fields[$field] = $field_definition->getLabel() . ' [' . $field_name . ']';
        }
      }
      $csv = $form_state->getValue('file');
      $file = File::load($csv[0]);
      $filepath = \Drupal::service('file_system')->realpath($file->getFileUri());
      $fp = fopen($filepath, "r");
      $headers = [];
      while (!feof($fp)) {
        $headers = fgetcsv($fp);
        break;
      }
      fclose($fp);
      foreach ($headers as $key => $val) {
        $form['fields_mapper'][$key]['csv_col'] = [
          '#markup' => $val,
        ];
        $form['fields_mapper'][$key]['field'] = [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#title_display' => 'invisible',
          '#options' => $fields,
          '#empty_option' => t('--None--')
        ];
        $form['fields_mapper'][$key]['separator'] = [
          '#type' => 'textfield',
          '#title' => t('Separator'),
          '#title_display' => 'invisible',
          '#size' => 5,
        ];
      }
    }
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function fieldMapper(array &$form, FormStateInterface $form_state) {
    return $form['fields_mapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fields_mapper = $form_state->getValue('fields_mapper');
    $csv = $form_state->getValue('file');
    $node_bundle = $form_state->getValue('node_bundle');
    $file = File::load($csv[0]);
    $batch = [
      'title' => t('Creating question...'),
      'finished' => '\Drupal\node_importer\NodeImporter::createNodeFinishedCallback',
      'operations' => []
    ];
    $filepath = \Drupal::service('file_system')->realpath($file->getFileUri());
    $fp = fopen($filepath, "r");
    $row = 0;
    while (!feof($fp)) {
      $data = fgetcsv($fp);
      if ($row == 0) {
        $row = 1;
        continue;
      }
      $batch['operations'][] = ['\Drupal\node_importer\NodeImporter::createNode', [$data, $fields_mapper, $node_bundle]];
    }
    fclose($fp);
    batch_set($batch);
  }

}
