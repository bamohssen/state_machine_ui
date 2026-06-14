<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Detects whether the Mermaid.js library is installed locally.
 *
 * @api
 */
interface MermaidLibraryLocatorInterface {

  /**
   * Checks if the Mermaid.js library is installed.
   *
   * @return bool
   *   TRUE if the library file exists.
   */
  public function isInstalled(): bool;

  /**
   * Gets the relative path to the library file.
   *
   * @return string
   *   The path relative to DRUPAL_ROOT.
   */
  public function getLibraryPath(): string;

}
