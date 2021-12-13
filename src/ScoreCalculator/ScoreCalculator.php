<?php

declare(strict_types=1);

namespace Baraja\Search\ScoreCalculator;


final class ScoreCalculator implements IScoreCalculator
{
	/** @var array<int, string> */
	private array $preferredYears;


	public function __construct()
	{
		$this->preferredYears = $this->computePreferredYears();
	}


	public function process(string $haystack, string $query, string $mode = null): int
	{
		$score = 0;
		if (trim($haystack) === '') {
			$score -= 16;
		} elseif ($haystack === $query) {
			$score += 32;
		} elseif ($query !== '' && str_contains($haystack, $query)) { // contains
			$score += 4;
			$subStringCount = substr_count($haystack, $query);
			if ($subStringCount > 0) {
				$score += $subStringCount <= 3 ? $subStringCount : 3;
			}
		} else {
			foreach (explode(' ', $query) as $queryWord) {
				$subStringCount = $queryWord === ''
					? 0
					: substr_count($haystack, $queryWord);
				if ($subStringCount > 0) {
					$score += $subStringCount <= 4 ? $subStringCount : 4;
				}
			}
		}
		$yearBoost = $this->computeYearBoost($haystack);
		if ($yearBoost !== 0) {
			$score *= 1 + $yearBoost;
		}
		if ($mode !== null) {
			if ($mode === ':') {
				$score *= $yearBoost !== 0 ? 10 : 6;
			}
			if ($mode === '!') {
				$score -= 4;
			}
		}

		return $score;
	}


	/**
	 * @return array<int, string>
	 */
	private function computePreferredYears(): array
	{
		$year = (int) date('Y');
		$month = (int) date('m');
		if ($month > 9) {
			return [
				(string) ($year - 1),
				(string) $year,
				(string) ($year + 1),
			];
		}

		return [
			(string) ($year + 1),
			(string) ($year - 1),
			(string) $year,
		];
	}


	private function computeYearBoost(string $haystack): int
	{
		$boost = 0;
		foreach ($this->preferredYears as $key => $year) {
			if (str_contains($haystack, $year)) {
				if ($boost === 0) {
					$boost = 1;
				}
				$boost *= $key + 1;
			}
		}

		return $boost;
	}
}
