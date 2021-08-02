<?php

declare(strict_types=1);

namespace Baraja\Search;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Ramsey\Uuid\Uuid;

final class AnalyticsUuidGenerator extends AbstractIdGenerator
{
	/**
	 * @param object|null $entity
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
