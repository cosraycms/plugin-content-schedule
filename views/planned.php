<?php

declare(strict_types=1);

// The answer to the editor's schedule section: the section as it is now,
// unless the schedule was refused, and what happened. Receives: section,
// kind, message.

$message = u($message ?? null);

if (u($section ?? null) !== null) {
	$this->include('kit/inspector-section', ['section' => $section, 'oob' => true]);
}

if (is_string($message) && $message !== '') {
	$this->include('component/toast', ['kind' => $kind, 'message' => $message]);
}
