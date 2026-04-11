<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface;
use Drupal\state_machine_ui\Validation\ConditionalRequiredValidatorInterface;

/**
 * Handles hook_form_alter logic for the state_field_rules widget.
 */
final class FormHooks {

  public function __construct(
    protected readonly ConditionalFieldResolverInterface $resolver,
    protected readonly ConditionalRequiredValidatorInterface $validator,
  ) {}

  /**
   * Processes hook_form_alter for entity forms.
   */
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
      if (!is_array($component) || ($component['type'] ?? '') !== 'state_field_rules') {
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
   * Applies conditional rules to the form.
   */
  private function applyConditions(array &$form, string $state_field_name, array $conditions): void {
    $selector = ':input[name="' . $state_field_name . '[0][value]"]';
    $referenced = $this->resolver->getReferencedFields($conditions);

    foreach ($referenced as $field_name) {
      if (!isset($form[$field_name])) {
        continue;
      }
      $states = $this->resolver->resolveStates($conditions, $field_name, $selector);
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
   * Gets the form display from the form state.
   */
  private function getFormDisplay(FormStateInterface $form_state): mixed {
    $storage = $form_state->getStorage();
    return $storage['form_display'] ?? NULL;
  }

}
