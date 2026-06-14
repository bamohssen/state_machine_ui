<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constant\OptionLabelSource;

/**
 * Default implementation of TransitionOptionsBuilderInterface.
 *
 * Always keeps the current state as the first option so editors can submit
 * a no-op save. When several transitions reach the same state and labels
 * come from the transitions themselves, the labels are merged with
 * {@see self::LABEL_SEPARATOR} to keep the option list unambiguous.
 *
 * @internal
 */
final class TransitionOptionsBuilder implements TransitionOptionsBuilderInterface {

  /**
   * Glue used when several transition labels collapse into one option.
   */
  private const string LABEL_SEPARATOR = ' / ';

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function build(
    array $transitions,
    string $current_id,
    string $current_label,
    OptionLabelSource $label_source,
  ): array {
    $options = [$current_id => $current_label];

    foreach ($transitions as $transition) {
      $to_state = $transition->getToState();
      $to_id = $to_state->getId();
      $label = $label_source === OptionLabelSource::Transition
        ? (string) $transition->getLabel()
        : (string) $to_state->getLabel();

      if (!isset($options[$to_id])) {
        $options[$to_id] = $label;
        continue;
      }
      // Multiple transitions reach the same state; merge their labels.
      if ($label_source === OptionLabelSource::Transition && $options[$to_id] !== $label) {
        $options[$to_id] .= self::LABEL_SEPARATOR . $label;
      }
    }

    return $options;
  }

}
