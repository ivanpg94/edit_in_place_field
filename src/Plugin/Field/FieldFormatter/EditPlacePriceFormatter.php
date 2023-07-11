<?php

namespace Drupal\edit_in_place_field\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\Plugin\Field\FieldFormatter\PriceDefaultFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'edit_in_place_field_price' formatter.
 *
 * @FieldFormatter(
 *   id = "edit_in_place_field_price",
 *   label = @Translation("Edit in place"),
 *   field_types = {
 *     "commerce_price"
 *   }
 * )
 */

class EditPlacePriceFormatter extends StringFormatter implements ContainerFactoryPluginInterface
{

  /**
   * Cache about list of entities.
   *
   * @var array
   */
  protected $cacheList = [];

  /**
   * The form builder service
   *
   * @var FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Construct a EditInPlaceFieldReferenceFormatter object
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service
   * @param FormBuilderInterface $form_builder
   *   The form builder service
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings
    , EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $entity_type_manager);

    $this->formBuilder = $form_builder;
  }


  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (!\Drupal::currentUser()->hasPermission('edit in place field editing permission')) {
      return parent::viewElements($items, $langcode);
    }
    $elements = [];
    $cardinality = 1;

    $field_values = [];
    if (!empty($items) ) {
      // Get cardinality of current field.
      $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();

      $url = '';
      $entity = $items->getEntity();
      $entity_type = $entity->getEntityType();
      if ($this->getSetting('link_to_entity') && !$entity->isNew() && $entity_type->hasLinkTemplate('canonical')) {
        $url = $this->getEntityUrl($entity);
      }

      // Render HTML elements.
      $render_elements = [];
      foreach($items as $delta => $item) {
        $view_value = $this->viewValue($item);
        $field_values[] = $item->getValue();

        $number = array_column($field_values, 'number');

        for($i = 0; $i < count($number); $i++) {
          if(!empty($number[$i])) {
            $number[$i] = number_format($number[$i], 2, '.', '') . '€';
          }
        }
        $render_elements[] = [
          '#theme' => 'edit_in_place_price_values',
          '#multiple' => ($cardinality !== 1),
          '#values' => $number,
        ];
      }
    }

    $ajax_call_replace = 'edit-in-place-replace-'.$items->getFieldDefinition()->getName().'-'.$items->getEntity()->id().'-'.$items->getEntity()->language()->getId();

    $elements[0] = [
      'field_container' => [
        '#attached' => [
          'library' => ['edit_in_place_field/edit_in_place'],
        ],
        '#type' => 'fieldset',
        '#title' => t('Edit'),
        '#attributes' => [
          'class' => ['edit-in-place-clickable', 'edit-in-place-clickable-init', $ajax_call_replace]
        ],
        'base_render' => $render_elements,
        'form_container' =>  $this->formBuilder->getForm('Drupal\edit_in_place_field\Form\EditPlacePriceForm', [
          'values' => $field_values,
          'cardinality' => $cardinality,
          'entity_type' => $items->getEntity()->getEntityTypeId(),
          'entity_id' => $items->getEntity()->id(),
          'field_name' => $items->getName(),
          'ajax_replace' => $ajax_call_replace,
        ])
      ]
    ];
    return $elements;
  }
}
