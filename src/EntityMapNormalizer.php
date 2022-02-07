<?php

declare(strict_types=1);

namespace Baraja\Search;


use Doctrine\ORM\EntityManagerInterface;

final class EntityMapNormalizer
{
	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . self::class . ' is static and cannot be instantiated.');
	}


	/**
	 * @param array<string, string|array<int, string>> $entityMap
	 * @return array<string, array<int, string>>
	 */
	public static function normalize(array $entityMap, EntityManagerInterface $em): array
	{
		if ($entityMap === []) {
			throw new \InvalidArgumentException('Entity map can not be empty. Did you configured your query?');
		}
		$return = [];
		foreach ($entityMap as $entityName => $columns) {
			if (\is_string($columns) === true) {
				$columns = [$columns];
			}

			$entityProperties = array_keys($em->getClassMetadata($entityName)->getReflectionProperties());
			foreach ($columns as $column) {
				self::checkColumnIsValidProperty($column, $entityName, $entityProperties);
			}

			$return[$entityName] = $columns;
		}

		return $return;
	}


	/**
	 * @param array<int, string> $entityProperties
	 */
	private static function checkColumnIsValidProperty(
		string $column,
		string $entityName,
		array $entityProperties,
	): void {
		if (\in_array(preg_replace('/^(?:\([^)]*\)|[^a-zA-Z0-9]*)([^.]+?)(?:\..+)?(?:\([^)]*\))?$/', '$1', $column), $entityProperties, true) === true) {
			return;
		}

		sort($entityProperties);
		$hint = Helpers::getSuggestion($entityProperties, $column);
		throw new \InvalidArgumentException(
			sprintf(
				'Column "%s" is not valid property of "%s".' . "\n" . 'Did you mean %s?',
				preg_replace('/^[:!_]/', '', $column),
				$entityName,
				$hint !== null ? '"' . $hint . '"' : '"' . implode('", "', $entityProperties) . '"',
			),
		);
	}
}
