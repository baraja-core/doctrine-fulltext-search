<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

class QueryBuilder
{
	private const MAX_RESULTS = 1024;

	/** @var EntityManagerInterface */
	private $entityManager;


	/**
	 * @param EntityManagerInterface $entityManager
	 */
	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @param string $query
	 * @param string $entity
	 * @param string[] $columns
	 * @return DoctrineQueryBuilder
	 * @throws SearchException
	 */
	public function build(string $query, string $entity, array $columns): DoctrineQueryBuilder
	{
		if (!$this->entityManager instanceof EntityManager) {
			SearchException::incompatibleEntityManagerInstance($this->entityManager);
		}

		$partialColumns = [];
		$entityColumns = [];
		$containsRelation = false;

		foreach ($columns as $column) {
			$columnNormalize = preg_replace('/^(?:\([^\)]*\)|[^a-zA-Z0-9])/', '', $column);
			$partialColumns[] = $columnNormalize;
			if (($column[0] ?? '') !== '_') {
				$entityColumns[] = 'e.' . $columnNormalize;
			}
			if ($containsRelation === false && strpos($column, '.') !== false) {
				$containsRelation = true;
			}
		}

		if ($containsRelation === true) {
			$queryBuilder = $this->entityManager->getRepository($entity)
				->createQueryBuilder('e')
				->select('e');

			$queryBuilder->where(Helpers::generateFulltextCondition(
				$query,
				$this->buildJoin($queryBuilder, $partialColumns)
			));

			return $queryBuilder->setMaxResults(self::MAX_RESULTS);
		}

		return $this->entityManager->getRepository($entity)
			->createQueryBuilder('e')
			->select('PARTIAL e.{id, ' . implode(', ', $partialColumns) . '}')
			->where(Helpers::generateFulltextCondition($query, $entityColumns))
			->setMaxResults(self::MAX_RESULTS);
	}


	/**
	 * Internal magic logic for build most effective join selector.
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
}