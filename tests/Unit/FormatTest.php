<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Tests\Unit;

use Cosray\ContentSchedule\Panel\Format;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\ContentSchedule\Panel\Format
 */
final class FormatTest extends TestCase
{
	public function testAnInputIsReadInThePanelsTimeZone(): void
	{
		$format = new Format('de_DE', new DateTimeZone('Europe/Berlin'));

		$parsed = $format->parse('2027-01-01T08:00');

		$this->assertSame(
			'2027-01-01T07:00:00+00:00',
			$parsed?->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
		);
		$this->assertSame('2027-01-01T08:00', $format->input($parsed));
	}

	public function testSecondsABrowserAddsAreDropped(): void
	{
		$format = new Format('en', new DateTimeZone('UTC'));

		$this->assertSame('2027-01-01T08:00:00+00:00', $format->parse('2027-01-01T08:00:30')?->format(DATE_ATOM));
	}

	public function testWhatIsNoTimeIsNone(): void
	{
		$format = new Format('en', new DateTimeZone('UTC'));

		$this->assertNull($format->parse('tomorrow'));
		$this->assertNull($format->parse('2027-02-30T08:00'));
		$this->assertNull($format->parse(''));
	}

	public function testTimesAreShownInThePanelsLocaleAndTimeZone(): void
	{
		$format = new Format('de_DE', new DateTimeZone('Europe/Berlin'));
		$at = new DateTimeImmutable('2026-12-31T23:30:00Z');

		$this->assertSame('2027-01-01', $format->date($at));
		$this->assertSame('Freitag, 1. Januar 2027', $format->day($at));
		$this->assertSame('00:30', $format->time($at));
		$this->assertSame('01.01.2027, 00:30', $format->dateTime($at));
	}
}
