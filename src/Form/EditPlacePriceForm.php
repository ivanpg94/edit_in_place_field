<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Class EditInPlaceStringForm.
 *
 * @package Drupal\edit_in_place_field\Form
 */
class EditPlacePriceForm extends EditInPlaceFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_in_place_price_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getInPlaceField (array $data): array {
    $text_fields = [];

    if ($data['cardinality'] !== -1) {
      for($delta = 0; $delta < $data['cardinality']; $delta++) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => isset($data['values'][0]['number']) ? number_format($data['values'][0]['number'], 2, '.', '') : '',
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE,

        ];
      }
    }
    else {
      $delta = 0;
      while (isset($data['values'][0])) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => $data['values'][0]['number'],
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE,

        ];
        $delta++;
      }
      $text_fields[] = [
        '#type' => 'textfield',
        '#default_value' => '',
        '#name' => $data[self::VAR_FIELD_NAME].'[]',
        '#multiple' => TRUE,

      ];
    }
    return $text_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse(array $data): AjaxResponse {
    $date = date('d-m-y h:i:s');
    $unix_date = strtotime($date);
    $id = $data['entity_id'];
    $product = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load(4622)->changed->getValue()[0]['value'];

    // Try to load the entity to update.
    $error_response = new AjaxResponse();
    $entity = $this->loadEntity($data, $error_response);
    if (empty($entity)) {
      return $error_response;
    }

    // try to update the entity.
    try {
      $saved_values = [];
      foreach ($data[self::VAR_FIELD_VALUES] as $value) {
        if (trim($value) !== '') {
          $saved_values[] = $value;
        }
      }
      // Obtén el valor del precio
      $price_value = $data[self::VAR_FIELD_VALUES][0];

// Reemplaza los puntos con comas
      $price_value = str_replace(',', '.', $price_value);

// Crea el objeto Price con el precio modificado
      $price = new \Drupal\commerce_price\Price($price_value, 'EUR');


      $entity->set($data[self::VAR_FIELD_NAME],$price);

      $entity->save();

      $date = date('y-m-d h:i:s');

      $id_variacion = $data['entity_id'];
      $variation = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->load($id_variacion);
      $product_id = $variation->product_id->getValue()[0]['target_id'];

      $product = \Drupal::entityTypeManager()->getStorage('commerce_product')->load($product_id);

      $new_changed_value = strtotime($date);
      $product->set('changed', $new_changed_value);
      $product->save();
    }
    catch(EntityStorageException $e) {
      return $this->getResponse(self::ERROR_DATA_CANNOT_BE_SAVED, ['error' => $e->getMessage()]);
    }

    // Get cardinality of current field.
    $definition = $entity->{$data[self::VAR_FIELD_NAME]}->getFieldDefinition();
    $fieldStorageDefinition = $definition->getFieldStorageDefinition();
    $cardinality = $fieldStorageDefinition->getCardinality();

    // Render entities.
    $values_html = $this->theme->render('edit_in_place_price_values', [
      'values' => $saved_values,
      'multiple' => ($cardinality === 1) ? FALSE : TRUE,
    ]);
    // Return ajax response.
    return $this->reloadAndRebind($data[self::VAR_AJAX_REPLACE_ID], $values_html);
  }

}
