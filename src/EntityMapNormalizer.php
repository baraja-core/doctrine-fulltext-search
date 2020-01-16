<?php

declare(strict_types=1);

namespace Baraja\Search;

final class EntityMapNormalizer
{

	/**
	 * @var string[]
	 */
	private static $validPropertyTypes = [
		'Column',
		'OneToOne',
		'OneToMany',
		'ManyToOne',
		'ManyToMany',
		'Join',
	];

	/**
	 * @throws \Error
	 */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}

	/**
	 * @param string[][]|string[] $entityMap
	 * @return string[][]
	 * @throws SearchException
	 */
	public static function normalize(array $entityMap): array
	{
		$return = [];

		foreach ($entityMap as $entityName => $columns) {
			if (\class_exists($entityName) === false) {
				SearchException::entityIsNotValidClass((string) $entityName);
			}

			try {
				$entityProperties = self::getEntityProperties($entityName);
			} catch (\ReflectionException $e) {
				throw new SearchException($e->getMessage(), $e->getCode(), $e);
			}

			if (\is_string($columns)) {
				$columns = [$columns];
			}

			if (\is_array($columns) === false) {
				SearchException::columnIsNotValidArray((string) $columns);
			}

			foreach ($columns as $column) {
				if (\in_array(preg_replace('/^(?:\([^\)]*\)|[^a-zA-Z0-9]*)([^\.]+)(?:\..+)?$/', '$1', $column), $entityProperties, true) === false) {
					SearchException::columnIsNotValidProperty($column, $entityName, $entityProperties);
				}
			}

			$return[$entityName] = $columns;
		}

		return $return;
	}

	/**
	 * @param string $entityName
	 * @param int $ttl
	 * @return string[]
	 * @throws SearchException|\ReflectionException
	 */
	private static function getEntityProperties(string $entityName, int $ttl = 10): array
	{
		if ($ttl <= 0) {
			return [];
		}

		$return = [];

		if (strpos((string) ($reflection = Helpers::getReflectionClass($entityName))->getDocComment(), '@ORM\Entity(') === false) {
			throw new SearchException(
				'Class "' . $entityName . '" is not valid database entity. Please check comment annotation.'
				. "\n" . $reflection->getDocComment()
			);
		}

		if (($parent = $reflection->getParentClass()) instanceof \ReflectionClass) { // TODO: Change to while loop.
			$return = self::getEntityProperties($parent->getName(), $ttl - 1);
		}

		foreach ($reflection->getProperties() as $property) {
			if (preg_match('/@ORM\\\\(' . implode('|', self::$validPropertyTypes) . ')\s*\(/', $property->getDocComment())) {
				$return[] = $property->getName();
			}
		}

		return $return;
	}

}