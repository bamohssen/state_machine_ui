<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\state_machine_ui\Service\ConditionalFieldResolver;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Service\ConditionalFieldResolver
 * @group state_machine_ui
 */
final class ConditionalFieldResolverTest extends TestCase {

  /**
   * The service under test.
   */
  private ConditionalFieldResolver $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resolver = new ConditionalFieldResolver();
  }

  /**
   * @covers ::getStates
   */
  public function testResolveStatesReturnsEmptyArrayWhenNoRulesForField(): void {
    $result = $this->resolver->getStates(
      [['field' => 'other_field', 'state' => 'draft', 'visibility' => 'show']],
      'body',
      ':input[name="status[0][value]"]',
    );
    $this->assertSame([], $result);
  }

  /**
   * @covers ::getStates
   */
  public function testResolveStatesShowRuleProducesVisibleStates(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show'],
      ['field' => 'body', 'state' => 'review', 'visibility' => 'show'],
    ];
    $result = $this->resolver->getStates($conditions, 'body', ':input[name="status"]');

    $this->assertArrayHasKey('#states', $result);
    $this->assertArrayHasKey('visible', $result['#states']);
    $this->assertCount(2, $result['#states']['visible']);
  }

  /**
   * @covers ::getStates
   */
  public function testResolveStatesHideRuleProducesInvisibleStates(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'archived', 'visibility' => 'hide'],
    ];
    $result = $this->resolver->getStates($conditions, 'body', ':input[name="status"]');

    $this->assertArrayHasKey('#states', $result);
    $this->assertArrayHasKey('invisible', $result['#states']);
    $this->assertCount(1, $result['#states']['invisible']);
  }

  /**
   * Show rules win when mixed with hide rules for the same field.
   *
   * @covers ::getStates
   */
  public function testResolveStatesShowWinsOverHide(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show'],
      ['field' => 'body', 'state' => 'archived', 'visibility' => 'hide'],
    ];
    $result = $this->resolver->getStates($conditions, 'body', ':input[name="status"]');

    $this->assertArrayHasKey('visible', $result['#states']);
    $this->assertArrayNotHasKey('invisible', $result['#states']);
  }

  /**
   * @covers ::getStates
   */
  public function testResolveStatesDefaultVisibilityIsShow(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft'],
    ];
    $result = $this->resolver->getStates($conditions, 'body', ':input[name="status"]');

    $this->assertArrayHasKey('visible', $result['#states']);
  }

  /**
   * @covers ::getReferencedFields
   */
  public function testGetReferencedFieldsReturnsUniqueFieldNames(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show'],
      ['field' => 'title', 'state' => 'published', 'visibility' => 'hide'],
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show'],
    ];
    $result = $this->resolver->getReferencedFields($conditions);
    sort($result);
    $this->assertSame(['body', 'title'], $result);
  }

  /**
   * @covers ::getReferencedFields
   */
  public function testGetReferencedFieldsIgnoresRulesWithoutField(): void {
    $conditions = [
      ['state' => 'draft', 'visibility' => 'show'],
      ['field' => '', 'state' => 'draft', 'visibility' => 'show'],
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show'],
    ];
    $result = $this->resolver->getReferencedFields($conditions);
    $this->assertSame(['body'], $result);
  }

  /**
   * @covers ::isFieldRequiredForState
   */
  public function testFieldIsRequiredWhenExplicitlyShownAndRequired(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE],
    ];
    $this->assertTrue($this->resolver->isFieldRequiredForState($conditions, 'body', 'published'));
  }

  /**
   * @covers ::isFieldRequiredForState
   */
  public function testFieldIsNotRequiredWhenShowRuleExistsButNotForTargetState(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show', 'required' => TRUE],
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show'],
    ];
    // Required flag is only on 'draft', not on 'published'.
    $this->assertFalse($this->resolver->isFieldRequiredForState($conditions, 'body', 'published'));
  }

  /**
   * @covers ::isFieldRequiredForState
   */
  public function testFieldIsNotRequiredWhenHiddenForTargetState(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'archived', 'visibility' => 'hide', 'required' => TRUE],
    ];
    $this->assertFalse($this->resolver->isFieldRequiredForState($conditions, 'body', 'archived'));
  }

  /**
   * @covers ::isFieldRequiredForState
   */
  public function testFieldIsNotRequiredWhenRequiredFlagMissing(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show'],
    ];
    $this->assertFalse($this->resolver->isFieldRequiredForState($conditions, 'body', 'published'));
  }

  /**
   * When a show rule exists, required only applies in the explicitly shown state.
   *
   * @covers ::isFieldRequiredForState
   */
  public function testShowRuleWhitelistMakesFieldNotRequiredInUnlistedState(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show', 'required' => TRUE],
    ];
    // 'published' is not in the show whitelist → field is not visible there.
    $this->assertFalse($this->resolver->isFieldRequiredForState($conditions, 'body', 'published'));
  }

  /**
   * @covers ::getRequiredFields
   */
  public function testGetRequiredFieldsReturnsOnlyFieldsRequiredForTargetState(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE],
      ['field' => 'title', 'state' => 'draft', 'visibility' => 'show', 'required' => TRUE],
    ];
    $result = $this->resolver->getRequiredFields($conditions, 'published');
    $this->assertSame(['body'], $result);
  }

  /**
   * @covers ::getRequiredFields
   */
  public function testGetRequiredFieldsReturnsEmptyArrayWhenNoneRequired(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show'],
    ];
    $result = $this->resolver->getRequiredFields($conditions, 'published');
    $this->assertSame([], $result);
  }

  /**
   * @covers ::getRequiredFields
   */
  public function testGetRequiredFieldsDeduplicatesFields(): void {
    $conditions = [
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE],
      ['field' => 'body', 'state' => 'published', 'visibility' => 'show', 'required' => TRUE],
    ];
    $result = $this->resolver->getRequiredFields($conditions, 'published');
    $this->assertSame(['body'], $result);
  }

  /**
   * @covers ::getStates
   */
  public function testResolveStatesSelectorEmbeddedCorrectly(): void {
    $selector = ':input[name="field_status[0][value]"]';
    $conditions = [
      ['field' => 'body', 'state' => 'draft', 'visibility' => 'show'],
    ];
    $result = $this->resolver->getStates($conditions, 'body', $selector);

    $visible = $result['#states']['visible'][0];
    $this->assertArrayHasKey($selector, $visible);
  }

}
