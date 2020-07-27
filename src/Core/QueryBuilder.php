<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

final class QueryBuilder
{
	private const MAX_RESULTS = 1024;

	private const NUMBER_INTERVAL_RANGE = 10;

	/** @var EntityManagerInterface */
	private $entityManager;


	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @param string $query
	 * @param string $entity
	 * @param string[] $columns
	 * @param string[] $userWheres use as AND with automated WHERE.
	 * @return DoctrineQueryBuilder
	 * @throws SearchException
	 */
	public function build(string $query, string $entity, array $columns, array $userWheres): DoctrineQueryBuilder
	{
		if (!$this->entityManager instanceof EntityManager) {
			SearchException::incompatibleEntityManagerInstance($this->entityManager);
		}

		$query = $this->rewriteExactMatch($query);
		$query = $this->rewriteNegativeMatch($query);
		$query = $this->rewriteNumberInterval($query);
		$partialColumns = [];
		$entityColumns = [];
		$containsRelation = false;

		foreach ($columns as $column) {
			$partialColumns[] = ($columnNormalize = (string) preg_replace('/^(?:\([^\)]*\)|[^a-zA-Z0-9])/', '', $column));
			if (($column[0] ?? '') !== '_') {
				$entityColumns[] = 'e.' . $columnNormalize;
			}
			if ($containsRelation === false && strpos($column, '.') !== false) {
				$containsRelation = true;
			}
		}

		$queryBuilder = $this->entityManager
			->getRepository($entity)
			->createQueryBuilder('e')
			->setMaxResults(self::MAX_RESULTS);

		if ($containsRelation === true) {
			$queryBuilder
				->select('e')
				->where($this->buildWhere(
					$query,
					$this->buildJoin($queryBuilder, $partialColumns)
				));
		} else {
			$queryBuilder
				->select('PARTIAL e.{id, ' . implode(', ', $partialColumns) . '}')
				->where($this->buildWhere($query, $entityColumns));
		}

		foreach ($userWheres as $userWhere) {
			$queryBuilder->andWhere($userWhere);
		}

		return $queryBuilder;
	}


	/**
	 * Create complex where statement with automated selector for simple query keywords
	 * and combine with user filters (exact match, number intervals and more).
	 *
	 * Automatic searchable column must be "%column%".
	 *
	 * @param string $query
	 * @param string[] $columns
	 * @param bool $ignoreAccents
	 * @return string compatible with Doctrine
	 * @throws SearchException
	 */
	private function buildWhere(string $query, array $columns, bool $ignoreAccents = false): string
	{
		if ($columns === [] || ($query = trim($query)) === '') {
			return '1=1';
		}

		$whereAnds = []; // Find special user filters and ignore in simple query match
		$simpleQuery = preg_replace_callback('/\{([^\{\}]+)}/', function (array $match) use (&$whereAnds): string {
			$whereAnds[] = $match[1];

			return '';
		}, $query);

		$return = '';
		$simpleQuery = trim((string) preg_replace('/\s+/', ' ', $simpleQuery));
		$simpleQuery = (string) str_replace(
			['.', '?', '"'], ['. ', ' ', '\''],
			$ignoreAccents === true ? Helpers::toAscii($simpleQuery) : $simpleQuery
		);

		// Simple query match with normal keywords in query
		foreach ($simpleQuery !== '' ? explode(' ', $simpleQuery) : [] as $word) {
			$return .= "\n" . ' AND (';
			foreach ($columns as $column) {
				if (@preg_match('/^[a-z0-9\.\_\-\@\(\)\'\, ]{1,100}$/i', $column) !== 1) {
					throw new SearchException('Invalid column name "' . $column . '".');
				}

				$return .= $column . ' LIKE \'%' . $this->escapeLikeString($word) . '%\' OR ';
			}
			$return = substr($return, 0, -4) . ')';
		}

		// Fix generated query
		$return = (string) preg_replace('/^\s*AND/', '', $return);

		// Process user filters and generate it for all searchable columns.
		if ($whereAnds !== []) {
			if ($return !== '') {
				$return = '(' . $return . ')' . "\n";
			}
			foreach ($whereAnds as $whereAnd) {
				$whereColumns = [];
				foreach ($columns as $column) {
					$whereColumns[] = str_replace('%column%', $column, $whereAnd);
				}
				$return .= ($return !== '' ? ' AND ' : '') . '(' . implode(' OR ', $whereColumns) . ')' . "\n";
			}
		}

		return $return;
	}


