<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Validation;

use Drupal\Core\Form\FormStateInterface;

/**
 * Validates conditionally required fields server-side.
 *
 * @api
 */
interface ConditionalRequiredValidatorInterface {

  /**
   * Validates that required fields are not empty for the selected target state.
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form array.
   */
  public function validate(array &$element, FormStateInterface $form_state, array &$complete_form): void;

}
