<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Backed enum representing the supported metadata field types.
 */
enum FieldType: string {

  case String = 'string';
  case List = 'list';
  case Boolean = 'boolean';
  case Number = 'number';

  /**
   * Returns a translatable options array suitable for select elements.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Associative array of value => translatable label.
   */
  public static function options(): array {
    return [
      self::String->value => new TranslatableMarkup('Text'),
      self::List->value => new TranslatableMarkup('List (multiple values)'),
      self::Boolean->value => new TranslatableMarkup('Boolean (yes/no)'),
      self::Number->value => new TranslatableMarkup('Number'),
    ];
  }

  /**
   * Creates a FieldType from a string value, falling back to String on unknown.
   *
   * @param string $value
   *   The raw string value to convert.
   *
   * @return self
   *   The matching case, or self::String if the value is not recognized.
   */
  public static function fromString(string $value): self {
    return self::tryFrom($value) ?? self::String;
  }

}
