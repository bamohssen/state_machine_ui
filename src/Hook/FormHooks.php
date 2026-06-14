<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Hook;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\state_machine_ui\Constant\StateMachineUiConstants;
use Drupal\state_machine_ui\Constraint\MetadataValueConstraint;
use Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface;
use Drupal\state_machine_ui\Validation\ConditionalRequiredValidatorInterface;

/**
 * Handles hook_form_alter logic for the state_field_rules widget.
 *
 * Applies conditional visibility (#states) to fields and registers server-side
 * required validation based on the conditions stored in widget settings.
 */
final readonly class FormHooks {

  public function __construct(
    private ConditionalFieldResolverInterface $resolver,
    private ConditionalRequiredValidatorInterface $validator,
  ) {}

  /**
   * Processes hook_form_alter for entity forms using state_field_rules widget.
   *
   * Iterates form display components and, for each component using the
   * state_field_rules widget that has conditions configured, applies
   * conditional visibility and registers the server-side validator.
   *
   * @param array $form
   *   The form render array, modified in place.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $form_id
   *   The form ID.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $form_display = $this->getFormDisplay($form_state);
    if ($form_display === NULL) {
      return;
    }

    foreach ($form_display->getComponents() as $field_name => $component) {
      if (!is_array($component) || ($component['type'] ?? '') !== StateMachineUiConstants::WIDGET_TYPE) {
        continue;
      }
      $settings = $component['settings'] ?? [];
      if (!is_array($settings)) {
        continue;
      }
      $conditions = $settings['conditions'] ?? [];
      if (!is_array($conditions) || empty($conditions)) {
        continue;
      }
      $this->applyConditions($form, $field_name, $conditions);
    }
  }

  /**
   * Applies conditional rules to the form render array.
   *
   * Attaches Drupal #states visibility arrays to each field referenced by the
   * conditions, then registers the server-side required validator so that PHP
   * enforces required fields even if JavaScript is disabled.
   *
   * @param array $form
   *   The form render array, modified in place.
   * @param string $state_field_name
   *   The name of the state field driving the conditions.
   * @param array $conditions
   *   The conditions array from widget settings.
   */
  private function applyConditions(array &$form, string $state_field_name, array $conditions): void {
    // SEC-3: guard against CSS selector injection before embedding in selector.
    $safe_name = MetadataValueConstraint::sanitize($state_field_name);
    $selector = ':input[name="' . $safe_name . '[0][value]"]';
    $referenced_fields = $this->resolver->getReferencedFields($conditions);

    foreach ($referenced_fields as $field_name) {
      if (!isset($form[$field_name])) {
        continue;
      }
      $states = $this->resolver->getStates($conditions, $field_name, $selector);
      if (!empty($states['#states'])) {
        $form[$field_name]['#states'] = $states['#states'];
      }
    }

    $form['#state_machine_ui'] = [
      'conditions' => $conditions,
      'state_field_name' => $state_field_name,
    ];
    $form['#element_validate'][] = [$this->validator, 'validate'];
  }

  /**
   * Gets the form display object from form state storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   *   The form display object, or NULL if not found.
   */
  private function getFormDisplay(FormStateInterface $form_state): ?EntityFormDisplayInterface {
    $storage = $form_state->getStorage();
    $display = $storage['form_display'] ?? NULL;
    return $display instanceof EntityFormDisplayInterface ? $display : NULL;
  }

}
