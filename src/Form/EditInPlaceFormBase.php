<?php

namespace Drupal\edit_in_place_field\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\edit_in_place_field\Ajax\RebindJSCommand;
use Drupal\edit_in_place_field\Ajax\StatusMessageCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class EditInPlaceFormBase.
 *
 * @package Drupal\edit_in_place_field\Form
 */
abstract class EditInPlaceFormBase extends FormBase {

  // Variables names
  const VAR_FIELD_VALUES = 'field_values';
  const VAR_FIELD_NAME = 'field_name';
  const VAR_ENTITY_TYPE = 'entity_type';
  const VAR_ENTITY_ID = 'entity_id';
  const VAR_AJAX_REPLACE_ID = 'ajax_replace';
  const VAR_ENTITY_LANG_CODE = 'entity_langcode';
  const VAR_LABEL_SUBSTITUTION = 'label_substitution';
  const VAR_PARENT_IDS = 'parent_ids';

  // Error strings
  const ERROR_INVALID_DATA = 'invalid_data';
  const ERROR_DATA_CANNOT_BE_SAVED = 'data_cannot_be_saved';
  const ERROR_UPDATE_NOT_ALLOWED = 'update_not_allowed';
  const ERROR_ENTITY_CANNOT_BE_LOADED = 'entity_cannot_be_loaded';

  /**
   * Entity type manager service.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Theme manager service.
   *
   * @var ThemeManagerInterface
   */
  protected $theme;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ThemeManagerInterface $theme_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->theme = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('theme.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $data = []) {
    $form = [
      'in_place_field' => $this->getInPlaceField($data),
      self::VAR_FIELD_NAME => [
        '#type' => 'hidden',
        '#value' => $data[self::VAR_FIELD_NAME],
      ],
      self::VAR_ENTITY_TYPE => [
        '#type' => 'hidden',
        '#value' => $data[self::VAR_ENTITY_TYPE],
      ],
      self::VAR_ENTITY_ID => [
        '#type' => 'hidden',
        '#value' => $data[self::VAR_ENTITY_ID],
      ],
      self::VAR_AJAX_REPLACE_ID => [
        '#type' => 'hidden',
        '#value' => $data[self::VAR_AJAX_REPLACE_ID],
      ],
      'actions' => [
        '#type' => 'fieldgroup',
        'save' => [
          '#type' => 'button',
          '#value' => 'Save',
          '#attributes' => [
            'class' => [
              'edit-in-place-save'
            ]
          ],
          '#ajax' => array(
            'callback' => [$this,'inPlaceAction'],
            'event' => 'click',
          ),
        ],
        'cancel' => [
          '#type' => 'button',
          '#value' => 'Cancel',
          '#attributes' => [
            'class' => [
              'edit-in-place-cancel'
            ],
            '#submit' => ['EditInPlaceFormBase::cancelCallback'],
          ]
        ],
      ]
    ];
    return $form;
  }

  public function cancelCallback(){}

  /**
   * Generate a Ajax response or error.
   *
   * @param null $error_type
   *    Error type in case of error.
   * @param array $data
   *    Data used to process error messages.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *    Ajax response object.
   */
  protected function getResponse($error_type = NULL, $data = []) {
    $message = $this->t('Data saved successfully.');
    $message_type = Messenger::TYPE_STATUS;

    switch($error_type) {
      case self::ERROR_DATA_CANNOT_BE_SAVED:
        $message = $this->t('Data cannot be saved @error.',
          ['@error' => isset($data['error'])?$data['error']:'']
        );
        $message_type = Messenger::TYPE_ERROR;
        break;

      case self::ERROR_ENTITY_CANNOT_BE_LOADED:
        $message = $this->t('Entity @entity_id of type @entity_type cannot be loaded.',[
            '@entity_type' => isset($data[self::VAR_ENTITY_TYPE])?$data[self::VAR_ENTITY_TYPE]:'',
            '@entity_id' => isset($data[self::VAR_ENTITY_ID])?$data[self::VAR_ENTITY_ID]:'',
          ]
        );
        $message_type = Messenger::TYPE_WARNING;
        break;

      case self::ERROR_INVALID_DATA:
        $message = $this->t('Invalid data (field name: @field_name, entity_type: @entity_type, entity_id: @entity_id).',[
            '@field_name' => isset($data[self::VAR_FIELD_NAME])?$data[self::VAR_FIELD_NAME]:'',
            '@entity_type' => isset($data[self::VAR_ENTITY_TYPE])?$data[self::VAR_ENTITY_TYPE]:'',
            '@entity_id' => isset($data[self::VAR_ENTITY_ID])?$data[self::VAR_ENTITY_ID]:'',
          ]
        );
        $message_type = Messenger::TYPE_WARNING;
        break;

      case self::ERROR_UPDATE_NOT_ALLOWED:
        $message = $this->t('Update not allowed for user @username..',[
            '@username' => isset($data['username'])?$data['username']:'',
          ]
        );
        $message_type = Messenger::TYPE_WARNING;
        break;
    }

    if ($message_type === Messenger::TYPE_WARNING) {
      $this->logger('edit_in_place_field')->warning($message);
    }

    $response = new AjaxResponse();
    $response->addCommand(new StatusMessageCommand($message_type, $message));
    return $response;
  }


