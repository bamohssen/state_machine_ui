<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

use Drupal\state_machine_ui\Constraint\MetadataValueConstraint;
use Psr\Log\LoggerInterface;

/**
 * Shared normalization and validation helpers for metadata parsers.
 *
 * @internal
 */
abstract readonly class AbstractMetadataParser implements MetadataParserInterface {

  public function __construct(
    protected LoggerInterface $logger,
  ) {}

  /**
   * Converts a raw scalar or array value to a non-empty list of strings.
   *
   * @param mixed $raw
   *   A scalar or array from a plugin/entity definition.
   *
   * @return string[]
   *   Normalised, non-empty string values (re-indexed).
   */
  protected function normalizeValues(mixed $raw): array {
    $values = is_array($raw)
      ? array_map(strval(...), array_values($raw))
      : [(string) $raw];
    return array_values(array_filter($values, static fn(string $v): bool => $v !== ''));
  }

  /**
   * Logs a warning for each value that does not match [a-z0-9_]+.
   *
   * @param string[] $values
   *   Already-normalised (non-empty) values.
   * @param string $field_key
   *   Metadata key, for context in the log message.
   * @param string $context
   *   Human-readable location string, e.g. "workflow_id:state:state_id".
   */
  protected function warnNonConformant(array $values, string $field_key, string $context): void {
    foreach ($values as $value) {
      if (!MetadataValueConstraint::isValid($value)) {
        $this->logger->warning(
          'Non-conformant metadata value @value for key @key in @context. Expected [a-z0-9_]+.',
          [
            '@value' => $value,
            '@key'   => $field_key,
            '@context' => $context,
          ]
        );
      }
    }
  }

}