	/**
	 * Create most effective join selector by internal magic.
	 *
	 * @param DoctrineQueryBuilder $queryBuilder
	 * @param string[] $partialColumns
	 * @return string[]
	 */
	private function buildJoin(DoctrineQueryBuilder $queryBuilder, array $partialColumns): array
	{
		$virtualRelationColumn = 1;
		$selectorColumns = [];

		foreach ($partialColumns as $partialColumn) {
			if (strpos($partialColumn, '.') !== false) {
				$leftJoinIterator = 1;
				$lastRelationColumn = 'e';
				$countRelationParts = \count($relationParts = explode('.', $partialColumn));
				foreach ($relationParts as $relationPart) {
					$relationPart = (string) preg_replace('/^([^\(]+)(?:\([^\)]*\))?$/', '$1', $relationPart);
					if ($countRelationParts > $leftJoinIterator) {
						$queryBuilder->leftJoin(
							($leftJoinIterator === 1
								? $lastRelationColumn
								: 'c_' . ($virtualRelationColumn - 1)
							) . '.' . $relationPart,
							'c_' . $virtualRelationColumn
						);
						$queryBuilder->addSelect('c_' . $virtualRelationColumn);
					} else {
						$selectorColumns[] = 'c_' . ($virtualRelationColumn - 1) . '.' . $relationPart;
					}

					$lastRelationColumn = $relationPart;
					$leftJoinIterator++;
					$virtualRelationColumn++;
				}
			} else {
				$selectorColumns[] = 'e.' . $partialColumn;
			}
		}

		return $selectorColumns;
	}


	/**
	 * Rewrite exact match in quotes to exact match format.
	 *
	 * For example: "to be or not to be".
	 */
	private function rewriteExactMatch(string $query): string
	{
		return (string) preg_replace_callback('/"([^"]+)"/', function (array $match): string {
			return '{%column% LIKE \'%' . $this->escapeLikeString($match[1]) . '%\'}';
		}, $query);
	}


	/**
	 * Rewrite negative match as word which can not be searched.
	 *
	 * For example: "linux -ubuntu".
	 */
	private function rewriteNegativeMatch(string $query): string
	{
		return (string) preg_replace_callback('/-(\S+)/', function (array $match): string {
			return '{%column% NOT LIKE \'%' . $this->escapeLikeString($match[1]) . '%\'}';
		}, $query);
	}


	/**
	 * Rewrite number interval operator to query.
	 *
	 * This logic supports patterns like (from..to) as integers:
	 *
	 * "2017..2020", "-17..26", "24.....800", "-21..-36", "50..21".
	 */
	private function rewriteNumberInterval(string $query): string
	{
		return (string) preg_replace_callback('/(\s|^)(-?\d+)\.{2,}(-?\d+)(\s|$)/', static function (array $match): string {
			$interval = [];
			$from = (int) $match[2];
			$to = (int) $match[3];

			if ($from > $to) {
				$helper = $from;
				$from = $to;
				$to = $helper;
			}

			if ($to - $from <= self::NUMBER_INTERVAL_RANGE) { // write full interval
				for ($i = $from; $i <= $to; $i++) {
					$interval[] = $i;
				}
			} else { // write partial interval (because large interval can be slow)
				for ($i = $from; $i <= $from + self::NUMBER_INTERVAL_RANGE; $i++) {
					$interval[] = $i;
				}
				for ($i = $to - self::NUMBER_INTERVAL_RANGE; $i <= $to; $i++) {
					$interval[] = $i;
				}
			}

			return $match[1] . ($interval !== [] ? '{(%column% LIKE \'%' . implode('%\' OR %column% LIKE \'%', array_unique($interval)) . '%\')}' : '') . $match[4];
		}, $query);
	}


	/**
	 * Escape user haystack for safe use in LIKE statement.
	 */
	private function escapeLikeString(string $haystack): string
	{
		return str_replace('\\\'', '\'\'', addslashes($haystack));
	}
}
