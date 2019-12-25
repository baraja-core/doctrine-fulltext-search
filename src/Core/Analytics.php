<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Baraja\Search\Entity\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Caching\Cache;

final class Analytics
{

	/**
	 * @var EntityManagerInterface
	 */
	private $entityManager;

	/**
	 * @var Cache
	 */
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
		$queryEntity = $this->getSearchQuery(Helpers::toAscii($query), $results);
		$queryEntity->addFrequency();
		$queryEntity->setResults($results);
		$queryEntity->setScore($this->countScore($queryEntity->getFrequency(), $results));

		try {
			$queryEntity->setUpdatedDate(new \DateTime('now'));
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}

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
	 * @return int
	 */
	public function getTopFrequency(): int
	{
		if (!$this->entityManager instanceof EntityManager) {
			return 0;
		}

		static $cache;

		if ($cache === null) {
			try {
				$cache = (int) $this->entityManager->getRepository(SearchQuery::class)
					->createQueryBuilder('search')
					->select('MAX(search.frequency)')
					->getQuery()
					->getSingleScalarResult();
			} catch (NonUniqueResultException $e) {
				$cache = 0;
			}
		}

		return $cache;
	}

	/**
	 * @return int
	 */
	public function getTopCountResults(): int
	{
		if (!$this->entityManager instanceof EntityManager) {
			return 0;
		}

		static $cache;

		if ($cache === null) {
			try {
				$cache = (int) $this->entityManager->getRepository(SearchQuery::class)
					->createQueryBuilder('search')
					->select('MAX(search.results)')
					->getQuery()
					->getSingleScalarResult();
			} catch (NonUniqueResultException $e) {
				$cache = 0;
			}
		}

		return $cache;
	}

	/**
	 * @param int $frequency
	 * @param int $results
	 * @return int
	 */
	private function countScore(int $frequency, int $results): int
	{
		$resultsMax = $this->getTopCountResults();
		$frequencyMax = $this->getTopFrequency();

		$score = (int) ((1 / 2) * (
				31 * (
					atan(
						15 * (
						$resultsMax === 0
							? 1
							: ($results - ($resultsMax / 2)) / $resultsMax
						)
					) + M_PI_2
				)
				+ 31 * (
					atan(
						3 * (
						$frequencyMax === 0
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
		/** @var EntityManager $em */
		$em = $this->entityManager;

		while ($this->cache->load($cacheKey) !== null && $ttl <= 100) { // Conflict treatment
			usleep(50000);
			$ttl++;

			try {
				$cache[$query] = $this->selectSearchQuery($query, $em);
				break;
			} catch (NoResultException|NonUniqueResultException $e) {
			}
		}

		if (isset($cache[$query]) === false) {
			while (true) {
				try {
					$cache[$query] = $this->selectSearchQuery($query, $em);
					break;
				} catch (NoResultException|NonUniqueResultException $e) {
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
						$em->persist($cache[$query]);
						$em->flush();
						break;
					}
				}
			}
		}

		return $cache[$query];
	}

	/**
	 * @param string $query
	 * @param EntityManager $em
	 * @return SearchQuery
	 * @throws NoResultException|NonUniqueResultException
	 */
	private function selectSearchQuery(string $query, EntityManager $em): SearchQuery
	{
		return $em->getRepository(SearchQuery::class)
			->createQueryBuilder('searchQuery')
			->where('searchQuery.query = :query')
			->setParameter('query', $query)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}

}