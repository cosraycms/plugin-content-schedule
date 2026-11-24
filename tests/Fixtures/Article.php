<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Fixtures;

use Cosray\ContentSchedule\Schedule;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Route;

/** A scheduled type with a route and drafts. */
#[Label('Article'), Route('/articles/{uid}'), Schedule]
final class Article implements Title
{
	#[Label('Title'), Required]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw() ?? '';
	}
}
