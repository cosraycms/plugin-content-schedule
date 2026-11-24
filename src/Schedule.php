<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule;

use Attribute;

/**
 * Lets editors plan when a node of this type goes online, goes offline or
 * its draft goes live. A `#[Record]` has no publication and takes none.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Schedule {}
