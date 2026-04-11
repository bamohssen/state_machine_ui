<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\ValueObject;

enum FieldType: string {

  case String = 'string';
  case List = 'list';
  case Boolean = 'boolean';
  case Number = 'number';

  public static function options(): array {
    return [
      self::String->value => 'Text',
      self::List->value => 'List (multiple values)',
      self::Boolean->value => 'Boolean (yes/no)',
      self::Number->value => 'Number',
    ];
  }

  public static function fromString(string $value): self {
    return self::tryFrom($value) ?? self::String;
  }

}
