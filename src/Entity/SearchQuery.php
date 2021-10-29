<?php

declare(strict_types=1);

namespace Baraja\Search\Entity;


use Baraja\Search\AnalyticsUuidGenerator;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

#[ORM\Entity]
#[ORM\Table(name: 'search__search_query')]
#[Index(columns: ['query', 'id'], name: 'search__search_query__query_id')]
#[Index(columns: ['results'], name: 'search__search_query__results')]
#[Index(columns: ['frequency'], name: 'search__search_query__frequency')]
class SearchQuery
{
	#[ORM\Id]
	#[ORM\Column(type: 'uuid', unique: true)]
	#[ORM\GeneratedValue(strategy: 'CUSTOM')]
	#[ORM\CustomIdGenerator(class: AnalyticsUuidGenerator::class)]
	private ?string $id = null;

	#[ORM\Column(type: 'string', unique: true)]
	private string $query;

	#[ORM\Column(type: 'integer')]
	private int $frequency = 1;

	#[ORM\Column(type: 'integer')]
	private int $results;

	#[ORM\Column(type: 'integer')]
	private int $score;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $insertedDate;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $updatedDate = null;


	public function __construct(string $query, int $results, int $score = 0)
	{
		$this->query = trim($query);
		$this->results = $results < 0 ? 0 : $results;
		$this->setScore($score);
		$this->insertedDate = new \DateTimeImmutable('now');
	}


	public function getId(): ?string
	{
		if ($this->id === null) {
			throw new \RuntimeException('Entity ID does not exist yet. Did you call ->persist() method first?');
		}

		return $this->id;
	}


	public function getQuery(): string
	{
		return $this->query;
	}


	public function getFrequency(): int
	{
		return $this->frequency;
	}


	public function addFrequency(int $frequency = 1): void
	{
		$this->frequency += $frequency;
	}


	public function getResults(): int
	{
		return $this->results;
	}


	public function setResults(int $results): void
	{
		$this->results = $results;
	}


	public function getScore(): int
	{
		return $this->score;
	}


	public function setScore(int $score): void
	{
		if ($score < 0) {
			$score = 0;
		} elseif ($score > 100) {
			$score = 100;
		}
		$this->score = $score;
	}


	public function getInsertedDate(): \DateTimeImmutable
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): ?\DateTimeInterface
	{
		return $this->updatedDate;
	}


	public function setUpdatedNow(): void
	{
		$this->updatedDate = new \DateTime('now');
	}
}
