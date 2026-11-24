<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

/** What a plan does to its node when it is due. */
enum Action: string
{
	/** The node goes online, with its working copy if it has one. */
	case Publish = 'publish';

	/** The node goes offline. */
	case Unpublish = 'unpublish';
}
