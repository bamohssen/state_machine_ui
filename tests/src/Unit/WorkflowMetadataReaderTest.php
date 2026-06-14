<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\state_machine_ui\Service\MetadataParserInterface;
use Drupal\state_machine_ui\Service\WorkflowMetadataReader;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Service\WorkflowMetadataReader
 * @group state_machine_ui
 */
final class WorkflowMetadataReaderTest extends TestCase {

  /**
   * Builds a reader from an ordered list of parser mocks.
   *
   * @param \Drupal\state_machine_ui\Service\MetadataParserInterface[] $parsers
   *   Parsers in priority order; the first matching wins.
   */
  private function makeReader(array $parsers): WorkflowMetadataReader {
    return new WorkflowMetadataReader($parsers);
  }

  /**
   * @covers ::getStateMetadata
   */
  public function testGetStateMetadataReturnsEmptyWhenNoParsersSupport(): void {
    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(FALSE);

    $reader = $this->makeReader([$parser]);
    $this->assertSame([], $reader->getStateMetadata('any_workflow'));
  }

  /**
   * @covers ::getTransitionMetadata
   */
  public function testGetTransitionMetadataReturnsEmptyWhenNoParsersSupport(): void {
    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(FALSE);

    $reader = $this->makeReader([$parser]);
    $this->assertSame([], $reader->getTransitionMetadata('any_workflow'));
  }

  /**
   * @covers ::getStateMetadata
   */
  public function testGetStateMetadataAggregatesAcrossAllStates(): void {
    $parsed = [
      'states' => [
        'draft'     => ['audience' => ['internal'], 'section' => ['news']],
        'published' => ['audience' => ['general'], 'section' => ['news']],
        'archived'  => ['audience' => ['internal']],
      ],
      'transitions' => [],
    ];

    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->method('parse')->willReturn($parsed);

    $reader = $this->makeReader([$parser]);
    $result = $reader->getStateMetadata('article');

    $this->assertEqualsCanonicalizing(['internal', 'general'], $result['audience']);
    $this->assertSame(['news'], $result['section']);
  }

  /**
   * @covers ::getStateValues
   */
  public function testGetStateValuesReturnsMetadataForSpecificState(): void {
    $parsed = [
      'states' => [
        'draft'     => ['audience' => ['internal']],
        'published' => ['audience' => ['general']],
      ],
      'transitions' => [],
    ];

    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->method('parse')->willReturn($parsed);

    $reader = $this->makeReader([$parser]);
    $this->assertSame(['audience' => ['internal']], $reader->getStateValues('article', 'draft'));
    $this->assertSame(['audience' => ['general']], $reader->getStateValues('article', 'published'));
  }

  /**
   * @covers ::getStateValues
   */
  public function testGetStateValuesReturnsEmptyForUnknownState(): void {
    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->method('parse')->willReturn(['states' => [], 'transitions' => []]);

    $reader = $this->makeReader([$parser]);
    $this->assertSame([], $reader->getStateValues('workflow', 'unknown'));
  }

  /**
   * @covers ::getTransitionValues
   */
  public function testGetTransitionValuesReturnsMetadataForSpecificTransition(): void {
    $parsed = [
      'states' => [],
      'transitions' => [
        'publish' => ['priority' => ['high']],
      ],
    ];

    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->method('parse')->willReturn($parsed);

    $reader = $this->makeReader([$parser]);
    $this->assertSame(['priority' => ['high']], $reader->getTransitionValues('workflow', 'publish'));
    $this->assertSame([], $reader->getTransitionValues('workflow', 'nonexistent'));
  }

  /**
   * @covers ::getStateMetadata
   */
  public function testFirstSupportingParserWins(): void {
    $parserA = $this->createMock(MetadataParserInterface::class);
    $parserA->method('supports')->willReturn(TRUE);
    $parserA->method('parse')->willReturn([
      'states'      => ['s1' => ['tag' => ['a']]],
      'transitions' => [],
    ]);

    $parserB = $this->createMock(MetadataParserInterface::class);
    $parserB->expects($this->never())->method('parse');
    $parserB->method('supports')->willReturn(TRUE);

    $reader = $this->makeReader([$parserA, $parserB]);
    $this->assertSame(['a'], $reader->getStateMetadata('wf')['tag']);
  }

  /**
   * @covers ::getStateMetadata
   */
  public function testResultsAreMemoizedPerWorkflowId(): void {
    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->expects($this->once())->method('parse')->willReturn([
      'states'      => ['s1' => ['tag' => ['v']]],
      'transitions' => [],
    ]);

    $reader = $this->makeReader([$parser]);
    $reader->getStateMetadata('wf');
    $reader->getStateMetadata('wf');
  }

  /**
   * @covers ::getStateMetadata
   */
  public function testDifferentWorkflowIdsAreNotMemoizedTogether(): void {
    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->expects($this->exactly(2))->method('parse')->willReturn([
      'states' => [],
      'transitions' => [],
    ]);

    $reader = $this->makeReader([$parser]);
    $reader->getStateMetadata('wf_a');
    $reader->getStateMetadata('wf_b');
  }

  /**
   * @covers ::getTransitionMetadata
   */
  public function testGetTransitionMetadataAggregatesUniqueValues(): void {
    $parsed = [
      'states' => [],
      'transitions' => [
        'publish'   => ['tag' => ['fast', 'tracked']],
        'archive'   => ['tag' => ['tracked']],
      ],
    ];

    $parser = $this->createMock(MetadataParserInterface::class);
    $parser->method('supports')->willReturn(TRUE);
    $parser->method('parse')->willReturn($parsed);

    $reader = $this->makeReader([$parser]);
    $result = $reader->getTransitionMetadata('wf');

    $this->assertEqualsCanonicalizing(['fast', 'tracked'], $result['tag']);
  }

}
