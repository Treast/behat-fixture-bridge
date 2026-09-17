# Behat Fixture Bridge

[![CI](https://github.com/Treast/behat-fixture-bridge/actions/workflows/ci.yml/badge.svg)](https://github.com/Treast/behat-fixture-bridge/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/treast/behat-fixture-bridge/v)](https://packagist.org/packages/treast/behat-fixture-bridge)
[![License](https://poser.pugx.org/treast/behat-fixture-bridge/license)](https://packagist.org/packages/treast/behat-fixture-bridge)

> A bridge between your fixture factories and your Behat scenarios. Annotate a
> factory, get a stable symbolic name (`@[book:42]`), use it in your Gherkin
> steps. No seed, no hardcoded IDs, no breaking existing tests.

---

## The problem

You generate realistic fixtures with Faker. Hundreds of entities get random IDs
and random values on every run. Your Behat scenarios need to reference **one
specific entity** to visit its page, edit its title, or assert it appears in a
list. Hardcoded IDs break as soon as the fixture order changes. Seeding Faker
is fragile the moment you add a property to an entity.

**Behat Fixture Bridge** solves this by giving each factory the ability to
register the entities it creates under a **stable, human-readable name**:

```
book:42
book:les-miserables
order:2026-0001
```

These names are written to a JSON file that Behat reads. Your scenarios then use
`@[book:42]` anywhere a value is expected:

```gherkin
Given I go to "/books/@[book:42]"
Then I should see "@[book:42][title]" in the "h1" element
```

The ID and properties are resolved at runtime, from the registry.

---

## Installation

### 1. Require the package

```bash
composer require --dev treast/behat-fixture-bridge
```

### 2. Register the bundle (dev and test only)

In `config/bundles.php`:

```php
return [
    // ...
    Treast\BehatFixtureBridge\BehatFixtureBridgeBundle::class => ['dev' => true, 'test' => true],
];
```

The bundle is **never loaded in production**. If someone accidentally enables it
in `prod`, no harm is done — the listeners are idle when no entity matches the
registered metadata.

### 3. Configure the bundle

Create `config/packages/test/behat_fixture_bridge.yaml`:

```yaml
behat_fixture_bridge:
    file: '%kernel.project_dir%/var/fixtures/registry.json'
    auto_flush_on_terminate: true
    purge_on_fixtures_load: true
```

| Option | Default | Purpose |
|---|---|---|
| `file` | `%kernel.project_dir%/var/fixtures/registry.json` | Where the registry is written. |
| `auto_flush_on_terminate` | `true` | Write the file after each Doctrine flush and on kernel/console shutdown. |
| `purge_on_fixtures_load` | `true` | Wipe the registry before `doctrine:fixtures:load` (skipped if `--append`). |

Add the registry file to `.gitignore`:

```gitignore
/var/fixtures/registry.json
```

### 4. Enable the Behat context

In `behat.yml` (or `behat.yaml`):

```yaml
default:
  suites:
    default:
      contexts:
        - Treast\BehatFixtureBridge\Behat\FixtureBridgeContext
        # ... your other contexts
```

The context is **public** in the container, so `friends-of-behat/symfony-extension`
will inject the registry automatically. No bootstrapping needed.

---

## Quick start

### Annotate a factory

If you use [Foundry](https://github.com/zenstruck/foundry), add the attribute on
top of the factory class:

```php
namespace App\Factory;

use Treast\BehatFixtureBridge\Attribute\AsFixtureBridge;
use App\Entity\Book;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

#[AsFixtureBridge(name: 'book', identifier: 'id', properties: ['title', 'slug'])]
final class BookFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Book::class;
    }

    protected function defaults(): array
    {
        return [
            'title' => self::faker()->sentence(3),
            'slug' => self::faker()->slug(),
        ];
    }
}
```

### Load your fixtures

```bash
php bin/console doctrine:fixtures:load --env=test
```

Your fixtures run as usual. Every time a `Book` is persisted, the listener
registers it in the registry:

```json
{
    "book:1": {
        "class": "App\\Entity\\Book",
        "ids": { "id": 1 },
        "properties": {
            "title": "Les Misérables",
            "slug": "les-miserables"
        }
    },
    "book:2": {
        "class": "App\\Entity\\Book",
        "ids": { "id": 2 },
        "properties": {
            "title": "Germinal",
            "slug": "germinal"
        }
    }
}
```

### Use it in a scenario

```gherkin
Feature: Book editing

  Scenario: Update a book title
    Given I go to "/book/@[book:1]/edit"
    When I fill in "book[title]" with "Les Misérables (édition 2024)"
    And I press "Save"
    Then I should see "Les Misérables (édition 2024)" in the "h1" element
```

That's it. The `@[book:1]` placeholder is resolved to `1` before the step runs.

---

## How placeholders work

The context exposes a single `@Transform` that recognises two syntaxes and
substitutes them anywhere they appear.

### ID: `@[name]`

Resolves to the entity's identifier. If the entity has a composite identifier,
the transform returns an array.

```gherkin
Given I go to "/book/@[book:1]"
Then the response status code should be 200
```

### Property: `@[name][property]`

Resolves to a registered property.

```gherkin
Then I should see "@[book:1][title]" in the "h1" element
And the JSON field "slug" should equal "@[book:1][slug]"
```

### Inline substitution

Placeholders work **inside a larger string**, not only as standalone arguments.

```gherkin
Given I go to "/book/@[book:1]/edit"
Then I should see "Edition : @[book:1][title]" in the ".subtitle"
```

This is the main advantage over the older `@name` syntax: `[` and `]` are
impossible in emails or social handles, so there is **no ambiguity**:

```gherkin
# Not touched (no brackets, looks like an email)
Given I fill in "email" with "contact@example.com"

# Touched (brackets delimit the placeholder)
Given I fill in "email" with "@[user:1][email]"
```

### In tables

Transforms do not run on `TableNode` cells. Use the dedicated step:

```gherkin
Given I expand fixtures in the following table:
  | parent    | title                       |
  | @[book:1] | @[book:1][title] (original) |
```

`parent` becomes `1`, `title` becomes `Les Misérables (original)`.

---

## The attribute in detail

```php
#[AsFixtureBridge(
    name: 'book',
    identifier: 'id',
    if: 'isFeatured',
    properties: ['title', 'slug', 'year' => 'releaseYear'],
)]
```

### `name` (required)

The prefix of the final key. Can contain dots, colons, and dashes:

```php
#[AsFixtureBridge(name: 'book')]         // → "book:42"
#[AsFixtureBridge(name: 'art:book')]  // → "art:book:42"
```

### `identifier` (default: `'id'`)

How the suffix after `:` is built. Four forms:

**Property or getter on the entity:**

```php
#[AsFixtureBridge(name: 'book', identifier: 'id')]    // → "book:42"
#[AsFixtureBridge(name: 'book', identifier: 'slug')]  // → "book:les-miserables"
```

**Static method on the factory:**

```php
#[AsFixtureBridge(name: 'book', identifier: '@buildSlug')]
public static function buildSlug(Book $book): string
{
    return sprintf('%d-%s', $book->getYear(), $book->getSlug());
}
// → "book:2024-les-miserables"
```

**Composite identifier:**

```php
#[AsFixtureBridge(name: 'order', identifier: ['year', 'number'])]
// → "order:2024-0001"
```

**No suffix:**

```php
#[AsFixtureBridge(name: 'art:book', identifier: [])]
// → "art:book"
```

### `if` (optional)

Name of a static method on the factory that filters which entities get
registered:

```php
#[AsFixtureBridge(name: 'book', identifier: 'id', if: 'isFeatured')]
public static function isFeatured(Book $book): bool
{
    return $book->isFeatured();
}
```

Only featured books are registered. Non-featured books are persisted as usual
but do not appear in the registry.

### `properties` (optional)

Extra properties exposed via `@[name][property]`. Two syntaxes:

```php
// List form: the property name is both the source and the exposed name
'properties' => ['title', 'slug']

// Map form: expose under a different name
'properties' => ['year' => 'releaseYear', 'author' => '@authorName']
```

`'@authorName'` calls a static method on the factory:

```php
public static function authorName(Book $book): string
{
    return $book->getAuthor()?->getFullName() ?? '';
}
```

Storage accepts scalars, `DateTimeInterface`, `BackedEnum`, `Stringable`, and
arrays of these. Anything else throws a clear exception at registration time.

---

## Supported return types for identifiers and properties

The registry is a JSON file. Only serialisable values are accepted.

| Type | Stored as |
|---|---|
| `string`, `int`, `float`, `bool` | as-is (`bool` becomes `1`/`0` in identifiers) |
| `null` | `null` (property) or omitted (identifier) |
| `DateTimeInterface` | ISO 8601 string |
| `BackedEnum` | its `->value` |
| `Stringable` | `(string) $value` |
| Array of the above | array (properties) or `-`-joined string (identifier) |

Objects are rejected. If you need to expose a relation, add a factory method
that returns a scalar:

```php
#[AsFixtureBridge(name: 'book', identifier: 'id', properties: ['author' => '@authorName'])]
public static function authorName(Book $book): string
{
    return $book->getAuthor()?->getFullName() ?? '';
}
```

---

## Where the registry lives

By default: `var/fixtures/registry.json`. It looks like this:

```json
{
    "book:1": {
        "class": "App\\Entity\\Book",
        "ids": { "id": 1 },
        "properties": {
            "title": "Les Misérables",
            "slug": "les-miserables"
        }
    },
    "user:1": {
        "class": "App\\Entity\\User",
        "ids": { "id": 1 },
        "properties": {
            "email": "admin@example.com"
        }
    }
}
```

You can read it, diff it in CI, commit it if you want a snapshot, or delete it
freely — it is regenerated on every `doctrine:fixtures:load`.

### Lifecycle

| Moment | What happens |
|---|---|
| `doctrine:fixtures:load` starts (no `--append`) | The registry is **wiped** and the file is deleted. |
| Each `persist()` of a matching entity | An entry is **registered in memory**. |
| After each Doctrine flush and on shutdown | The file is **written to disk**. |
| Behat scenario runs | The context **reads** the file lazily (first access). |

If you need to inspect the registry during a scenario from a custom step, inject
`FixtureRegistryInterface`:

```php
use Treast\BehatFixtureBridge\Registry\FixtureRegistryInterface;

final class MyContext implements Context
{
    public function __construct(private FixtureRegistryInterface $registry) {}

    /**
     * @Then /^the fixture "([^"]+)" should exist$/
     */
    public function theFixtureShouldExist(string $name): void
    {
        Assert::assertTrue($this->registry->has($name));
    }
}
```

---

## Manual registration (without the attribute)

Some cases are hard to express with an attribute — a key built at runtime, or
several keys for the same entity. Use the trait instead:

```php
namespace App\DataFixtures;

use Treast\BehatFixtureBridge\Factory\FixtureBridgeAwareInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class AppFixtures extends Fixture
{
    use FixtureBridgeAwareInterface;

    public function load(ObjectManager $manager): void
    {
        $book = new Book();
        $book->setTitle('Les Misérables');
        $book->setSlug('les-miserables');
        $manager->persist($book);
        $manager->flush();

        // Register under two names for the same entity.
        $this->registerFixture($book, 'book:' . $book->getId());
        $this->registerFixture($book, 'art:' . $book->getId());
    }
}
```

The class must be a Symfony service with autowiring enabled, so the trait's
`setFixtureRegistry()` is called automatically.

---

## Inheritance: `Art` and its subclasses

When an entity has subclasses (`Book` extending `Art`), use
one factory per concrete class, an abstract factory for shared fields, and two
attributes per concrete factory:

```php
// src/Factory/ArtFactory.php
abstract class ArtFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'title' => self::faker()->sentence(3),
            'slug' => self::faker()->unique()->slug(4),
        ];
    }
}

// src/Factory/BookFactory.php
#[AsFixtureBridge(name: 'book', identifier: 'id', properties: ['title'])]
#[AsFixtureBridge(name: 'art', identifier: 'id', properties: ['title'])]
final class BookFactory extends ArtFactory
{
    public static function class(): string
    {
        return Book::class;
    }

    protected function defaults(): array
    {
        return array_merge(parent::defaults(), [
            'pages' => self::faker()->numberBetween(60, 180),
        ]);
    }
}
```

Now `@[book:1]` targets the specific type, while `@[art:1]` works regardless
of the concrete class.

---

## A full example

### Fixtures

```php
// src/DataFixtures/AppFixtures.php
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Named fixtures: referenced in scenarios
        BookFactory::createOne(['title' => 'Les Misérables', 'slug' => 'les-miserables']);
        BookFactory::createOne(['title' => 'Germinal', 'slug' => 'germinal']);

        // Volume: not referenced by name, only used to fill lists
        BookFactory::createMany(150);
    }
}
```

Result: `book:1`, `book:2`, and 150 more. You only ever reference `book:1` and
`book:2` in scenarios — the others are noise that makes the UI realistic.

### Feature

```gherkin
Feature: Browse and edit books

  Background:
    Given the fixtures are loaded

  Scenario: See a book detail page
    Given I go to "/book/@[book:1]"
    Then I should see "@[book:1][title]" in the "h1" element

  Scenario: Edit a book title
    Given I go to "/book/@[book:1]/edit"
    When I fill in "book[title]" with "Les Misérables (édition définitive)"
    And I press "Save"
    Then I should see "Les Misérables (édition définitive)" in the ".alert-success"

  Scenario: Filter books by a specific one
    Given I go to "/books?search=@[book:2][slug]"
    Then I should see "@[book:2][title]" in the "table tbody tr:first-child td"

  Scenario: Check a JSON API response
    Given I send a GET request to "/api/book/@[book:1]"
    Then the JSON node "slug" should be equal to "@[book:1][slug]"
```

No hardcoded ID, no seed, no brittle assertion.

---

## Troubleshooting

### `Class "...FixtureBridgeContext" not found`

Composer does not know the class yet:

```bash
composer dump-autoload
php bin/console cache:clear --env=test
```

### `Interface "...FixtureRegistryInterface" not found` at container compile time

The bundle is not registered, or is registered in `prod` only. Check
`config/bundles.php` — it must include `BehatFixtureBridgeBundle::class` for
both `dev` and `test`.

### `Typed property ... must not be accessed before initialization`

The context still has a `setFixtureRegistry()` setter. In a Symfony + FoB setup,
inject by **constructor**. Remove the setter and the interface; let the container
pass the registry.

### `@[book:1]` is not substituted and appears in the URL

The context is not registered in `behat.yml` for the current suite. Add it
under `contexts`. Verify with:

```bash
vendor/bin/behat --suite=default --definitions
```

The `@Transform` from `FixtureBridgeContext` should appear in the list.

### `Property "title" is not registered for "book:1"`

The property is missing from the `properties` argument of
`#[AsFixtureBridge]`. Add it:

```php
#[AsFixtureBridge(name: 'book', identifier: 'id', properties: ['title', 'slug'])]
```

Then reload fixtures so the registry picks up the new properties.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.4+ |
| Behat | 3.13+ |
| Doctrine ORM | 2.14+ or 3.0+ |
| Symfony | 6.x, 7.x, 8.x |

Optional but recommended:

- `friends-of-behat/symfony-extension` (auto-instantiates the context via the container)
- `zenstruck/foundry` (attribute detection supports both v1 and v2)

---

## Contributing

```bash
git clone https://github.com/Treast/behat-fixture-bridge
cd behat-fixture-bridge
composer install
composer ci
```

`composer ci` runs Rector (dry-run), PHP CS Fixer (dry-run), and PHPUnit. It
mirrors exactly what GitHub Actions does, so a green local run guarantees a
green CI.

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## License

MIT. See [LICENSE](LICENSE).