<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Unit;

use Cosray\ContentSchedule\Schedule;
use Cosray\ContentSchedule\ScheduleHandler;
use Cosray\ContentSchedule\Tests\Fixtures\Article;
use Cosray\ContentSchedule\Tests\Fixtures\Note;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Schema\Registry;
use Cosray\Node\Types;
use Cosray\Schema\Record;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\ContentSchedule\ScheduleHandler
 */
final class ScheduleHandlerTest extends TestCase
{
	public function testTheAttributeMarksATypeAsScheduled(): void
	{
		$types = $this->types();

		$this->assertTrue($types->get(Article::class, 'schedule', false));
		$this->assertFalse($types->get(Note::class, 'schedule', false));
	}

	public function testARecordCannotBeScheduled(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage(ScheduledRecord::class . ' is a record, which has no publication to schedule');

		$this->types()->get(ScheduledRecord::class, 'schedule', false);
	}

	private function types(): Types
	{
		$registry = Registry::withDefaults();
		$registry->register(Schedule::class, new ScheduleHandler());

		return new Types($registry);
	}
}

#[Record, Schedule]
final class ScheduledRecord {}
