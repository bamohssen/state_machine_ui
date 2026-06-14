<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Validation;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\state_machine\WorkflowManagerInterface;
use Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates conditionally required fields server-side.
 *
 * @internal
 */
final class ConditionalRequiredValidator implements ConditionalRequiredValidatorInterface {

  use StringTranslationTrait;

  /**
   * Constructs a ConditionalRequiredValidator instance.
   *
   * @param \Drupal\state_machine_ui\Service\ConditionalFieldResolverInterface $resolver
   *   The conditional field resolver.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   The workflow plugin manager, used to resolve human-readable state labels.
   */
  public function __construct(
    private readonly ConditionalFieldResolverInterface $resolver,
    #[Autowire(service: 'plugin.manager.workflow')]
    private readonly WorkflowManagerInterface $workflowManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $config = $element['#state_machine_ui'] ?? [];
    if (!is_array($config)) {
      return;
    }
    $conditions = $config['conditions'] ?? [];
    if (!is_array($conditions) || empty($conditions)) {
      return;
    }
    $state_field_name = $config['state_field_name'] ?? '';
    if ($state_field_name === '') {
      return;
    }

    $state_value = $form_state->getValue($state_field_name);
    if (is_array($state_value)) {
      $target_state = (string) ($state_value[0]['value'] ?? $state_value['value'] ?? '');
    }
    else {
      $target_state = (string) ($state_value ?? '');
    }
    if ($target_state === '') {
      return;
    }

    // Only fields that are both required AND effectively visible for this target
    // state are returned (see ConditionalFieldResolver::isFieldRequiredForState).
    $required_fields = $this->resolver->getRequiredFields($conditions, $target_state);
    if (empty($required_fields)) {
      return;
    }

    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    $entity = $form_object->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $state_label = $this->getStateLabel($entity, $state_field_name, $target_state);

    foreach ($required_fields as $field_name) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $field_value = $form_state->getValue($field_name);
      $field_list = $entity->get($field_name);
      $field_list->setValue($field_value);

      if ($field_list->isEmpty()) {
        $field_label = $entity->getFieldDefinition($field_name)?->getLabel() ?? $field_name;
        $form_state->setErrorByName(
          $field_name,
          $this->t('@field is required when transitioning to @state.', [
            '@field' => $field_label,
            '@state' => $state_label,
          ]),
        );
      }
    }
  }

  /**
   * Gets the human-readable label for a state, falling back to its ID.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being validated.
   * @param string $state_field_name
   *   The name of the state field on the entity.
   * @param string $state_id
   *   The machine name of the target state.
   *
   * @return string
   *   The state label, or $state_id if the workflow cannot be loaded.
   */
  private function getStateLabel(FieldableEntityInterface $entity, string $state_field_name, string $state_id): string {
    $workflow_id = $entity->getFieldDefinition($state_field_name)?->getSetting('workflow') ?? '';
    if ($workflow_id === '') {
      return $state_id;
    }
    try {
      $workflow = $this->workflowManager->createInstance($workflow_id);
    }
    catch (PluginException) {
      return $state_id;
    }
    return (string) ($workflow->getState($state_id)?->getLabel() ?? $state_id);
  }

}
