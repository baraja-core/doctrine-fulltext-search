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
	 * @param mixed[] $entityMap
	 * @return string[][]
	 */
	public static function normalize(array $entityMap, EntityManagerInterface $em): array
	{
		$return = [];
		foreach ($entityMap as $entityName => $columns) {
			if (\is_string($columns) === true) {
				$columns = [$columns];
			} elseif (\is_array($columns) === false) {
				throw new \InvalidArgumentException('Column definition is not valid column array (must be string or array), but type "' . \gettype($columns) . '" given.');
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
	 * @param string[] $entityProperties
	 */
	private static function checkColumnIsValidProperty(
		string $column,
		string $entityName,
		array $entityProperties
	): void {
		if (\in_array(preg_replace('/^(?:\([^)]*\)|[^a-zA-Z0-9]*)([^.]+)(?:\..+)?$/', '$1', $column), $entityProperties, true) === true) {
			return;
		}

		sort($entityProperties);
		$hint = Helpers::getSuggestion($entityProperties, $column);
		throw new \InvalidArgumentException(
			'Column "' . preg_replace('/^[:!_]/', '', $column) . '" is not valid property of "' . $entityName . '".'
			. "\n" . 'Did you mean ' . ($hint !== null ? '"' . $hint . '"' : '"' . implode('", "', $entityProperties) . '"') . '?',
		);
	}
}
