<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchItem;
use Baraja\Search\ScoreCalculator\IScoreCalculator;

/**
 * @internal
 */
final class Core
{

	/**
	 * @var QueryBuilder
	 */
	private $queryBuilder;

	/**
	 * @var IScoreCalculator
	 */
	private $scoreCalculator;

	/**
	 * @param QueryBuilder $queryBuilder
	 * @param IScoreCalculator $scoreCalculator
	 */
	public function __construct(QueryBuilder $queryBuilder, IScoreCalculator $scoreCalculator)
	{
		$this->queryBuilder = $queryBuilder;
		$this->scoreCalculator = $scoreCalculator;
	}

	/**
	 * @param string $query
	 * @param string $entity
	 * @param string[] $columns
	 * @return SearchItem[]
	 */
	public function processCandidateSearch(string $query, string $entity, array $columns): array
	{
		$return = [];
		$columnGetters = $this->getColumnGetters($columns);
		$query = strtolower(Helpers::toAscii($query));

		try {
			/** @var object[] $candidateResults */
			$candidateResults = $this->queryBuilder->build($query, $entity, $columns)->getQuery()->getResult();
		} catch (SearchException $e) {
			return [];
		}

		foreach ($candidateResults as $candidateResult) {
			$finalScore = 0;
			$snippets = [];
			$title = null;
			foreach ($columns as $column) {
				$mode = $column[0];
				if (strpos($columnGetters[$column], '.') !== false) {
					$rawColumnValue = $this->getValueByRelation($columnGetters[$column], $candidateResult);
				} else {
					$rawColumnValue = (string) $candidateResult->{'get' . $columnGetters[$column]}();
				}

				$columnValue = strtolower(Helpers::toAscii($rawColumnValue));
				$score = $this->scoreCalculator->process($columnValue, $query, $mode);

				if ($mode === ':' && $title === null) {
					$title = $rawColumnValue;
				}

				if ($mode !== '!') {
					$snippets[] = [
						'haystack' => $rawColumnValue,
						'score' => $score,
					];
				}

				$finalScore += $score;
			}

			usort($snippets, function (array $a, array $b) {
				return $a['score'] < $b['score'] ? 1 : -1;
			});

			$snippet = '';
			foreach ($snippets as $_snippet) {
				$snippet .= ($snippet !== '' ? '; ' : '') . $_snippet['haystack'];
			}

			$return[] = new SearchItem(
				$candidateResult,
				$query,
				$title ?? Helpers::truncate($snippet, 64),
				$snippet,
				$finalScore
			);
		}

		return $return;
	}

	/**
	 * @param string[] $columns
	 * @return string[]
	 */
	private function getColumnGetters(array $columns): array
	{
		$return = [];

		foreach ($columns as $column) {
			$return[$column] = Helpers::firstUpper(
				(string) preg_replace('/^(?:\([^\)]*\)|[^a-zA-Z0-9])/', '', $column)
			);
		}

		return $return;
	}

	/**
	 * @param string $column
	 * @param null $candidateResult
	 * @return string
	 */
	private function getValueByRelation(string $column, $candidateResult = null): string
	{
		$rawColumnValue = '';
		foreach (explode('.', $column) as $columnRelation) {
			if (preg_match('/^(?<column>[^\(]+)(\((?<getter>[^\)]*)\))$/', $columnRelation, $columnParser)) {
				$rawColumnValue = (string) $candidateResult->{'get' . Helpers::firstUpper($columnParser['getter'])}();
			} else {
				$rawColumnValue = (string) $candidateResult->{'get' . Helpers::firstUpper($columnRelation)}();
			}

			$candidateResult = $rawColumnValue;

			if (\is_scalar($rawColumnValue) === true) {
				break;
			}
		}

		return $rawColumnValue;
	}

}