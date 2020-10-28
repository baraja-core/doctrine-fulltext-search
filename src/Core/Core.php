<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\Entity\SearchItem;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Nette\Utils\Strings;

/**
 * @internal
 */
final class Core
{
	private QueryBuilder $queryBuilder;

	private IScoreCalculator $scoreCalculator;


	public function __construct(QueryBuilder $queryBuilder, IScoreCalculator $scoreCalculator)
	{
		$this->queryBuilder = $queryBuilder;
		$this->scoreCalculator = $scoreCalculator;
	}


	/**
	 * @param string[] $columns
	 * @param string[] $userWheres
	 * @return SearchItem[]
	 */
	public function processCandidateSearch(string $query, string $entity, array $columns, array $userWheres): array
	{
		$return = [];
		$columnGetters = $this->getColumnGetters($columns);
		$query = strtolower(trim(Strings::toAscii($query)));

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
				} else {
					$methodName = 'get' . $columnGetters[$column];
					$emptyRequiredParameters = true;
					try {
						foreach ((new \ReflectionMethod(\get_class($candidateResult), $methodName))->getParameters() as $parameter) {
							if ($parameter->isOptional() === false) {
								$emptyRequiredParameters = false;
								break;
							}
						}
					} catch (\ReflectionException $e) {
					}

					if ($emptyRequiredParameters === false) { // Use property loading if method can not be called
						try {
							$propertyRef = new \ReflectionProperty(\get_class($candidateResult), Strings::firstLower($columnGetters[$column]));
							$propertyRef->setAccessible(true);
							$columnDatabaseValue = $propertyRef->getValue($candidateResult);
						} catch (\ReflectionException $e) {
							throw new \RuntimeException('Can not read property "' . $column . '" from "' . \get_class($candidateResult) . '": ' . $e->getMessage(), $e->getCode(), $e);
						}
					} else { // Call native method when contain only optional parameters
						$columnDatabaseValue = $candidateResult->{$methodName}();
					}
					if (\is_array($columnDatabaseValue) === true) {
						$rawColumnValue = implode(', ', $columnDatabaseValue);
					} else {
						$rawColumnValue = (string) $columnDatabaseValue;
					}
				}

				if (($mode = $column[0] ?? '') !== '_') {
					$score = $this->scoreCalculator->process(strtolower(Strings::toAscii($rawColumnValue)), $query, $mode);

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

			usort($snippets, fn (array $a, array $b): int => $a['score'] < $b['score'] ? 1 : -1);

			$snippet = Helpers::implodeSnippets($snippets);
			$return[] = new SearchItem(
				$candidateResult,
				$query,
				$title ?? Strings::truncate($snippet, 64),
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
			$return[$column] = Strings::firstUpper(
				(string) preg_replace('/^(?:\([^\)]*\)|[^a-zA-Z0-9])/', '', $column)
			);
		}

		return $return;
	}


	/**
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
				? $candidateEntity->{'get' . Strings::firstUpper($columnParser['getter'])}()
				: $candidateEntity->{'get' . Strings::firstUpper($columnRelation)}();

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
