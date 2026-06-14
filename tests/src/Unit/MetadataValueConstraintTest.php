<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\state_machine_ui\Constraint\MetadataValueConstraint;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Constraint\MetadataValueConstraint
 * @group state_machine_ui
 */
final class MetadataValueConstraintTest extends TestCase {

  /**
   * @covers ::isValid
   * @dataProvider validValueProvider
   */
  public function testIsValidAcceptsValidValues(string $value): void {
    $this->assertTrue(MetadataValueConstraint::isValid($value));
  }

  /**
   * Provides values that should be accepted by the constraint.
   *
   * @return array<string, array{string}>
   *   Test cases.
   */
  public static function validValueProvider(): array {
    return [
      'lowercase letters only'       => ['draft'],
      'digits only'                  => ['123'],
      'underscore only'              => ['_'],
      'mixed lower and digits'       => ['state1'],
      'leading underscore'           => ['_internal'],
      'trailing underscore'          => ['status_'],
      'all valid chars combined'     => ['abc_123_xyz'],
      'single char lowercase'        => ['a'],
      'single digit'                 => ['0'],
    ];
  }

  /**
   * @covers ::isValid
   * @dataProvider invalidValueProvider
   */
  public function testIsValidRejectsInvalidValues(string $value): void {
    $this->assertFalse(MetadataValueConstraint::isValid($value));
  }

  /**
   * Provides values that should be rejected by the constraint.
   *
   * @return array<string, array{string}>
   *   Test cases.
   */
  public static function invalidValueProvider(): array {
    return [
      'uppercase letter'        => ['Draft'],
      'space in value'          => ['my value'],
      'hyphen'                  => ['my-value'],
      'dot'                     => ['v1.0'],
      'colon'                   => ['state:value'],
      'html tag'                => ['<script>'],
      'empty string'            => [''],
      'unicode letter'          => ['état'],
      'newline'                 => ["line\nbreak"],
      'backtick'                => ['`cmd`'],
      'double quote'            => ['"value"'],
    ];
  }

  /**
   * @covers ::isValid
   */
  public function testEmptyStringIsInvalid(): void {
    $this->assertFalse(MetadataValueConstraint::isValid(''));
  }

  /**
   * @covers ::isValid
   */
  public function testPatternConstantMatchesExpectedRegex(): void {
    $this->assertSame('/^[a-z0-9_]+$/', MetadataValueConstraint::PATTERN);
  }

  /**
   * @covers ::isValid
   */
  public function testIsValidReturnsBool(): void {
    $this->assertIsBool(MetadataValueConstraint::isValid('valid'));
    $this->assertIsBool(MetadataValueConstraint::isValid('INVALID'));
  }

}
