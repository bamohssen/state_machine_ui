<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

/**
 * Inter-key logic applied when combining metadata filters.
 *
 * Used by {@see \Drupal\state_machine_ui\Service\MetadataFilter}: with
 * "and" all checked metadata keys must match, with "or" matching a single
 * key is enough.
 */
enum FilterLogic: string {

  case And = 'and';
  case Or = 'or';

}
