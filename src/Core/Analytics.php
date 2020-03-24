<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Baraja\Search\Entity\SearchQuery;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Caching\Cache;

final class Analytics
{

	/** @var EntityManagerInterface */
	private $entityManager;

	/** @var Cache */
	private $cache;


	/**
	 * @param EntityManagerInterface $entityManager
	 * @param Cache $cache
	 */
	public function __construct(EntityManagerInterface $entityManager, Cache $cache)
	{
		$this->entityManager = $entityManager;
		$this->cache = $cache;
	}


	/**
	 * @param string $query
	 * @param int $results
	 */
	public function save(string $query, int $results): void
	{
		($queryEntity = $this->getSearchQuery(Helpers::toAscii($query), $results))
			->addFrequency()
			->setResults($results)
			->setScore($this->countScore($queryEntity->getFrequency(), $results))
			->setUpdatedNow();

		$this->entityManager->flush();
	}


	/**
	 * Return array, key is query, value is last.
	 *
	 * @param string|null $query
	 * @return int[]
	 */
	public function getQueryScore(string $query = null): array
	{
		if (!$this->entityManager instanceof EntityManager) {
			return [];
		}

		$queryBuilder = $this->entityManager->getRepository(SearchQuery::class)
			->createQueryBuilder('query')
			->select('PARTIAL query.{id, query, score}');

		if ($query !== null) {
			$queryBuilder->where('query.query LIKE :query')
				->setParameter('query', Helpers::substring($query, 0, 3) . '%');
		}

		/** @var string[][] $result */
		$result = $queryBuilder->getQuery()->getArrayResult();

		$return = [];

		foreach ($result as $_query) {
			$return[$_query['query']] = $_query['score'];
		}

		return $return;
	}


	/**
	 * @param int $frequency
	 * @param int $results
	 * @return int
	 */
	private function countScore(int $frequency, int $results): int
	{
		static $cache;

		if ($cache === null) {
			try {
				$cache = $this->entityManager->getConnection()
					->executeQuery(
						'SELECT MAX(frequency) AS frequency, MAX(results) AS results '
						. 'FROM search__search_query'
					)->fetch();
			} catch (DBALException $e) {
			}
		}

		$score = (int) ((1 / 2) * (
				31 * (
					atan(
						15 * (
						($resultsMax = (int) ($cache['results'] ?? 0)) === 0
							? 1
							: ($results - ($resultsMax / 2)) / $resultsMax
						)
					) + M_PI_2
				)
				+ 31 * (
					atan(
						3 * (
						($frequencyMax = (int) ($cache['frequency'] ?? 0)) === 0
							? 1
							: ($frequency - ($frequencyMax / 2)) / $frequencyMax
						)
					) + M_PI_2
				)
			)
		);

		if ($score > 100) {
			return 100;
		}

		if ($score < 0) {
			return 0;
		}

		return $score;
	}


	/**
	 * @param string $query
	 * @param int $results
	 * @return SearchQuery
	 */
	private function getSearchQuery(string $query, int $results): SearchQuery
	{
		static $cache = [];
		$cacheKey = 'analyticsSearchQuery-' . md5($query);
		$ttl = 0;

		while ($this->cache->load($cacheKey) !== null && $ttl <= 100) { // Conflict treatment
			usleep(50000);
			$ttl++;

			try {
				$cache[$query] = $this->selectSearchQuery($query);
				break;
			} catch (NoResultException | NonUniqueResultException $e) {
			}
		}

		if (isset($cache[$query]) === false) {
			while (true) {
				try {
					$cache[$query] = $this->selectSearchQuery($query);
					break;
				} catch (NoResultException | NonUniqueResultException $e) {
					try {
						usleep(random_int(1, 250) * 1000);
					} catch (\Throwable $e) {
						usleep(200000);
					}

					if ($this->cache->load($cacheKey) === null) {
						$this->cache->save($cacheKey, \time(), [
							Cache::EXPIRE => '5 seconds',
						]);

						$cache[$query] = new SearchQuery($query, $results, $this->countScore(1, $results));
						$this->entityManager->persist($cache[$query]);
						$this->entityManager->flush();
						break;
					}
				}
			}
		}

		return $cache[$query];
	}


	/**
	 * @param string $query
	 * @return SearchQuery
	 * @throws NoResultException|NonUniqueResultException
	 */
	private function selectSearchQuery(string $query): SearchQuery
	{
		if (!$this->entityManager instanceof EntityManager) {
			$searchQuery = $this->entityManager
				->getRepository(SearchQuery::class)
				->findOneBy(['query' => $query]);

			if ($searchQuery instanceof SearchQuery) {
				return $searchQuery;
			}

			throw new \RuntimeException(
				'SearchQuery entity must be instance of "' . SearchQuery::class . '", '
				. 'but ' . (\is_object($searchQuery) === true
					? 'instance of "' . \get_class($searchQuery) . '"'
					: 'type "' . \gettype($searchQuery) . '"'
				) . ' given.'
			);
		}

		return $this->entityManager->getRepository(SearchQuery::class)
			->createQueryBuilder('searchQuery')
			->where('searchQuery.query = :query')
			->setParameter('query', $query)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}
}