<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Validation;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface;

/**
 * Validates conditionally required fields server-side.
 */
final class ConditionalRequiredValidator implements ConditionalRequiredValidatorInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConditionalFieldResolverInterface $resolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $config = $element['#state_machine_ui'] ?? [];
    if (!is_array($config)) {
      return;
    }
    $conditions = $config['conditions'] ?? [];
    if (!is_array($conditions) || empty($conditions)) {
      return;
    }
    $state_field_name = $config['state_field_name'] ?? '';
    if ($state_field_name === '') {
      return;
    }

    // Get the selected target state.
    $state_value = $form_state->getValue($state_field_name);
    if (is_array($state_value)) {
      $target_state = (string) ($state_value[0]['value'] ?? $state_value['value'] ?? '');
    }
    else {
      $target_state = (string) ($state_value ?? '');
    }
    if ($target_state === '') {
      return;
    }

    $required_fields = $this->resolver->getRequiredFields($conditions, $target_state);
    if (empty($required_fields)) {
      return;
    }

    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    foreach ($required_fields as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $field_value = $form_state->getValue($field_name);
      $field_list = $entity->get($field_name);
      $field_list->setValue($field_value);

      if ($field_list->isEmpty()) {
        $field_label = $entity->getFieldDefinition($field_name)?->getLabel() ?? $field_name;
        $form_state->setErrorByName(
          $field_name,
          $this->t('@field is required when transitioning to @state.', [
            '@field' => $field_label,
            '@state' => $target_state,
          ]),
        );
      }
    }
  }

}
