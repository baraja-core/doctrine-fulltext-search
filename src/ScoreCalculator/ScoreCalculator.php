<?php

declare(strict_types=1);

namespace Baraja\Search\ScoreCalculator;


final class ScoreCalculator implements IScoreCalculator
{
	public function process(string $haystack, string $query, string $mode = null): int
	{
		$score = 0;
		if (trim($haystack) === '') {
			$score -= 16;
		} elseif ($haystack === $query) {
			$score += 32;
		} elseif ($query !== '' && strpos($haystack, $query) !== false) { // contains
			$score += 4;
			if (($subStringCount = substr_count($haystack, $query)) > 0) {
				$score += $subStringCount <= 3 ? $subStringCount : 3;
			}
		} else {
			foreach (explode(' ', $query) as $queryWord) {
				if ($queryWord !== '' && ($subStringCount = substr_count($haystack, $queryWord)) > 0) {
					$score += $subStringCount <= 4 ? $subStringCount : 4;
				}
			}
		}
		if ($mode !== null) {
			if ($mode === ':') {
				$score *= 6;
			}
			if ($mode === '!') {
				$score -= 4;
			}
		}

		return $score;
	}
}
