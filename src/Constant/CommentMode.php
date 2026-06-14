<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

/**
 * Whether the widget asks for a comment when a transition is fired.
 */
enum CommentMode: string {

  case Disabled = 'disabled';
  case Optional = 'optional';
  case Required = 'required';

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
      self::Disabled->value => 'Disabled',
      self::Optional->value => 'Optional',
      self::Required->value => 'Required',
    ];
  }

}
