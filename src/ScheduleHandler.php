<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Cosray\Exception\RuntimeException;
use Cosray\Node\Schema\Handler;
use Cosray\Schema\Record;
use Override;
use ReflectionClass;

final class ScheduleHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		if (new ReflectionClass($nodeClass)->getAttributes(Record::class) !== []) {
			throw new RuntimeException("{$nodeClass} is a record, which has no publication to schedule");
		}

		return ['schedule' => true];
	}
}
