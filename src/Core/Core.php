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

	/** @var QueryBuilder */
	private $queryBuilder;

	/** @var IScoreCalculator */
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
	 * @param string[] $userWheres
	 * @return SearchItem[]
	 */
	public function processCandidateSearch(string $query, string $entity, array $columns, array $userWheres): array
	{
		$return = [];
		$columnGetters = $this->getColumnGetters($columns);
		$query = strtolower(trim(Helpers::toAscii($query)));

		try {
			/** @var object[] $candidateResults */
			$candidateResults = $this->queryBuilder->build($query, $entity, $columns, $userWheres)->getQuery()->getResult();
		} catch (SearchException $e) {
			return [];
		}

		foreach ($candidateResults as $candidateResult) {
			$finalScore = 0;
			$snippets = [];
			$title = null;
			foreach ($columns as $column) {
				if (strpos($columnGetters[$column], '.') !== false) {
					$rawColumnValue = $this->getValueByRelation($columnGetters[$column], $candidateResult);
				} elseif (\is_array($getterValue = $candidateResult->{'get' . $columnGetters[$column]}()) === true) {
					$rawColumnValue = implode(', ', $getterValue);
				} else {
					$rawColumnValue = (string) $getterValue;
				}

				if (($mode = $column[0] ?? '') !== '_') {
					$score = $this->scoreCalculator->process(strtolower(Helpers::toAscii($rawColumnValue)), $query, $mode);

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
			}

			usort($snippets, function (array $a, array $b) {
				return $a['score'] < $b['score'] ? 1 : -1;
			});

			$snippet = Helpers::implodeSnippets($snippets);

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
	 * @param object|null $candidateEntity
	 * @return string
	 */
	private function getValueByRelation(string $column, $candidateEntity = null): string
	{
		$getterValue = '';
		$columnsIterator = 0;

		foreach ($columns = explode('.', $column) as $columnRelation) {
			$columnsIterator++;
			$getterValue = preg_match('/^(?<column>[^\(]+)(\((?<getter>[^\)]*)\))$/', $columnRelation, $columnParser)
				? $candidateEntity->{'get' . Helpers::firstUpper($columnParser['getter'])}()
				: $candidateEntity->{'get' . Helpers::firstUpper($columnRelation)}();

			if (is_iterable($getterValue) === true) {
				$nextColumnsPath = '';
				for ($ci = $columnsIterator; isset($columns[$ci]); $ci++) {
					$nextColumnsPath .= ($nextColumnsPath ? '.' : '') . $columns[$ci];
				}

				$getterFinalValue = '';

				foreach ($getterValue as $getterItem) {
					$getterFinalValue .= ($getterFinalValue ? '; ' : '') . $this->getValueByRelation($nextColumnsPath, $getterItem);
				}

				$getterValue = $getterFinalValue;
			}

			/** @var string|null|object $getterValue */
			$candidateEntity = $getterValue;

			if (\is_scalar($getterValue) === true) {
				break;
			}
		}

		return $getterValue;
	}
}
