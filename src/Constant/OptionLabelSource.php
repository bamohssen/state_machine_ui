<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

/**
 * Whether the transition options show state labels or transition labels.
 *
 * The widget always submits a target state key. With "transition" labels,
 * options stay keyed by their target state ID but display the transition
 * label, giving editors verb-shaped choices ("Submit for review") instead
 * of noun-shaped choices ("Review").
 */
enum OptionLabelSource: string {

  case State = 'state';
  case Transition = 'transition';

  /**
   * Returns the enum cases as a {value => label} map for form selects.
   *
   * Labels are plain strings so callers can wrap them in t() for the
   * actual rendering, keeping translation strings extractable.
   *
   * @return array<string, string>
   *   The value-to-label map.
   */
  public static function options(): array {
    return [
      self::State->value => 'Target state label',
      self::Transition->value => 'Transition label',
    ];
  }

}
