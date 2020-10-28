<?php

declare(strict_types=1);

namespace Baraja\Search;


final class EntityMapNormalizer
{

	/** @var string[] */
	private static array $validPropertyTypes = ['Column', 'OneToOne', 'OneToMany', 'ManyToOne', 'ManyToMany', 'Join'];


	/** @throws \Error */
	public function __construct()
	{
		throw new \Error('Class ' . get_class($this) . ' is static and cannot be instantiated.');
	}


	/**
	 * @param mixed[] $entityMap
	 * @return string[][]
	 * @throws SearchException
	 */
	public static function normalize(array $entityMap): array
	{
		$return = [];
		foreach ($entityMap as $entityName => $columns) {
			if (\class_exists($entityName) === false) {
				throw new \InvalidArgumentException('Haystack "' . $entityName . '" is not valid class, ' . \gettype((string) $entityName) . ' given.');
			}

			$entityProperties = self::getEntityProperties($entityName);
			if (\is_string($columns) === true) {
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
	 * @return string[]
	 * @throws SearchException
	 */
	private static function getEntityProperties(string $entityName): array
	{
		$return = [];
		if (strpos((string) ($reflection = Helpers::getReflectionClass($entityName))->getDocComment(), '@ORM\Entity(') === false) {
			SearchException::classIsNotValidDatabaseEntity($entityName, $reflection->getDocComment());
		}

		$properties = [];
		while ($reflection !== false) {
			$properties[] = $reflection->getProperties();
			$reflection = $reflection->getParentClass();
		}

		/** @var \ReflectionProperty $property */
		foreach (array_merge([], ...$properties) as $property) {
			if (preg_match('/@ORM\\\\(' . implode('|', self::$validPropertyTypes) . ')\s*\(/', $property->getDocComment())) {
				$return[] = $property->getName();
			}
		}

		return $return;
	}
}