  /**
   * Check access to the form.
   *
   * @return bool|\Drupal\Core\Ajax\AjaxResponse
   *    TRUE if access is allowed or ajax response if access is denied.
   */
  protected function accessAllowed() {
    if (!\Drupal::currentUser()->hasPermission('edit in place field editing permission')) {
      return $this->getResponse(self::ERROR_UPDATE_NOT_ALLOWED, [
        'username' => \Drupal::currentUser()->getAccountName()
      ]);
    }
    return TRUE;
  }

  /**
   * Save data from ajax request.
   *
   * @param array $form
   *    Render array of Drupal form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *    Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *    Ajax response object.
   */
  public function inPlaceAction(array &$form, FormStateInterface $form_state) {
    $access = $this->accessAllowed();
    if ($access !== TRUE) {
      return $access;
    }
    $data = $this->processRequest();
    return $this->processResponse($data);
  }

  /**
   * Process HTTP request and parameters.
   *
   * @return array
   *    Array of parameters needed to process a response.
   */
  protected function processRequest() {
    // Get data from ajax request.
    $field_name = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_FIELD_NAME);
    $entity_type = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_ENTITY_TYPE);
    $entity_id = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_ENTITY_ID);
    $ajax_replace = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_AJAX_REPLACE_ID);
    $parent_ids = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_PARENT_IDS);
    $label_substitution = \Drupal::requestStack()->getCurrentRequest()->get(self::VAR_LABEL_SUBSTITUTION);


    if (!empty($parent_ids)) {
      $parent_ids = explode(', ', $parent_ids);

      // Retrieve the selected values.
      $field_values = [];
      foreach($parent_ids as $parent_id) {
        $field_values = array_merge($field_values, \Drupal::requestStack()->getCurrentRequest()->get('in_place_field'.$parent_id, []));
      }
    }
    else {
      $field_values = \Drupal::requestStack()->getCurrentRequest()->get($field_name);
    }

    // Get the current langcode.
    $replace_data = explode('-', $ajax_replace);
    $entity_langcode = end($replace_data);

    return [
      self::VAR_FIELD_NAME => $field_name,
      self::VAR_ENTITY_TYPE => $entity_type,
      self::VAR_ENTITY_ID => $entity_id,
      self::VAR_AJAX_REPLACE_ID => $ajax_replace,
      self::VAR_FIELD_VALUES => $field_values,
      self::VAR_ENTITY_LANG_CODE => $entity_langcode,
      self::VAR_LABEL_SUBSTITUTION => $label_substitution,
    ];
  }

  /**
   * Load the entity needed to be updated of set an Ajax response error.
   *
   * @param $data
   *    Data sent from ajax request.
   * @param \Drupal\Core\Ajax\AjaxResponse $error_response
   *    If the function return is empty, this error response is sent.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadEntity($data, AjaxResponse $error_response): EntityInterface {
    $field_name = $data[self::VAR_FIELD_NAME];
    $entity_type = $data[self::VAR_ENTITY_TYPE];
    $entity_id = $data[self::VAR_ENTITY_ID];
    $lang_code = $data[self::VAR_ENTITY_LANG_CODE];
    $entity = NULL;
    if (empty($field_name) || empty($entity_type) || empty($entity_id)) {
      $error_response = $this->getResponse(self::ERROR_INVALID_DATA, [
        self::VAR_FIELD_NAME => $field_name,
        self::VAR_ENTITY_TYPE => $entity_type,
        self::VAR_ENTITY_ID => $entity_id,
      ]);
    }

    // try to load entity.
    try {
      /** @var EntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      if (!empty($entity) && method_exists($entity, 'getTranslation') && $entity->hasTranslation($lang_code)) {
        $entity = $entity->getTranslation($lang_code);
      }
    }
    catch(\Exception $e){}

    if (empty($entity)) {
      $error_response = $this->getResponse(self::ERROR_ENTITY_CANNOT_BE_LOADED, [
        self::VAR_ENTITY_TYPE => $entity_type,
        self::VAR_ENTITY_ID => $entity_id,
      ]);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Reload the html after a success ajax response and rebind the JavaScript for next update.
   *
   * @param string $ajax_replace
   *    The css class of the html container (fieldset)
   * @param string $html
   *    The render of the updated html of the field.
   *
   * @return AjaxResponse
   *
   */
  protected function reloadAndRebind(string $ajax_replace, string $html): AjaxResponse {
    $response = $this->getResponse();

    // Values replacement.
    $response->addCommand(new InsertCommand('.'.$ajax_replace.' .edit-in-place-editable', $html));

    // Bind JavaScript events after html replacement from ajax call.
    $response->addCommand(new RebindJSCommand('rebindJS', '.'.$ajax_replace));

    return $response;
  }

  /**
   * Get text fields to change field value.
   *
   * @param $data
   *    Data to be pass to the build form method.
   *
   * @return array
   *    Render array of the choice field.
   */
  abstract protected function getInPlaceField(array $data): array;

  /**
   * Process a response from ajax request.
   *
   * @param $data
   *    Parameters needed to process the response
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *    Ajax response object
   */
  abstract protected function processResponse(array $data): AjaxResponse;
}
