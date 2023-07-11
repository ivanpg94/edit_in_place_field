<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Class EditInPlaceFieldReferenceForm.
 *
 * @package Drupal\edit_in_place_field\Form
 */
class EditInPlaceFieldReferenceForm extends EditInPlaceFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_in_place_field_reference_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getInPlaceField (array $data): array {
    $multiple = ($data['cardinality'] !== 1);
    $type = 'select';
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('select2')) {
      $type = 'select2';
    }
    $choice_field = [
      '#type' => $type,
      '#options' => $data['choice_list'],
      '#value' => $data['selected'],
      '#name' => $data[self::VAR_FIELD_NAME],
      '#attributes' => [
        'multiple' => $multiple,
        'class' => ['edit-in-place'],
      ],
      '#multiple' => $multiple,
    ];
    return $choice_field;
  }

  /**
   * Process a response from ajax request.
   *
   * @param $data
   *    Parameters needed to process the response
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *    Ajax response object
   */
  protected function processResponse(array $data): AjaxResponse {
    $field_name = $data[self::VAR_FIELD_NAME];
    $label_substitution = $data[self::VAR_LABEL_SUBSTITUTION];

    $labels = [];
    $selected_entities = [];

    // Try to load the entity to update.
    $error_response = new AjaxResponse();
    $entity = $this->loadEntity($data, $error_response);
    if (empty($entity)) {
      return $error_response;
    }

    // try to update the entity.
    try {
      $entity->{$field_name} = $data[self::VAR_FIELD_VALUES];
      $entity->save();

      foreach($entity->{$field_name} as $field_data) {
        $entity_field = $field_data->entity;
        try {
          $entity_field = $entity_field->getTranslation($data[self::VAR_ENTITY_LANG_CODE]);
        }catch(\Exception $e){}
        $entity_label = $entity_field->label();
        if (!empty($label_substitution) && isset($entity_field->{$label_substitution}) && !empty($entity_field->{$label_substitution}->value)) {
          $entity_label = $entity_field->{$label_substitution}->value;
        }
        $labels[] = $entity_label;
        $selected_entities[] = $entity_field;
      }
    }
    catch(EntityStorageException $e) {
      return $this->getResponse(self::ERROR_DATA_CANNOT_BE_SAVED, ['error' => $e->getMessage()]);
    }

    // Render entities labels.
    $labels_html = \Drupal::theme()->render('edit_in_place_reference_label', [
      'labels' => $labels,
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
