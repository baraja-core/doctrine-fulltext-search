<?php

declare(strict_types=1);

namespace Baraja\Search;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Search\QueryNormalizer\QueryNormalizer;
use Baraja\Search\ScoreCalculator\ScoreCalculator;
use Nette\DI\CompilerExtension;

final class DoctrineFulltextSearchExtension extends CompilerExtension
{
	/**
	 * @return string[]
	 */
	public static function mustBeDefinedBefore(): array
	{
		if (\class_exists('\Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension')) {
			return [OrmAnnotationsExtension::class];
		}

		return [];
	}


	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		if (\class_exists('\Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension')) {
			OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Search', __DIR__ . '/Entity');
		}

		$builder->addDefinition($this->prefix('search'))
			->setFactory(Search::class);

		$builder->addDefinition($this->prefix('queryNormalizer'))
			->setFactory(QueryNormalizer::class);

		$builder->addDefinition($this->prefix('scoreCalculator'))
			->setFactory(ScoreCalculator::class);

		$builder->addAccessorDefinition($this->prefix('searchAccessor'))
			->setImplement(SearchAccessor::class);

		$builder->addDefinition($this->prefix('queryBuilder'))
			->setFactory(QueryBuilder::class);
	}
}
