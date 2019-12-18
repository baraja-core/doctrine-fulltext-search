<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\EntityManager;
use Baraja\Search\Entity\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;

final class Analytics
{

	/**
	 * @var EntityManagerInterface
	 */
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
	 * @param int $results
	 */
	public function save(string $query, int $results): void
	{
		/** @var SearchQuery|null $queryEntity */
		$queryEntity = $this->entityManager->getRepository(SearchQuery::class)->findOneBy([
			'query' => $query = Helpers::toAscii($query),
		]);

		if ($queryEntity === null) {
			$queryEntity = new SearchQuery($query, $results, $this->countScore(1, $results));
			$this->entityManager->persist($queryEntity);
		} else {
			$queryEntity->addFrequency();
			$queryEntity->setResults($results);
			$queryEntity->setScore($this->countScore($queryEntity->getFrequency(), $results));

			try {
				$queryEntity->setUpdatedDate(new \DateTime('now'));
			} catch (\Throwable $e) {
				throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		}

		if ($this->entityManager instanceof EntityManager) {
			$this->entityManager->flush($queryEntity);
		} else {
			$this->entityManager->flush();
		}
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

}