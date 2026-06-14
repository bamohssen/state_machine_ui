<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for simple config-entity forms.
 *
 * Provides a saveAndRedirect() helper that handles the SAVED_NEW / SAVED_UPDATED
 * status message pattern and the redirect to the entity collection, eliminating
 * the identical save() boilerplate that would otherwise be duplicated across
 * WorkflowGroupForm and WorkflowMetadataSchemaForm.
 */
abstract class ConfigEntityFormBase extends EntityForm {

  /**
   * Human-readable noun for status messages (e.g. "group", "metadata schema").
   *
   * Override in subclasses to customise the message strings.
   */
  abstract protected function entityNoun(): string;

  /**
   * Saves the entity, adds a status message, and redirects to the collection.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED.
   */
  protected function saveAndRedirect(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    $noun   = $this->entityNoun();
    $label  = $this->entity->label();

    $this->messenger()->addStatus(
      $status === SAVED_NEW
        ? $this->t('Created @noun %label.', ['@noun' => $noun, '%label' => $label])
        : $this->t('Updated @noun %label.', ['@noun' => $noun, '%label' => $label])
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $status;
  }

}
