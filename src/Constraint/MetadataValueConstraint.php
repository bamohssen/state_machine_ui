<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constraint;

use Drupal\Core\Form\FormStateInterface;

/**
 * Shared constraint for workflow metadata values.
 *
 * A metadata value is valid when it contains only lowercase letters, digits,
 * and underscores (matches PATTERN). This rule is enforced at form-validation
 * time, at import/migration time (install hooks), and at parse/read time
 * (WorkflowMetadataReader).
 */
final class MetadataValueConstraint {

  /**
   * Regex that every metadata value must match.
   */
  public const string PATTERN = '/^[a-z0-9_]+$/';

  /**
   * Returns TRUE when $value satisfies PATTERN.
   */
  public static function isValid(string $value): bool {
    return (bool) preg_match(self::PATTERN, $value);
  }

  /**
   * Strips any character not allowed by PATTERN (used for CSS selector safety).
   */
  public static function sanitize(string $value): string {
    return (string) preg_replace('/[^a-z0-9_]/', '', $value);
  }

  /**
   * Form #element_validate callback — sets a form error for invalid values.
   *
   * Handles both:
   *   - Scalar fields (textfield / string type): validates the trimmed value.
   *   - List fields (textarea / list type): splits on newlines and validates
   *     each non-empty line independently. The error lists every offending
   *     entry so the editor can fix them in one round-trip.
   *
   * Usage:
   * @code
   * $element['#element_validate'][] = [MetadataValueConstraint::class, 'validateFormElement'];
   * @endcode
   *
   * @param array $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function validateFormElement(array $element, FormStateInterface $form_state): void {
    $raw = (string) ($element['#value'] ?? '');
    if ($raw === '') {
      return;
    }

    $is_list = ($element['#type'] ?? '') === 'textarea';
    $values = $is_list
      ? array_values(array_filter(
          array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: []),
          static fn(string $line): bool => $line !== ''
        ))
      : [trim($raw)];

    $invalid = array_values(array_filter(
      $values,
      static fn(string $value): bool => !self::isValid($value)
    ));

    if ($invalid === []) {
      return;
    }

    $form_state->setError(
      $element,
      t('@label must contain only lowercase letters, digits, and underscores. Invalid: @values', [
        '@label' => $element['#title'] ?? $element['#name'] ?? t('Value'),
        '@values' => implode(', ', $invalid),
      ]),
    );
  }

}
