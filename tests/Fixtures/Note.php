<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Fixtures;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;

/** A type without a schedule. */
#[Label('Note')]
final class Note implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw() ?? '';
	}
}
