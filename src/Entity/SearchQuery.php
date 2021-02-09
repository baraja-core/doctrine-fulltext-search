<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * @ORM\Entity()
 * @ORM\Table(
 *    name="search__search_query",
 *    indexes={
 *       @Index(name="search__search_query__query_id", columns={"query", "id"}),
 *       @Index(name="search__search_query__results", columns={"results"}),
 *       @Index(name="search__search_query__frequency", columns={"frequency"})
 *    }
 * )
 */
class SearchQuery
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="uuid", unique=true)
	 * @ORM\GeneratedValue(strategy="CUSTOM")
	 * @ORM\CustomIdGenerator(class="\Baraja\Search\AnalyticsUuidGenerator")
	 */
	private ?string $id;

	/** @ORM\Column(type="string", unique=true) */
	private string $query;

	/** @ORM\Column(type="integer") */
	private int $frequency = 1;

	/** @ORM\Column(type="integer") */
	private int $results;

	/** @ORM\Column(type="integer") */
	private int $score;

	/** @ORM\Column(type="datetime") */
	private \DateTimeImmutable $insertedDate;

	/** @ORM\Column(type="datetime", nullable=true) */
	private ?\DateTimeInterface $updatedDate;


	public function __construct(string $query, int $results, int $score = 0)
	{
		$this->query = trim($query);
		$this->results = $results < 0 ? 0 : $results;
		$this->setScore($score);
		try {
			$this->insertedDate = new \DateTimeImmutable('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}
	}


	public function getId(): ?string
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return (string) $this->id;
	}


	public function setId(?string $id = null): void
	{
		throw new \LogicException('Can not set identifier, ID "' . $id . '" given.');
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	public function getFrequency(): int
	{
		return $this->frequency;
	}


	public function addFrequency(int $frequency = 1): self
	{
		$this->frequency += $frequency;

		return $this;
	}


	public function getResults(): int
	{
		return $this->results;
	}


	public function setResults(int $results): self
	{
		$this->results = $results;

		return $this;
	}


	public function getScore(): int
	{
		return $this->score;
	}


	public function setScore(int $score): self
	{
		if ($score < 0) {
			$this->score = 0;
		} elseif ($score > 100) {
			$this->score = 100;
		} else {
			$this->score = $score;
		}

		return $this;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): ?\DateTimeInterface
	{
		return $this->updatedDate;
	}


	public function setUpdatedDate(\DateTimeInterface $updatedDate): self
	{
		$this->updatedDate = $updatedDate;

		return $this;
	}


	public function setUpdatedNow(): self
	{
		try {
			$this->updatedDate = new \DateTime('now');
		} catch (\Throwable $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
		}

		return $this;
	}
}
