<?php

declare(strict_types=1);

namespace Baraja\Search;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\Entity;
use Ramsey\Uuid\Uuid;

final class AnalyticsUuidGenerator extends AbstractIdGenerator
{
	/**
	 * @param Entity|null $entity
	 */
	public function generate(EntityManager $em, $entity): string
	{
		try {
			return Uuid::uuid4()->toString();
		} catch (\Throwable $e) {
			throw new \RuntimeException('Can not generate UUID: ' . $e->getMessage(), $e->getCode(), $e);
		}
	}
}
