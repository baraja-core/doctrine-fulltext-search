<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Lock\Lock;
use Baraja\Search\Entity\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class Analytics
{
	public function __construct(
		private Container $container,
		private EntityManagerInterface $entityManager,
	) {
	}


	public function save(string $query, int $results): void
	{
		$query = Helpers::toAscii($query);
		$cacheKey = __DIR__ . '/analyticsSearchQuery-' . md5($query);
		$logger = $this->container->getLogger();

		Lock::wait($cacheKey);
		Lock::startTransaction($cacheKey, maxExecutionTimeMs: 5000);

		try {
			$queryEntity = $this->getSearchQuery($query, $results);
			Lock::stopTransaction($cacheKey);
		} catch (\Throwable $e) {
			Lock::stopTransaction($cacheKey);
			if ($logger !== null) {
				$logger->critical($e->getMessage(), ['exception' => $e]);
			}
			return;
		}

		$queryEntity->addFrequency();
		$queryEntity->setResults($results);
		$queryEntity->setScore($this->countScore($queryEntity->getFrequency(), $results));
		$queryEntity->setUpdatedNow();

		try {
			$this->entityManager->getUnitOfWork()->commit($queryEntity);
		} catch (\Throwable $e) {
			if ($logger !== null) {
				$logger->critical($e->getMessage(), ['exception' => $e]);
			}
			trigger_error('Can not save search Analytics: ' . $e->getMessage());
		}
	}


	/**
	 * Return array, key is query, value is last.
	 *
	 * @return array<string, int>
	 */
	public function getQueryScore(?string $query = null): array
	{
		$queryBuilder = (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(SearchQuery::class),
		))
			->createQueryBuilder('query')
			->select('PARTIAL query.{id, query, score}');

		if ($query !== null) {
			$queryBuilder->where('query.query LIKE :query')
				->setParameter('query', mb_substr($query, 0, 3, 'UTF-8') . '%');
		}

		/** @var array<int, array{query: string, score: int}> $result */
		$result = $queryBuilder->getQuery()->getArrayResult();

		$return = [];
		foreach ($result as $_query) {
			$return[$_query['query']] = $_query['score'];
		}

		return $return;
	}


	private function countScore(int $frequency, int $results): int
	{
		static $cache;
		if ($cache === null) {
			try {
				/** @var array{results: int, frequency: int} $cache */
				$cache = $this->entityManager->getConnection()
					->executeQuery(
						'SELECT MAX(frequency) AS frequency, MAX(results) AS results '
						. 'FROM search__search_query',
					)->fetchOne();
			} catch (\Throwable) {
				// Silence is golden.
			}
		}
		if ($cache !== null && isset($cache['results'], $cache['frequency'])) {
			$resultsMax = $cache['results'];
			$frequencyMax = $cache['frequency'];
		} else {
			$resultsMax = 0;
			$frequencyMax = 0;
		}

		// magic </BRJ> scoring algorithm
		$score = (int) (1 / 2 * (
				31 * (
					atan(
						15 * (
						$resultsMax === 0
							? 1
							: ($results - ($resultsMax / 2)) / $resultsMax
						),
					) + M_PI_2
				)
				+ 31 * (
					atan(
						3 * (
						$frequencyMax === 0
							? 1
							: ($frequency - ($frequencyMax / 2)) / $frequencyMax
						),
					) + M_PI_2
				)
			)
		);

		if ($score > 100) {
			$score = 100;
		} elseif ($score < 0) {
			$score = 0;
		}

		return $score;
	}


	private function getSearchQuery(string $query, int $results): SearchQuery
	{
		try {
			$searchQuery = $this->selectSearchQuery($query);
		} catch (NoResultException | NonUniqueResultException) {
			$searchQuery = new SearchQuery($query, $results, $this->countScore(1, $results));
			$this->entityManager->persist($searchQuery);
			$this->entityManager->getUnitOfWork()->commit($searchQuery);
		}

		return $searchQuery;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	private function selectSearchQuery(string $query): SearchQuery
	{
		return (new EntityRepository(
			$this->entityManager,
			$this->entityManager->getClassMetadata(SearchQuery::class),
		))
			->createQueryBuilder('searchQuery')
			->where('searchQuery.query = :query')
			->setParameter('query', $query)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}
}
