<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Workflow Metadata Schema config entity.
 *
 * Defines a reusable set of field_definitions (key, label, type, description)
 * that multiple WorkflowStateMachine entities can reference by ID via their
 * `metadata_schema` property. Separating the schema from the workflow allows
 * the same field structure to be shared across N workflows.
 *
 * @ConfigEntityType(
 *   id = "workflow_metadata_schema",
 *   label = @Translation("Workflow Metadata Schema"),
 *   label_collection = @Translation("Workflow Metadata Schemas"),
 *   handlers = {
 *     "list_builder" = "Drupal\state_machine_ui\ListBuilder\MetadataSchemaListBuilder",
 *     "form" = {
 *       "add" = "Drupal\state_machine_ui\Form\WorkflowMetadataSchemaForm",
 *       "edit" = "Drupal\state_machine_ui\Form\WorkflowMetadataSchemaForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "metadata_schema",
 *   admin_permission = "administer state machine workflows",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "field_definitions",
 *   },
 *   links = {
 *     "collection" = "/admin/config/workflow/state-machine/metadata-schemas",
 *     "add-form" = "/admin/config/workflow/state-machine/metadata-schemas/add",
 *     "edit-form" = "/admin/config/workflow/state-machine/metadata-schemas/{workflow_metadata_schema}",
 *     "delete-form" = "/admin/config/workflow/state-machine/metadata-schemas/{workflow_metadata_schema}/delete",
 *   },
 * )
 */
class WorkflowMetadataSchema extends ConfigEntityBase {

  /**
   * Machine name.
   */
  protected string $id = '';

  /**
   * Label.
   */
  protected string $label = '';

  /**
   * Ordered list of field definitions carried by each state.
   *
   * Each entry is an associative array with keys:
   *   - key (string): machine name, e.g. "tags"
   *   - label (string): label, e.g. "Tags"
   *   - type (string): one of string|list|boolean|number
   *   - description (string): optional help text.
   *
   * @var array<int, array{key: string, label: string, type: string, description: string}>
   */
  protected array $field_definitions = [];

  /**
   * Returns the ordered list of field definitions for this schema.
   *
   * @return array<int, array{key: string, label: string, type: string, description: string}>
   *   Indexed array of field definition maps.
   */
  public function getFieldDefinitions(): array {
    return $this->field_definitions;
  }

}
