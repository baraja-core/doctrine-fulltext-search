<div align='center'>
  <picture>
    <source media='(prefers-color-scheme: dark)' srcset='https://cdn.brj.app/images/brj-logo/logo-regular.png'>
    <img src='https://cdn.brj.app/images/brj-logo/logo-dark.png' alt='BRJ logo'>
  </picture>
  <br>
  <a href="https://brj.app">BRJ organisation</a>
</div>
<hr>

# Doctrine Fulltext Search

![Integrity check](https://github.com/baraja-core/doctrine-fulltext-search/workflows/Integrity%20check/badge.svg)

A powerful, easy-to-use fulltext search engine for Doctrine entities with automatic relevance scoring, query normalization, and machine learning-powered suggestions.

- Define entity and column mappings with simple configuration
- Automatic relevance scoring and result sorting
- Built-in "Did you mean?" suggestions using analytics
- Query normalization with stopword filtering
- Support for entity relationships and custom getters
- Nette Framework integration via DIC extension

---

## 🎯 Core Principles

- **Zero Configuration Start**: Define your entity map and start searching immediately
- **Intelligent Scoring**: Results are automatically scored and sorted by relevance (0-512 points)
- **Query Normalization**: Automatic stopword removal, duplicate filtering, and query sanitization
- **Relationship Support**: Search across related entities using dot notation
- **Analytics-Powered**: Machine learning suggestions based on search history
- **Extensible Architecture**: Override query normalizer and score calculator via interfaces
- **Performance Optimized**: PARTIAL selection for efficient database queries with configurable timeout

---

## 🏗️ Architecture Overview

The package follows a modular architecture with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              Search                                      │
│                         (Main Entry Point)                               │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
         ┌──────────────┐  ┌───────────────┐  ┌──────────────┐
         │   Container  │  │SelectorBuilder│  │EntityMapNorm.│
         │  (Services)  │  │ (Fluent API)  │  │ (Validation) │
         └──────────────┘  └───────────────┘  └──────────────┘
                 │
    ┌────────────┼────────────┬──────────────┐
    ▼            ▼            ▼              ▼
┌────────┐ ┌──────────┐ ┌───────────┐ ┌───────────┐
│  Core  │ │Analytics │ │  Query    │ │  Score    │
│(Search)│ │(Did you  │ │Normalizer │ │Calculator │
│        │ │  mean?)  │ │           │ │           │
└────────┘ └──────────┘ └───────────┘ └───────────┘
    │
    ▼
┌──────────────┐
│ QueryBuilder │
│   (DQL)      │
└──────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          SearchResult                                    │
│              (Contains SearchItem[] with scoring)                        │
└─────────────────────────────────────────────────────────────────────────┘
```

### 🔧 Main Components

| Component | Purpose |
|-----------|---------|
| **Search** | Main entry point, orchestrates the search process |
| **SelectorBuilder** | Fluent API for building search queries with type validation |
| **Container** | Service container holding all dependencies (PSR-11 compatible) |
| **Core** | Internal search logic, processes candidate results |
| **QueryBuilder** | Builds DQL queries with JOIN support for relations |
| **Analytics** | Stores search statistics, powers "Did you mean?" feature |
| **QueryNormalizer** | Normalizes queries, removes stopwords |
| **ScoreCalculator** | Calculates relevance scores with year boost |
| **SearchResult** | Collection of results implementing Iterator |
| **SearchItem** | Single search result with entity, title, snippet, and score |

---

## 📦 Installation

It's best to use [Composer](https://getcomposer.org) for installation, and you can also find the package on
[Packagist](https://packagist.org/packages/baraja-core/doctrine-fulltext-search) and
[GitHub](https://github.com/baraja-core/doctrine-fulltext-search).

To install, simply use the command:

```shell
$ composer require baraja-core/doctrine-fulltext-search
```

### Requirements

- PHP 8.0 or higher
- ext-mbstring
- Doctrine ORM 2.9+

### Nette Framework Integration

Register the DIC extension in your NEON configuration:

```yaml
extensions:
    doctrineFulltextSearch: Baraja\Search\DoctrineFulltextSearchExtension
```

The extension automatically registers:
- `Search` service
- `QueryNormalizer` service
- `ScoreCalculator` service
- `SearchAccessor` accessor
- `QueryBuilder` service

### Manual Instantiation

You can create an instance of `Search` manually:

```php
use Baraja\Search\Search;
use Doctrine\ORM\EntityManagerInterface;

$search = new Search($entityManager);
```

With custom normalizer and score calculator:

```php
$search = new Search(
    em: $entityManager,
    queryNormalizer: new CustomQueryNormalizer(),
    scoreCalculator: new CustomScoreCalculator(),
);
```

---

## 🚀 Basic Usage

### Simple Array-Based Query

The simplest way to perform a search is by defining an entity map:

```php
$results = $search->search($query, [
    Article::class => [':title', 'description', 'content'],
    User::class => ':username',
    Product::class => [':name', 'sku', '!internalCode'],
]);

echo $results; // Uses built-in HTML renderer
```

### Fluent SelectorBuilder API

For better type safety and IDE autocompletion, use the `SelectorBuilder`:

```php
$results = $search->selectorBuilder($query)
    ->addEntity(Article::class)
        ->addColumnTitle('title')
        ->addColumn('description')
        ->addColumn('content')
    ->addEntity(User::class)
        ->addColumnTitle('username')
        ->addEntity(Product::class)
        ->addColumnTitle('name')
        ->addColumn('sku')
        ->addColumnSearchOnly('internalCode')
    ->search();
```

### Adding WHERE Conditions

Filter results with custom conditions:

```php
$results = $search->selectorBuilder($query)
    ->addEntity(Article::class)
        ->addColumnTitle('title')
        ->addColumn('content')
    ->addWhere('active = TRUE')
    ->addWhere('publishedAt <= NOW()')
    ->search();
```

---

## 🛠️ Column Modifiers

Column names support special prefixes that control how they're used in search:

| Modifier | Syntax | Description |
|----------|--------|-------------|
| **Title** | `:column` | Used as result caption, displayed even without match |
| **Search Only** | `!column` | Searched but excluded from snippet output |
| **Select Only** | `_column` | Loaded but not searched or included in snippet |
| **Normal** | `column` | Searched and included in snippet |

### Examples

```php
$entityMap = [
    Article::class => [
        ':title',           // Title column - always shown
        'description',      // Normal - searched and in snippet
        '!slug',            // Search only - searched but not in snippet
        '_authorId',        // Select only - loaded but not searched
    ],
];
```

Using SelectorBuilder:

```php
$search->selectorBuilder($query)
    ->addEntity(Article::class)
        ->addColumnTitle('title')           // :title
        ->addColumn('description')          // description
        ->addColumnSearchOnly('slug')       // !slug
        ->addColumnSelectOnly('authorId')   // _authorId
    ->search();
```

---

## 🔗 Entity Relationships

Search across related entities using dot notation:

```php
$entityMap = [
    Article::class => [
        ':title',
        'author.name',           // ManyToOne: Article -> Author
        'categories.name',       // ManyToMany: Article -> Categories
        'content.versions.text', // Deep relation chain
    ],
];
```

### Custom Getters

When the getter method differs from the column name:

```php
$entityMap = [
    Article::class => [
        'versions(content)', // Joins 'versions' but calls getContent()
    ],
];
```

---

## 🔍 Advanced Query Features

### Exact Match

Wrap phrases in quotes for exact matching:

```php
$query = '"to be or not to be"';
// Finds exact phrase
```

### Negative Match

Exclude words with minus prefix:

```php
$query = 'linux -ubuntu';
// Finds "linux" but excludes results containing "ubuntu"
```

### Number Intervals

Search for number ranges:

```php
$query = 'conference 2020..2024';
// Finds results containing years 2020, 2021, 2022, 2023, or 2024
```

---

## 📊 Working with Results

### SearchResult Entity

The `search()` method returns a `SearchResult` entity implementing `Iterator`:

```php
$results = $search->search($query, $entityMap);

// Total count
$count = $results->getCountResults();

// Search time in milliseconds
$time = $results->getSearchTime();

// "Did you mean?" suggestion
$suggestion = $results->getDidYouMean();

// Iterate results
foreach ($results as $item) {
    echo $item->getTitle();
}
```

### Getting Results

```php
// Get first 10 results
$items = $results->getItems();

// With pagination
$items = $results->getItems(limit: 20, offset: 40);

// Filter by entity type
$articles = $results->getItemsOfType(Article::class, limit: 10);

// Get only IDs
$ids = $results->getIds(limit: 100);
```

### SearchItem Methods

Each result is a `SearchItem` with these methods:

| Method | Return Type | Description |
|--------|-------------|-------------|
| `getId()` | `string\|int` | Entity identifier |
| `getEntity()` | `object` | Original Doctrine entity (PARTIAL loaded) |
| `getTitle()` | `?string` | Normalized title |
| `getTitleHighlighted()` | `?string` | Title with `<i class="highlight">` tags |
| `getSnippet()` | `string` | Best matching text snippet |
| `getSnippetHighlighted()` | `string` | Snippet with highlighted words |
| `getScore()` | `int` | Relevance score (0-512) |
| `entityToArray()` | `array` | Entity as normalized array |

### Quick HTML Rendering

For rapid prototyping, `SearchResult` implements `__toString()`:

```php
echo $results;
```

This outputs styled HTML with:
- Result count and search time
- "Did you mean?" suggestion (if available)
- Results with highlighted titles and snippets

Add `?debugMode=1` to URL to see scores in output.

---

## ✅ "Did You Mean?" Feature

When search returns few or no results, the engine can suggest alternative queries:

```php
$results = $search->search('programing', $entityMap);

if ($results->getCountResults() === 0) {
    $suggestion = $results->getDidYouMean();
    if ($suggestion !== null) {
        echo "Did you mean: $suggestion?"; // "programming"
    }
}
```

### How It Works

1. Every search query and result count is stored in the `search__search_query` table
2. Queries are scored based on frequency and result count
3. When needed, the system finds similar queries using Levenshtein distance
4. The best match is suggested based on combined scoring

Disable analytics for specific searches:

```php
$results = $search->search($query, $entityMap, useAnalytics: false);

// Or with SelectorBuilder
$results = $search->selectorBuilder($query)
    ->addEntity(Article::class)
        ->addColumnTitle('title')
    ->search(useAnalytics: false);
```

---

## 📈 Scoring System

Results are scored on a scale of 0-512 points based on multiple factors:

### Score Calculation

| Factor | Points | Description |
|--------|--------|-------------|
| Exact match | +32 | Haystack equals query exactly |
| Contains query | +4 | Query found as substring |
| Substring count | +1-3 | Bonus per occurrence (max 3) |
| Word match | +1-4 | Per word occurrence (max 4) |
| Empty content | -16 | Penalty for empty fields |
| Search-only column | -4 | Reduced weight for `!` columns |
| Title column | x6-10 | Multiplier for `:` columns |
| Year boost | x1-6 | Bonus for current/recent years |

### Year Boost

The score calculator automatically boosts results containing recent years:
- Current year and adjacent years receive higher scores
- Particularly relevant for news, events, and time-sensitive content

### Custom Score Calculator

Implement `IScoreCalculator` for custom scoring:

```php
use Baraja\Search\ScoreCalculator\IScoreCalculator;

class CustomScoreCalculator implements IScoreCalculator
{
    public function process(string $haystack, string $query, string $mode = null): int
    {
        // Your custom scoring logic
        return $score;
    }
}
```

Register in Nette DI:

```yaml
services:
    - CustomScoreCalculator
```

The container will automatically use your implementation.

---

## 🔄 Query Normalization

Queries are automatically normalized before processing:

### Default Normalizer Features

1. **Whitespace normalization**: Multiple spaces reduced to single
2. **Length limit**: Truncated to 255 characters
3. **Stopword removal**: Common words filtered (in, it, a, the, of, or, etc.)
4. **Duplicate removal**: Repeated words kept only once
5. **Special character handling**: `%`, `_`, `{`, `}` converted or removed
6. **Hash removal**: `#123` becomes `123`

### Custom Query Normalizer

Implement `IQueryNormalizer` for project-specific normalization:

```php
use Baraja\Search\QueryNormalizer\IQueryNormalizer;

class CustomQueryNormalizer implements IQueryNormalizer
{
    public function normalize(string $query): string
    {
        // Your normalization logic
        return $normalizedQuery;
    }
}
```

---

## ⚙️ Configuration Options

### Search Timeout

Configure maximum search time (default: 2500ms):

```php
$container = new Container(
    entityManager: $em,
    searchTimeout: 5000, // 5 seconds
);

$search = new Search($em, container: $container);
```

### Exact Search Mode

Disable "Did you mean?" suggestions:

```php
$results = $search->search(
    query: $query,
    entityMap: $entityMap,
    searchExactly: true,
);
```

### User Conditions

Add WHERE conditions to all entity queries:

```php
$results = $search->search(
    query: $query,
    entityMap: $entityMap,
    userConditions: [
        'e.active = TRUE',
        'e.deletedAt IS NULL',
    ],
);
```

---

## 📝 Database Entity

The package creates one database table for analytics:

### SearchQuery Entity

Table: `search__search_query`

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| query | string | Normalized search query (unique) |
| frequency | int | Number of times searched |
| results | int | Last result count |
| score | int | Calculated relevance (0-100) |
| insertedDate | datetime | First search time |
| updatedDate | datetime | Last search time |

The table is automatically created when using Doctrine migrations with the package's entity mappings.

---

## 🎨 Styling Highlighted Results

The default highlighter wraps matched words in:

```html
<i class="highlight">matched word</i>
```

Add CSS for styling:

```css
.highlight {
    background: rgba(68, 134, 255, 0.35);
}

.search__info {
    padding: .5em 0;
    margin-bottom: .5em;
    border-bottom: 1px solid #eee;
}

.search__did_you_mean {
    color: #ff421e;
}
```

### Custom Highlight Pattern

Use `Helpers::highlightFoundWords()` with custom pattern:

```php
use Baraja\Search\Helpers;

$highlighted = Helpers::highlightFoundWords(
    haystack: $text,
    words: $query,
    replacePattern: '<mark>\0</mark>',
);
```

---

## 🌍 Internationalization

The search engine handles accented characters intelligently:

- **ASCII conversion**: Queries are converted for matching (`café` matches `cafe`)
- **Accent-aware highlighting**: Original text preserved with proper highlighting
- **Character mapping**: Supports Czech, Slovak, Polish, and other Central European languages

Supported character mappings:
- `a` matches `á`, `ä`
- `c` matches `č`
- `e` matches `è`, `ê`, `é`, `ě`
- `n` matches `ň`
- `r` matches `ř`, `ŕ`
- `s` matches `š`, `ś`
- `z` matches `ž`, `ź`
- And more...

---

## 🔧 Troubleshooting

### Column Not Found

```
InvalidArgumentException: Column "title" is not valid property of "App\Entity\Article".
Did you mean "headline"?
```

The package validates column names against entity metadata. Check your entity properties or use the suggested alternative.

### Empty Results

1. Verify entity has data in the database
2. Check if columns contain searchable text
3. Try disabling query normalization for debugging
4. Verify WHERE conditions aren't too restrictive

### Performance Issues

1. Add database indexes on searched columns
2. Reduce the number of entities/columns in search
3. Lower the search timeout
4. Use `!` modifier for large text columns
5. Consider `_` modifier for columns only needed in results

---

## 👤 Author

**Jan Barášek**

- Website: [https://baraja.cz](https://baraja.cz)
- GitHub: [@janbarasek](https://github.com/janbarasek)

---

## 📄 License

`baraja-core/doctrine-fulltext-search` is licensed under the MIT license. See the [LICENSE](https://github.com/baraja-core/doctrine-fulltext-search/blob/master/LICENSE) file for more details.
