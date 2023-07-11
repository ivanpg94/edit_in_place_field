<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\commerce_stock_field\Plugin\Field\FieldType\StockLevel;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Class EditInPlaceStringForm.
 *
 * @package Drupal\edit_in_place_field\Form
 */
class EditPlaceStockForm extends EditInPlaceFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_in_place_stock_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getInPlaceField (array $data): array {

    //dump($id_variacion);
    $text_fields = [];
    if ($data['cardinality'] !== -1) {
      for($delta = 0; $delta < $data['cardinality']; $delta++) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => isset($data['values'][$delta]) ? intval($data['values'][0]['value']) : '',
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE
        ];
      }
    }
    else {
      $delta = 0;
      while (isset($data['values'][0])) {
        $text_fields[] = [
          '#type' => 'textfield',
          '#default_value' => $data['values'][0]['value'],
          '#name' => $data[self::VAR_FIELD_NAME].'[]',
          '#multiple' => TRUE
        ];
        $delta++;
      }
      $text_fields[] = [
        '#type' => 'textfield',
        '#default_value' => '',
        '#name' => $data[self::VAR_FIELD_NAME].'[]',
        '#multiple' => TRUE
      ];
    }

    return $text_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse(array $data): AjaxResponse {
    // Try to load the entity to update.
    $error_response = new AjaxResponse();
    $entity = $this->loadEntity($data, $error_response);
    $stockServiceManager = \Drupal::service('commerce_stock.service_manager');
    $stocks = $stockServiceManager->getStockLevel($entity);
    if (empty($entity)) {
      return $error_response;
    }

    // try to update the entity.
    try {
      $saved_values = [];
      foreach($data[self::VAR_FIELD_VALUES] as $value) {
        if (trim($value) !== '') {
          $saved_values[] = $value;
        }
      }
      $stock = $data[self::VAR_FIELD_VALUES][0];
      $resultado = $stock - $stocks;
      $entity->set('field_stock', $resultado);
      $entity->save();
     // $entity->{$data[self::VAR_FIELD_NAME]} = $saved_values;
     // $entity->save();
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
    $values_html = $this->theme->render('edit_in_place_stock_values', [
      'values' => $saved_values,
      'multiple' => ($cardinality === 1) ? FALSE : TRUE,
    ]);

    // Return ajax response.
    return $this->reloadAndRebind($data[self::VAR_AJAX_REPLACE_ID], $values_html);
  }

}
