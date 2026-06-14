<?php

declare(strict_types=1);

namespace Drupal\Tests\state_machine_ui\Unit;

use Drupal\state_machine_ui\Constant\FilterLogic;
use Drupal\state_machine_ui\Service\MetadataFilter;
use Drupal\state_machine_ui\Service\WorkflowMetadataReaderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\state_machine_ui\Service\MetadataFilter
 * @group state_machine_ui
 */
final class MetadataFilterTest extends TestCase {

  /**
   * The service under test.
   */
  private MetadataFilter $filter;

  /**
   * The mocked metadata reader.
   */
  private WorkflowMetadataReaderInterface $reader;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->reader = $this->createMock(WorkflowMetadataReaderInterface::class);
    $this->filter = new MetadataFilter($this->reader);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesReturnsAllWhenNoFilters(): void {
    $result = $this->filter->filterStates('wf', ['draft', 'published'], []);
    $this->assertSame(['draft', 'published'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesAndLogicAllKeysMustPass(): void {
    $this->reader->method('getStateValues')->willReturnMap([
      ['wf', 'draft', ['tag' => ['editable'], 'category' => ['article']]],
      ['wf', 'published', ['tag' => ['readonly'], 'category' => ['article']]],
      ['wf', 'archived', ['tag' => ['editable'], 'category' => ['other']]],
    ]);

    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published', 'archived'],
      ['tag' => ['editable'], 'category' => ['article']],
      FilterLogic::And,
    );

    $this->assertSame(['draft'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesOrLogicAnyKeyPasses(): void {
    $this->reader->method('getStateValues')->willReturnMap([
      ['wf', 'draft', ['tag' => ['editable'], 'category' => ['other']]],
      ['wf', 'published', ['tag' => ['readonly'], 'category' => ['article']]],
      ['wf', 'archived', ['tag' => ['readonly'], 'category' => ['other']]],
    ]);

    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published', 'archived'],
      ['tag' => ['editable'], 'category' => ['article']],
      FilterLogic::Or,
    );

    $this->assertSame(['draft', 'published'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesIntraKeyAndAllValuesMustBePresent(): void {
    $this->reader->method('getStateValues')->willReturnMap([
      ['wf', 'draft', ['tag' => ['editable', 'review']]],
      ['wf', 'published', ['tag' => ['editable']]],
    ]);

    // State must have BOTH 'editable' AND 'review'.
    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published'],
      ['tag' => ['editable', 'review']],
    );

    $this->assertSame(['draft'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesExcludesStatesMissingMetadataKey(): void {
    $this->reader->method('getStateValues')->willReturnMap([
      ['wf', 'draft', ['tag' => ['editable']]],
      ['wf', 'published', []],
    ]);

    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published'],
      ['tag' => ['editable']],
    );

    $this->assertSame(['draft'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesSkipsEmptyFilterValues(): void {
    $this->reader->method('getStateValues')->willReturn([]);

    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published'],
      ['tag' => []],
    );

    $this->assertSame(['draft', 'published'], $result);
  }

  /**
   * @covers ::filterTransitions
   */
  public function testFilterTransitionsReturnsAllWhenNoFilters(): void {
    $result = $this->filter->filterTransitions('wf', ['place', 'publish'], []);
    $this->assertSame(['place', 'publish'], $result);
  }

  /**
   * @covers ::filterTransitions
   */
  public function testFilterTransitionsAndLogic(): void {
    $this->reader->method('getTransitionValues')->willReturnMap([
      ['wf', 'place', ['role' => ['editor'], 'level' => ['normal']]],
      ['wf', 'publish', ['role' => ['admin'], 'level' => ['normal']]],
    ]);

    $result = $this->filter->filterTransitions(
      'wf',
      ['place', 'publish'],
      ['role' => ['editor'], 'level' => ['normal']],
      FilterLogic::And,
    );

    $this->assertSame(['place'], $result);
  }

  /**
   * @covers ::filterTransitions
   */
  public function testFilterTransitionsOrLogic(): void {
    $this->reader->method('getTransitionValues')->willReturnMap([
      ['wf', 'place', ['role' => ['editor']]],
      ['wf', 'publish', ['level' => ['normal']]],
      ['wf', 'archive', ['other' => ['x']]],
    ]);

    $result = $this->filter->filterTransitions(
      'wf',
      ['place', 'publish', 'archive'],
      ['role' => ['editor'], 'level' => ['normal']],
      FilterLogic::Or,
    );

    $this->assertSame(['place', 'publish'], $result);
  }

  /**
   * @covers ::filterStates
   */
  public function testFilterStatesReturnsReIndexedArray(): void {
    $this->reader->method('getStateValues')->willReturnMap([
      ['wf', 'draft', ['tag' => ['editable']]],
      ['wf', 'published', []],
      ['wf', 'archived', ['tag' => ['editable']]],
    ]);

    $result = $this->filter->filterStates(
      'wf',
      ['draft', 'published', 'archived'],
      ['tag' => ['editable']],
    );

    $this->assertSame([0 => 'draft', 1 => 'archived'], $result);
  }

}
