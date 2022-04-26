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
	public function __construct(
		private QueryBuilder $queryBuilder,
		private IScoreCalculator $scoreCalculator,
	) {
	}


	/**
	 * Based on the user query, the database entity, and the constraints, this method finds a list of candidate results.
	 * The candidates can be sorted in any order regardless of relevance.
	 *
	 * @param array<int, string> $columns
	 * @param array<int, string> $userConditions
	 * @return array<int, SearchItem>
	 */
	public function processCandidateSearch(string $query, string $entity, array $columns, array $userConditions): array
	{
		$columnGetters = $this->getColumnGetters($columns);
		$query = strtolower(trim(Helpers::toAscii($query, useCache: true)));

		/** @var object[] $candidateResults */
		$candidateResults = $this->queryBuilder
			->build($query, $entity, $columns, $userConditions)
			->getQuery()
			->getResult();

		$return = [];
		foreach ($candidateResults as $candidateResult) {
			$finalScore = 0;
			$snippets = [];
			$title = null;
			foreach ($columns as $column) {
				$mode = $column[0] ?? '';
				if ($mode === '_') {
					continue;
				}
				$getterColumn = $columnGetters[$column];
				if (str_contains($getterColumn, '.') === true) { // relation
					$rawColumnValue = $this->getValueByRelation($getterColumn, $candidateResult);
				} else { // scalar field
					$getter = 'get' . $getterColumn;
					$candidateResultClass = $candidateResult::class;
					$emptyRequiredParameters = true;
					$methodExist = false;
					try { // is available by getter?
						$methodRef = new \ReflectionMethod($candidateResultClass, $getter);
						foreach ($methodRef->getParameters() as $parameter) {
							if ($parameter->isOptional() === false) {
								$emptyRequiredParameters = false;
								break;
							}
						}
						$methodExist = true;
					} catch (\ReflectionException) {
						// Silence is golden.
					}

					if ($methodExist === false) { // method does not exist, but it is a magic?
						$columnDatabaseValue = null;
						$magicGetters = $this->getMagicMethodsByClass($candidateResult, 'get');
						if (in_array($getter, $magicGetters, true)) {
							try {
								// Get data from magic getter
								/** @phpstan-ignore-next-line */
								$columnDatabaseValue = $candidateResult->$getter();
							} catch (\Throwable) {
								// Silence is golden.
							}
						} elseif ($magicGetters === []) {
							throw new \InvalidArgumentException(
								'There are no magic getters in the "' . $entity . '" entity, '
								. 'but getter "' . $getter . '" is mandatory.',
							);
						} else {
							throw new \InvalidArgumentException(
								'Getter "' . $getter . '" in entity "' . $entity . '" does not exist. '
								. 'Did you mean "' . implode('", "', $magicGetters) . '"?',
							);
						}
					} elseif ($emptyRequiredParameters === false) { // Use property loading if method can not be called
						try {
							$propertyRef = new \ReflectionProperty($candidateResultClass, Helpers::firstLower($getterColumn));
							$propertyRef->setAccessible(true);
							$columnDatabaseValue = $propertyRef->getValue($candidateResult);
						} catch (\ReflectionException $e) {
							throw new \RuntimeException(sprintf('Can not read property "%s" from "%s": %s', $column, $candidateResultClass, $e->getMessage()), $e->getCode(), $e);
						}
					} elseif (isset($methodRef)) { // Call native method when contain only optional parameters
						try {
							$columnDatabaseValue = $methodRef->invoke($candidateResult);
						} catch (\ReflectionException $e) {
							throw new \LogicException($e->getMessage(), $e->getCode(), $e);
						}
					} else {
						throw new \LogicException('Method "' . $getter . '" can not be called on "' . $candidateResultClass . '".');
					}
					try {
						$rawColumnValue = $this->hydrateColumnValue($columnDatabaseValue);
					} catch (\InvalidArgumentException $e) {
						throw new \InvalidArgumentException(
							sprintf('Column "%s" of entity "%s" ', $getterColumn, $entity)
							. 'can not be converted to string because the value is not scalar type.' . "\n"
							. 'Advance info: ' . $e->getMessage(),
						);
					}
				}

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

			usort($snippets, static fn(array $a, array $b): int => $a['score'] < $b['score'] ? 1 : -1);

			$snippet = Helpers::implodeSnippets($snippets);
			$return[] = new SearchItem(
				entity: $candidateResult,
				query: $query,
				title: $title ?? Helpers::truncate($snippet, 64),
				snippet: $snippet,
				score: $finalScore,
			);
		}

		return $return;
	}


	/**
	 * Translate getter value to scalar
	 */
	private function hydrateColumnValue(mixed $haystack): string
	{
		if (is_array($haystack)) {
			return implode(', ', $haystack);
		}
		if (is_scalar($haystack) || $haystack === null) {
			return (string) $haystack;
		}
		if (
			is_object($haystack)
			&& ($haystack instanceof \Stringable || method_exists($haystack, '__toString'))
		) {
			return (string) $haystack;
		}

		throw new \InvalidArgumentException(
			'Entity column can not be converted to string. '
			. (is_object($haystack)
				? 'Object type of "' . $haystack::class . '"'
				: 'Type "' . \get_debug_type($haystack) . '"')
			. ' given. Did you mean to use a relation with dot syntax like "relation.targetScalarColumn"?',
		);
	}


	/**
	 * @param array<int, string> $columns
	 * @return array<string, string>
	 */
	private function getColumnGetters(array $columns): array
	{
		$return = [];
		foreach ($columns as $column) {
			if (preg_match('/^(:\([^)]*\)|[^a-zA-Z0-9])?(.+?)(?:\(([^)]*)\))?$/', $column, $columnParser) === 1) {
				$columnNormalize = $columnParser[2] ?? '';
				$columnGetter = $columnParser[3] ?? null;
			} else {
				throw new \InvalidArgumentException('Column "' . $column . '" has invalid syntax.');
			}

			$return[$column] = Helpers::firstUpper($columnGetter ?? $columnNormalize);
		}

		return $return;
	}


	private function getValueByRelation(string $column, ?object $candidateEntity = null): string
	{
		$return = null;
		$columnsIterator = 0;
		$columns = explode('.', $column);
		foreach ($columns as $position => $columnRelation) {
			$columnsIterator++;
			if ($candidateEntity === null) {
				$getterValue = null;
			} elseif (is_string($candidateEntity)) {
				$getterValue = $candidateEntity;
			} else {
				$method = preg_match('/^(?<column>[^(]+)(\((?<getter>[^)]*)\))$/', $columnRelation, $columnParser) === 1
					? 'get' . Helpers::firstUpper($columnParser['getter'])
					: 'get' . Helpers::firstUpper($columnRelation);
				/** @phpstan-ignore-next-line */
				$getterValue = $candidateEntity->{$method}();
			}

			if (is_iterable($getterValue) === true) { // OneToMany or ManyToMany
				$nextColumnsPath = '';
				for ($ci = $columnsIterator; isset($columns[$ci]); $ci++) {
					$nextColumnsPath .= ($nextColumnsPath !== '' ? '.' : '') . $columns[$ci];
				}

				$getterFinalValue = '';
				foreach ($getterValue as $getterItem) {
					$getterFinalValue .= ($getterFinalValue !== '' ? '; ' : '')
						. $this->getValueByRelation($nextColumnsPath, $getterItem);
				}

				$return = ($getterValue = $getterFinalValue);
			} elseif ($getterValue instanceof \Stringable) { // Stringable value
				$return = (string) $getterValue;
			} elseif (is_object($getterValue)) { // ManyToOne or OneToOne
				$columnTrace = [];
				foreach ($columns as $positionItem => $columnItem) {
					if ($positionItem > $position) {
						$columnTrace[] = $columnItem;
					}
				}

				return $this->getValueByRelation(implode('.', $columnTrace), $getterValue);
			} elseif (is_scalar($getterValue) || $getterValue === null) { // Scalar value
				$return = (string) $getterValue;
			} else {
				trigger_error('Type "' . \get_debug_type($getterValue) . '" can not be converted to string. Did you implement __toString() method?');
			}

			/** @var string|object|null $getterValue */
			$candidateEntity = $getterValue;

			if (is_scalar($getterValue) === true || $getterValue === null) {
				break;
			}
		}

		return $return ?? '';
	}


	/**
	 * @return array<int, string>
	 */
	private function getMagicMethodsByClass(object $entity, ?string $prefix = null): array
	{
		/** @var array<class-string, array<int, string>> $cache */
		static $cache = [];
		$class = $entity::class;
		if (class_exists($class) === false) {
			throw new \InvalidArgumentException('Class "' . $class . '" does not exist.');
		}
		if (isset($cache[$class]) === false) {
			$classRef = $this->getReflection($entity);
			do {
				if (preg_match_all('~@method\s+(?:\S+\s+)?(\w+)\(~', (string) $classRef->getDocComment(), $parser) > 0) {
					$cache[$class] = array_merge($cache[$class] ?? [], $parser[1] ?? []);
				} else {
					$cache[$class] = [];
				}
				$classRef = $classRef->getParentClass();
			} while ($classRef !== false);
			$cache[$class] = array_unique($cache[$class]);
		}

		$return = [];
		foreach ($cache[$class] as $method) {
			if ($prefix === null || str_starts_with($method, $prefix)) {
				$return[] = $method;
			}
		}

		return $return;
	}


	private function getReflection(object $entity): \ReflectionClass
	{
		static $cache = [];
		$class = $entity::class;
		if (class_exists($class) === false) {
			throw new \InvalidArgumentException('Class "' . $class . '" does not exist.');
		}
		if (isset($cache[$class]) === false) {
			$cache[$class] = new \ReflectionClass($class);
		}

		return $cache[$class];
	}
}
