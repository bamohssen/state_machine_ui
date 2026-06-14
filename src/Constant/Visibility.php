<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

/**
 * Visibility option for a conditional field rule.
 *
 * Drives the per-rule "Show" / "Hide" choice in
 * {@see \Drupal\state_machine_ui\Form\ConditionsTableBuilder}; a "Show" rule
 * acts as a whitelist for the target field, a "Hide" rule as a blacklist.
 */
enum Visibility: string {

  case Show = 'show';
  case Hide = 'hide';

}
