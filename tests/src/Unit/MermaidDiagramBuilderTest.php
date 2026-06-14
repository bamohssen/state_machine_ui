<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\state_machine_ui\Service\MermaidDiagramBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Service\MermaidDiagramBuilder
 * @group state_machine_ui
 */
final class MermaidDiagramBuilderTest extends TestCase {

  /**
   * The service under test.
   */
  private MermaidDiagramBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = new MermaidDiagramBuilder();
  }

  /**
   * @covers ::build
   */
  public function testBuildContainsStateDiagramHeader(): void {
    $result = $this->builder->build([], []);
    $this->assertStringContainsString('stateDiagram-v2', $result);
    $this->assertStringContainsString('direction LR', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildRendersStateLabels(): void {
    $states = [
      ['key' => 'draft', 'label' => 'Draft'],
      ['key' => 'published', 'label' => 'Published'],
    ];
    $result = $this->builder->build($states, []);
    $this->assertStringContainsString('draft : Draft', $result);
    $this->assertStringContainsString('published : Published', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildRendersTransitionArrows(): void {
    $states = [
      ['key' => 'draft', 'label' => 'Draft'],
      ['key' => 'published', 'label' => 'Published'],
    ];
    $transitions = [
      ['label' => 'Publish', 'from' => ['draft'], 'to' => 'published'],
    ];
    $result = $this->builder->build($states, $transitions);
    $this->assertStringContainsString('draft --> published', $result);
    $this->assertStringContainsString(': Publish', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildSkipsStatesWithEmptyKey(): void {
    $states = [
      ['key' => '', 'label' => 'No key'],
      ['key' => 'draft', 'label' => 'Draft'],
    ];
    $result = $this->builder->build($states, []);
    $this->assertStringNotContainsString('No key', $result);
    $this->assertStringContainsString('draft : Draft', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildSkipsTransitionsWithEmptyToKey(): void {
    $transitions = [
      ['label' => 'Orphan', 'from' => ['draft'], 'to' => ''],
    ];
    $result = $this->builder->build([], $transitions);
    $this->assertStringNotContainsString('Orphan', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildHandlesTransitionWithoutLabel(): void {
    $states = [
      ['key' => 'a', 'label' => 'A'],
      ['key' => 'b', 'label' => 'B'],
    ];
    $transitions = [
      ['label' => '', 'from' => ['a'], 'to' => 'b'],
    ];
    $result = $this->builder->build($states, $transitions);
    $this->assertStringContainsString('a --> b', $result);
    // No colon label when transition label is empty.
    $this->assertStringNotContainsString('a --> b :', $result);
  }

  /**
   * @covers ::build
   */
  public function testBuildHandlesMultipleFromStates(): void {
    $transitions = [
      ['label' => 'Archive', 'from' => ['draft', 'published'], 'to' => 'archived'],
    ];
    $result = $this->builder->build([], $transitions);
    $this->assertStringContainsString('draft --> archived', $result);
    $this->assertStringContainsString('published --> archived', $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeStripsColonsFromLabels(): void {
    $states = [['key' => 'draft', 'label' => 'Draft: Step 1']];
    $result = $this->builder->build($states, []);
    $this->assertStringNotContainsString('Draft: Step 1', $result);
    $this->assertStringContainsString('Draft  Step 1', $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeHtmlEncodesAmpersand(): void {
    $states = [['key' => 'draft', 'label' => 'A & B']];
    $result = $this->builder->build($states, []);
    $this->assertStringContainsString('A &amp; B', $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeHtmlEncodesAngleBrackets(): void {
    $states = [['key' => 'draft', 'label' => '<script>xss</script>']];
    $result = $this->builder->build($states, []);
    $this->assertStringNotContainsString('<script>', $result);
    $this->assertStringContainsString('&lt;script&gt;', $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeStripsBackticks(): void {
    $states = [['key' => 'draft', 'label' => 'A`B']];
    $result = $this->builder->build($states, []);
    $this->assertStringContainsString('AB', $result);
    $this->assertStringNotContainsString('`', $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeStripsNewlines(): void {
    $states = [['key' => 'draft', 'label' => "line1\nline2"]];
    $result = $this->builder->build($states, []);
    $this->assertStringNotContainsString("\n\n", $result);
  }

  /**
   * @covers ::build
   */
  public function testSanitizeStripsDoubleQuotes(): void {
    $states = [['key' => 'draft', 'label' => 'Say "hello"']];
    $result = $this->builder->build($states, []);
    $this->assertStringNotContainsString('"hello"', $result);
    $this->assertStringContainsString('Say hello', $result);
  }

  /**
   * @covers ::INLINE_TEMPLATE
   */
  public function testInlineTemplateContainsMermaidClass(): void {
    $this->assertStringContainsString('class="mermaid"', MermaidDiagramBuilder::INLINE_TEMPLATE);
  }

  /**
   * @covers ::INLINE_TEMPLATE
   */
  public function testInlineTemplateUsesRawFilter(): void {
    $this->assertStringContainsString('|raw', MermaidDiagramBuilder::INLINE_TEMPLATE);
  }

}
