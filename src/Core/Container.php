<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Search\QueryNormalizer\IQueryNormalizer;
use Baraja\Search\QueryNormalizer\QueryNormalizer;
use Baraja\Search\ScoreCalculator\IScoreCalculator;
use Baraja\Search\ScoreCalculator\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\FileStorage;
use Nette\Utils\FileSystem;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class Container implements ContainerInterface
{
	private EntityManagerInterface $entityManager;

	private IQueryNormalizer $queryNormalizer;

	private IScoreCalculator $scoreCalculator;

	private Core $core;

	private Cache $cache;

	private Analytics $analytics;

	private ?LoggerInterface $logger;

	private int $searchTimeout;


	public function __construct(
		EntityManagerInterface $entityManager,
		?IQueryNormalizer $queryNormalizer = null,
		?IScoreCalculator $scoreCalculator = null,
		?Storage $cacheStorage = null,
		?LoggerInterface $logger = null,
		?int $searchTimeout = null,
	) {
		$this->entityManager = $entityManager;
		$this->queryNormalizer = $queryNormalizer ?? new QueryNormalizer;
		$this->scoreCalculator = $scoreCalculator ?? new ScoreCalculator;
		$this->core = new Core(new QueryBuilder($entityManager), $this->scoreCalculator);
		if ($cacheStorage === null) {
			$cacheDir = sys_get_temp_dir() . '/baraja-doctrine/' . md5(__FILE__);
			FileSystem::createDir($cacheDir);
			$cacheStorage = new FileStorage($cacheDir);
		}
		$this->cache = new Cache($cacheStorage, 'baraja-doctrine-fulltext-search');
		$this->analytics = new Analytics($this, $entityManager);
		$this->logger = $logger;
		$this->setSearchTimeout($searchTimeout ?? 2_500);
	}


	public function get(string $id): mixed
	{
		throw new \LogicException('Method has not implemented, use direct method.');
	}


	public function has(string $id): bool
	{
		return true;
	}


	public function getEntityManager(): EntityManagerInterface
	{
		return $this->entityManager;
	}


	public function getQueryNormalizer(): IQueryNormalizer
	{
		return $this->queryNormalizer;
	}


	public function getScoreCalculator(): IScoreCalculator
	{
		return $this->scoreCalculator;
	}


	public function getCore(): Core
	{
		return $this->core;
	}


	public function getCache(): Cache
	{
		return $this->cache;
	}


	public function getAnalytics(): Analytics
	{
		return $this->analytics;
	}


	public function getLogger(): ?LoggerInterface
	{
		return $this->logger;
	}


	public function getSearchTimeout(): int
	{
		return $this->searchTimeout;
	}


	public function setSearchTimeout(int $searchTimeout): void
	{
		if ($searchTimeout < 0) {
			$searchTimeout = 0;
		}
		$this->searchTimeout = $searchTimeout;
	}
}
