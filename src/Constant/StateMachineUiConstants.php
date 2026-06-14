<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Constant;

/**
 * Module-wide string constants to prevent magic-string duplication.
 */
final class StateMachineUiConstants {

  public const string PERM_ADMINISTER = 'administer state machine workflows';

  public const string ENTITY_WORKFLOW   = 'workflow_state_machine';
  public const string ENTITY_GROUP      = 'workflow_group_config';
  public const string ENTITY_SCHEMA     = 'workflow_metadata_schema';
  public const string ENTITY_TRANSITION = 'workflow_transition';

  public const string WIDGET_TYPE = 'state_field_rules';

  /**
   * Config prefix used in listAll() queries to scope workflow configs.
   */
  public const string CONFIG_PREFIX_WORKFLOW = 'state_machine_ui.workflow.';

  /**
   * Config prefix for transition entities.
   */
  public const string CONFIG_PREFIX_TRANSITION = 'state_machine_ui.transition.';

  /**
   * Separator between workflow ID and transition key in a composite ID.
   *
   * Double underscore is used to avoid ambiguity with state keys, which
   * commonly use single underscores. Example: "publication__publish".
   */
  public const string TRANSITION_ID_SEPARATOR = '__';

  public const string LOGGER_CHANNEL = 'state_machine_ui';

  /**
   * Format string for dynamic per-transition permission IDs.
   *
   * Filled with the workflow ID and transition key, e.g.
   *   "use article_publishing approve transition".
   */
  public const string PERM_TRANSITION_FORMAT = 'use %s %s transition';

  /**
   * Service tag identifying metadata-parser strategy implementations.
   */
  public const string TAG_METADATA_PARSER = 'state_machine_ui.metadata_parser';

}
