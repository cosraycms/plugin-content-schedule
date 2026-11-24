<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Fixtures;

use Cosray\ContentSchedule\Schedule;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Drafts;
use Cosray\Schema\Label;

/** A scheduled type without a route or drafts: active or inactive, saved directly. */
#[Label('Setting'), Drafts(false), Schedule]
final class Setting implements Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw() ?? '';
	}
}
