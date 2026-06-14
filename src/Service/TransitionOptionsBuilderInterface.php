<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constant\OptionLabelSource;

/**
 * Computes the options array displayed by the state widget selector.
 *
 * Encapsulates the rules for deduplicating target states, merging labels
 * when multiple transitions reach the same state, and deciding whether
 * labels come from the target state or from the transition itself.
 */
interface TransitionOptionsBuilderInterface {

  /**
   * Builds the {target_state_id => label} map.
   *
   * The current state is always included as the first option so editors can
   * submit a no-op save (keep the current status).
   *
   * @param array<int|string, \Drupal\state_machine\Plugin\Workflow\WorkflowTransition> $transitions
   *   The allowed transitions, already filtered by guards/metadata/access.
   * @param string $current_id
   *   The state ID currently held by the entity.
   * @param string $current_label
   *   Label of the current state.
   * @param \Drupal\state_machine_ui\Constant\OptionLabelSource $label_source
   *   Whether labels come from the target state or the transition.
   *
   * @return array<string, string>
   *   Target state ID → label.
   */
  public function build(
    array $transitions,
    string $current_id,
    string $current_label,
    OptionLabelSource $label_source,
  ): array;

}
