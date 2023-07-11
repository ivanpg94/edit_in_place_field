<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class EditInPlaceReferenceWithParentForm.
 *
 * @package Drupal\edit_in_place_field\Form
 */
class EditInPlaceReferenceWithParentForm extends EditInPlaceFieldReferenceForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_in_place_reference_with_parent_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $data = []) {
    $form = parent::buildForm($form, $form_state, $data);
    $form['parent_ids'] = [
      '#type' => 'hidden',
      '#value' => implode(', ', array_keys($data['choice_lists'])),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInPlaceField(array $data): array {
    $choice_fields = [];
    $multiple = ($data['cardinality'] !== 1);
    $type = 'select';
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('select2')) {
      $type = 'select2';
    }
    foreach($data['choice_lists'] as $parent_id => $choices) {
      $choice_fields['in_place_field'.$parent_id] = [
        '#title' => isset($data['parent_labels'][$parent_id])?$data['parent_labels'][$parent_id]:'',
        '#type' => $type,
        '#options' => $choices,
        '#value' => isset($data['selected'][$parent_id])?$data['selected'][$parent_id]['ids']:[],
        '#name' => 'in_place_field'.$parent_id,
        '#attributes' => [
          'multiple' => $multiple,
          'class' => ['edit-in-place'],
        ],
        '#multiple' => $multiple
      ];
    }
    return $choice_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse(array $data): AjaxResponse {
    $field_name = $data[self::VAR_FIELD_NAME];
    $label_substitution = $data[self::VAR_LABEL_SUBSTITUTION];

    $selected_entities = [];

    // Try to load the entity to update.
    $error_response = new AjaxResponse();
    $entity = $this->loadEntity($data, $error_response);
    if (empty($entity)) {
      return $error_response;
    }

    try {
      // Save field data.
      $entity->{$field_name} = $data[self::VAR_FIELD_VALUES];
      $entity->save();

      // Retrieve data to be pass to the template.
      foreach($entity->{$field_name} as $field_data) {
        $child_entity = $field_data->entity;
        try {
          $child_entity = $child_entity->getTranslation($data[self::VAR_ENTITY_LANG_CODE]);
        }catch(\Exception $e){}
        $parent_id = $child_entity->get('parent')->target_id;
        $selected_entities[$parent_id]['ids'][] = $child_entity->id();
        $entity_label = $child_entity->label();
        if (!empty($label_substitution) && isset($child_entity->{$label_substitution}) && !empty($child_entity->{$label_substitution}->value)) {
          $entity_label = $child_entity->{$label_substitution}->value;
        }
        $selected_entities[$parent_id]['labels'][] = $entity_label;
        $selected_entities[$parent_id]['entities'][] = $child_entity;
      }
    }
    catch(EntityStorageException $e) {
      return $this->getResponse(parent::ERROR_DATA_CANNOT_BE_SAVED, ['error' => $e->getMessage()]);
    }

    // Render entities labels.
    $labels_html = $this->theme->render('edit_in_place_reference_with_parent_label', [
      'entities' => $selected_entities,
      'entity_type' => $data[self::VAR_ENTITY_TYPE],
      'field_name' => $field_name,
      'entity_id' => $data[self::VAR_ENTITY_ID],
      'lang_code' => $data[self::VAR_ENTITY_LANG_CODE],
    ]);

    // Return ajax response.
    return $this->reloadAndRebind($data[self::VAR_AJAX_REPLACE_ID], $labels_html);
  }

}
