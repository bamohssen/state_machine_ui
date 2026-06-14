<?php

declare(strict_types=1);

namespace Drupal\state_machine_ui\Service;

/**
 * Detects whether the Mermaid.js library is installed locally.
 *
 * Checks for the library in the standard Drupal libraries directory:
 * libraries/mermaid/dist/mermaid.min.js.
 */
final class MermaidLibraryLocator implements MermaidLibraryLocatorInterface {

  /**
   * Relative path from DRUPAL_ROOT to the mermaid.min.js file.
   */
  private const string LIBRARY_PATH = 'libraries/mermaid/dist/mermaid.min.js';

  /**
   * Cached result.
   */
  private ?bool $installed = NULL;

  /**
   * Checks if the Mermaid.js library is installed.
   *
   * @return bool
   *   TRUE if the library file exists.
   */
  public function isInstalled(): bool {
    if ($this->installed !== NULL) {
      return $this->installed;
    }
    $this->installed = file_exists(DRUPAL_ROOT . '/' . self::LIBRARY_PATH);
    return $this->installed;
  }

  /**
   * Gets the relative path to the library file.
   *
   * @return string
   *   The path relative to DRUPAL_ROOT.
   */
  public function getLibraryPath(): string {
    return self::LIBRARY_PATH;
  }

}
