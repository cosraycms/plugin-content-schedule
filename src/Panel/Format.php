<?php

declare(strict_types=1);

namespace Cosray\ContentSchedule\Panel;

use Celema\Core\Request;
use Cosray\Config;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;

/**
 * Times as the panel shows and reads them: in the user's format locale
 * and the panel's time zone, which the request carries.
 */
final readonly class Format
{
	/** The value of a `datetime-local` input, to the minute. */
	private const string INPUT = 'Y-m-d\TH:i';

	public function __construct(
		public string $locale,
		public DateTimeZone $timezone,
	) {}

	public static function of(?Request $request, Config $config): self
	{
		$locale = $request?->get('formatLocale', null);
		$timezone = $request?->get('panelTimezone', null);

		return new self(
			is_string($locale) ? $locale : 'en',
			$timezone instanceof DateTimeZone ? $timezone : $config->app->timezone,
		);
	}

	public function dateTime(DateTimeInterface $at): string
	{
		return $this->format($at, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
	}

	/** The day with its weekday, as a heading over what happens on it. */
	public function day(DateTimeInterface $at): string
	{
		return $this->format($at, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
	}

	public function time(DateTimeInterface $at): string
	{
		return $this->format($at, IntlDateFormatter::NONE, IntlDateFormatter::SHORT);
	}

	/** The day in the panel's time zone, to group times by. */
	public function date(DateTimeInterface $at): string
	{
		return DateTimeImmutable::createFromInterface($at)->setTimezone($this->timezone)->format('Y-m-d');
	}

	public function input(DateTimeInterface $at): string
	{
		return DateTimeImmutable::createFromInterface($at)->setTimezone($this->timezone)->format(self::INPUT);
	}

	/** The time a `datetime-local` input sent, null when it is none. */
	public function parse(string $value): ?DateTimeImmutable
	{
		// Browsers add seconds once a step asks for them.
		$parsed = DateTimeImmutable::createFromFormat('!' . self::INPUT, substr(trim($value), 0, 16), $this->timezone);
		$errors = DateTimeImmutable::getLastErrors();

		if ($parsed === false || $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
			return null;
		}

		return $parsed;
	}

	private function format(DateTimeInterface $at, int $date, int $time): string
	{
		$formatted = new IntlDateFormatter($this->locale, $date, $time, $this->timezone)->format($at);

		return is_string($formatted) ? $formatted : $at->format(DATE_ATOM);
	}
}
