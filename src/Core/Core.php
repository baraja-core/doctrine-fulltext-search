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
	public function __construct(
		private QueryBuilder $queryBuilder,
		private IScoreCalculator $scoreCalculator
	) {
	}


	/**
	 * @param string[] $columns
	 * @param string[] $userConditions
	 * @return SearchItem[]
	 */
	public function processCandidateSearch(string $query, string $entity, array $columns, array $userConditions): array
	{
		$return = [];
		$columnGetters = $this->getColumnGetters($columns);
		$query = strtolower(trim(Strings::toAscii($query)));

		/** @var object[] $candidateResults */
		$candidateResults = $this->queryBuilder->build($query, $entity, $columns, $userConditions)->getQuery()->getResult();

		foreach ($candidateResults as $candidateResult) {
			$finalScore = 0;
			$snippets = [];
			$title = null;
			foreach ($columns as $column) {
				if (($mode = $column[0] ?? '') === '_') {
					continue;
				}
				if (str_contains($columnGetters[$column], '.') === true) {
					$rawColumnValue = $this->getValueByRelation($columnGetters[$column], $candidateResult);
				} else {
					$methodName = 'get' . $columnGetters[$column];
					$emptyRequiredParameters = true;
					try {
						foreach ((new \ReflectionMethod($candidateResult::class, $methodName))->getParameters() as $parameter) {
							if ($parameter->isOptional() === false) {
								$emptyRequiredParameters = false;
								break;
							}
						}
					} catch (\ReflectionException) {
					}

					if ($emptyRequiredParameters === false) { // Use property loading if method can not be called
						try {
							$propertyRef = new \ReflectionProperty($candidateResult::class, Strings::firstLower($columnGetters[$column]));
							$propertyRef->setAccessible(true);
							$columnDatabaseValue = $propertyRef->getValue($candidateResult);
						} catch (\ReflectionException $e) {
							throw new \RuntimeException('Can not read property "' . $column . '" from "' . $candidateResult::class . '": ' . $e->getMessage(), $e->getCode(), $e);
						}
					} else { // Call native method when contain only optional parameters
						$columnDatabaseValue = $candidateResult->{$methodName}();
					}
					if (\is_array($columnDatabaseValue) === true) {
						$rawColumnValue = implode(', ', $columnDatabaseValue);
					} elseif (\is_scalar($columnDatabaseValue) === true || $columnDatabaseValue === null) {
						$rawColumnValue = (string) $columnDatabaseValue;
					} elseif (\is_object($columnDatabaseValue) && method_exists($columnDatabaseValue, '__toString')) {
						$rawColumnValue = (string) $columnDatabaseValue;
					} else {
						throw new \InvalidArgumentException(
							'Column definition error: '
							. 'Column "' . ($columnGetters[$column] ?? $column) . '" of entity "' . $entity . '" '
							. 'can not be converted to string because the value is not scalar type. '
							. (\is_object($columnDatabaseValue)
								? 'Object type of "' . $columnDatabaseValue::class . '"'
								: 'Type "' . \get_debug_type($columnDatabaseValue) . '"')
							. ' given. Did you mean to use a relation with dot syntax like "relation.targetScalarColumn"?',
						);
					}
				}

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

			usort($snippets, static fn(array $a, array $b): int => $a['score'] < $b['score'] ? 1 : -1);

			$snippet = Helpers::implodeSnippets($snippets);
			$return[] = new SearchItem(
				$candidateResult,
				$query,
				$title ?? Strings::truncate($snippet, 64),
				$snippet,
				$finalScore,
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
			if (preg_match('/^(:\([^)]*\)|[^a-zA-Z0-9])?(.+?)(?:\(([^)]*)\))?$/', $column, $columnParser)) {
				$columnNormalize = $columnParser[2] ?? '';
				$columnGetter = $columnParser[3] ?? null;
			} else {
				throw new \InvalidArgumentException('Column "' . $column . '" has invalid syntax.');
			}

			$return[$column] = Strings::firstUpper($columnGetter ?? $columnNormalize);
		}

		return $return;
	}


	private function getValueByRelation(string $column, object |null $candidateEntity = null): string
	{
		$getterValue = null;
		$return = null;
		$columnsIterator = 0;
		foreach ($columns = explode('.', $column) as $position => $columnRelation) {
			$columnsIterator++;
			$getterValue = preg_match('/^(?<column>[^(]+)(\((?<getter>[^)]*)\))$/', $columnRelation, $columnParser)
				? $candidateEntity->{'get' . Strings::firstUpper($columnParser['getter'])}()
				: $candidateEntity->{'get' . Strings::firstUpper($columnRelation)}();

			if (is_iterable($getterValue) === true) { // OneToMany or ManyToMany
				$nextColumnsPath = '';
				for ($ci = $columnsIterator; isset($columns[$ci]); $ci++) {
					$nextColumnsPath .= ($nextColumnsPath ? '.' : '') . $columns[$ci];
				}

				$getterFinalValue = '';
				foreach ($getterValue as $getterItem) {
					$getterFinalValue .= ($getterFinalValue ? '; ' : '') . $this->getValueByRelation($nextColumnsPath, $getterItem);
				}

				$return = ($getterValue = $getterFinalValue);
			} elseif (\is_object($getterValue) && $getterValue instanceof \Stringable) { // Stringable value
				$return = (string) $getterValue;
			} elseif (\is_object($getterValue)) { // ManyToOne or OneToOne
				$columnTrace = [];
				foreach ($columns as $positionItem => $columnItem) {
					if ($positionItem > $position) {
						$columnTrace[] = $columnItem;
					}
				}

				return $this->getValueByRelation(implode('.', $columnTrace), $getterValue);
			} elseif (\is_scalar($getterValue) || $getterValue === null) { // Scalar value
				$return = (string) $getterValue;
			} else {
				trigger_error('Type "' . \get_debug_type($getterValue) . '" can not be converted to string. Did you implement __toString() method?');
			}

			/** @var string|object|null $getterValue */
			$candidateEntity = $getterValue;

			if (\is_scalar($getterValue) === true || $getterValue === null) {
				break;
			}
		}

		return $return ?? '';
	}
}
