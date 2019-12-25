<?php

declare(strict_types=1);

namespace Baraja\Search;


final class SimilarSearch
{

	/**
	 * @var Analytics
	 */
	private $analytics;

	/**
	 * @param Analytics $analytics
	 */
	public function __construct(Analytics $analytics)
	{
		$this->analytics = $analytics;
	}

	/**
	 * @param string $query
	 * @return string|null
	 */
	public function findSimilarQuery(string $query): ?string
	{
		$similarCandidates = [];
		$queryScore = $this->analytics->getQueryScore($query);

		for ($i = ($length = Helpers::length($query)) - 1; $i > 0; $i--) {
			$part = Helpers::substring($query, 0, $i);
			foreach ($queryScore as $_query => $score) {
				if (strncmp($q = (string) $_query, $part, \strlen($part)) === 0) {
					$similarCandidates[$q] = [
						'query' => $q,
						'score' => $score,
						'levenshtein' => levenshtein($q, $query),
					];
				}
			}

			if ($i <= (int) ($length / 2) || \count($similarCandidates) > 1000) {
				break;
			}
		}

		$candidatesByScore = $similarCandidates;
		$candidatesByLevenshtein = $similarCandidates;

		usort($candidatesByScore, function (array $a, array $b) {
			return $a['score'] < $b['score'] ? 1 : -1;
		});

		usort($candidatesByLevenshtein, function (array $a, array $b) {
			return $a['levenshtein'] > $b['levenshtein'] ? 1 : -1;
		});

		$scores = [];
		$levenshteins = [];

		foreach ($candidatesByScore as $index => $value) {
			$scores[$value['query']] = $index;
		}

		foreach ($candidatesByLevenshtein as $index => $value) {
			$levenshteins[$value['query']] = $index;
		}

		$candidates = [];

		foreach ($similarCandidates as $similarCandidate) {
			$candidates[$similarCandidate['query']] =
				$scores[$similarCandidate['query']] + $levenshteins[$similarCandidate['query']];
		}

		$top = null;
		$minScore = null;
		foreach ($candidates as $candidateQuery => $candidateScore) {
			if ($candidateScore < $minScore || $minScore === null) {
				$minScore = $candidateScore;
				$top = $candidateQuery;
			}
		}

		return ((string) $top) ? : null;
	}

}
