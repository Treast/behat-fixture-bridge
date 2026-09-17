# Contributing

Thank you for considering a contribution to **Behat Fixture Bridge**. This
document describes how to set up the project locally, the coding standards we
follow, and the process for submitting a pull request.

By participating, you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md).

---

## Table of contents

- [Requirements](#requirements)
- [Setup](#setup)
- [Project layout](#project-layout)
- [Running the tools](#running-the-tools)
- [Coding standards](#coding-standards)
- [Testing](#testing)
- [Commit messages](#commit-messages)
- [Pull request process](#pull-request-process)
- [Releasing](#releasing)

---

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.4 |
| Composer | 2.x |
| Extensions | `json`, `mbstring` |

Optional but recommended:

- `xdebug` for coverage (CI does not require it)
- `make` (all commands are also available as Composer scripts)

---

## Setup

```bash
git clone https://github.com/Treast/behat-fixture-bridge.git
cd behat-fixture-bridge
composer install
```

`composer install` will also install the dev tools: PHPUnit, PHP CS Fixer, Rector.

To confirm everything is in place:

```bash
composer ci
```

This should end with `OK` for every tool. If it does, you are ready to code.

---

## Project layout

```
src/
├── Attribute/              # The #[AsFixtureRegistered] attribute
├── Behat/                  # Behat context exposing @[name] placeholders
├── DependencyInjection/    # Bundle extension and compiler pass
├── EventListener/          # Doctrine and Symfony listeners
├── Exception/              # Domain exceptions
├── Factory/                # Trait for manual registration
├── IdExtractor/            # Abstraction over Doctrine ID extraction
├── Metadata/               # Fixture metadata and its registry
├── Registry/               # In-memory registry and JSON file I/O
└── Resolver/               # Property and static-method resolver

tests/                      # PHPUnit tests, mirroring src/ layout
```

Every class lives in a file whose name matches the class. PSR-4 mapping is:

- `Treast\BehatFixtureBridge\` → `src/`
- `Treast\BehatFixtureBridge\Tests\` → `tests/`

Do not add new top-level directories without discussing it first in an issue.

---

## Running the tools

All tools are exposed as Composer scripts, so you never have to remember the
binary path.

| Command | What it does |
|---|---|
| `composer test` | Run PHPUnit |
| `composer test:coverage` | Run PHPUnit with HTML coverage in `var/coverage` |
| `composer cs` | Apply PHP CS Fixer (modifies files) |
| `composer cs:check` | PHP CS Fixer in dry-run mode (no modification) |
| `composer rector` | Apply Rector (modifies files) |
| `composer rector:check` | Rector in dry-run mode |
| `composer phpstan` | Static analysis |
| `composer ci` | Run everything in dry-run + tests |

### Before opening a pull request

```bash
composer cs      # apply fixes
composer rector  # apply refactorings
composer test    # ensure tests still pass
```

Then commit. The CI runs the same three tools in dry-run mode. If they pass
locally, they will pass in CI.

### What the CI does

The workflow in `.github/workflows/ci.yml` runs three jobs in parallel:

1. **PHPUnit** — on 8.4.
2. **Rector** — `rector process --dry-run`.
3. **PHP CS Fixer** — `php-cs-fixer fix --dry-run --diff`.

A red check means one of these three failed. Open the job log, reproduce
locally with the corresponding `composer` script, fix, and push.

---

## Coding standards

We follow the conventions of the Symfony ecosystem.

### Language and syntax

- **PHP 8.4+** only. Do not use features introduced in later versions unless
  the minimum PHP requirement is bumped in `composer.json`.
- **`declare(strict_types=1);`** at the top of every file.
- **Typed properties** and **return types** everywhere. Use `void`, `never`,
  `static`, and union types where appropriate.
- **`readonly`** for constructor-promoted DTOs and services that never change.
- **Enums** for closed sets of values.
- **Match expressions** over `switch` where possible.

### Style

- PSR-12 as configured in `.php-cs-fixer.dist.php`.
- 4 spaces for indentation. LF line endings (enforced by `.editorconfig`).
- Single quotes for strings, except when interpolation is needed.
- Short array syntax (`[]`).
- Alphabetically sorted `use` statements.
- One class per file, no exception.
- Trailing commas in multi-line arrays and argument lists.

Run `composer cs` after every significant edit. It is fast and idempotent.

### Naming

| Element | Convention | Example |
|---|---|---|
| Class | `PascalCase` | `FixtureMetadataRegistry` |
| Interface | `PascalCase` + `Interface` suffix | `IdExtractorInterface` |
| Trait | `PascalCase` + `AwareTrait` suffix when applicable | `FixtureRegistryAwareTrait` |
| Attribute | `As...` prefix | `AsFixtureBridge` |
| Method | `camelCase`, verb-first | `registerFixture()`, `buildKey()` |
| Property | `camelCase` | `$identifierFields` |
| Constant | `SCREAMING_SNAKE_CASE` | `PURGE_COMMANDS` |
| Test class | `<TestedClass>Test` | `FixtureRegistryTest` |
| Test method | `test<ExpectedBehaviorInPlainEnglish>` | `testThrowsOnUnknownFixtureName` |

Avoid abbreviations. `$metadata` is fine, `$md` is not. `$registry` is fine,
`$reg` is not.

### Documentation

- **Public classes and methods** carry a one-line docblock only when the
  behaviour is not obvious from the signature.
- **Complex logic** is explained with a short comment above the block, not
  inside it.
- **`@param`/`@return`** are used only when the PHP type system cannot express
  the constraint (generics, array shapes).
- **No decorative comments** like `// ****` or `// --- Section ---`.

Example of a good docblock:

```php
/**
 * Extracts the identifier values from a persisted entity.
 *
 * @return array<string, mixed>
 */
public function extract(object $entity): array
```

Example of an unnecessary one:

```php
/**
 * Returns the name.
 *
 * @return string The name
 */
public function getName(): string
```

### Architecture

- **Dependency direction**: `Behat` depends on `Registry`. `Registry` depends
  on `IdExtractor` and `Resolver`. Nothing in `Registry` imports anything from
  `Behat`. If you need to break this rule, open an issue first.
- **No static state.** Every service is instantiable with explicit dependencies.
- **Exceptions are domain-specific**: use `RegistryKeyNotFoundException`,
  not a generic `\RuntimeException`, unless the case is genuinely impossible to
  name.
- **Attributes are inert.** They carry data; they do not perform I/O or
  reflection. Reading happens in the compiler pass.

---

## Testing

### Philosophy

We test **behaviour that could break**, not trivialities. A getter with no logic
does not need a test. A method that reads a file, resolves a value through
reflection, or builds a composite string does.

### Structure

Tests mirror `src/` one-to-one:

```
src/Registry/FixtureRegistry.php   →   tests/Registry/FixtureRegistryTest.php
src/Resolver/ValueResolver.php     →   tests/Resolver/ValueResolverTest.php
```

### Conventions

- **One assertion concept per test.** Multiple `assertSame` on the same outcome
  are fine; testing two unrelated things is not.
- **Test names describe the expected behaviour.** `testThrowsOnUnknownFixtureName`
  is better than `testGetId`.
- **No mocking of what you own.** Use real instances of `RegistryFile`,
  `FixtureRegistry`, `ValueResolver`. Mock only external boundaries
  (Doctrine's `ManagerRegistry`, Symfony's container).
- **Anonymous classes or small test fixtures** for entity stubs. Avoid
  creating full Doctrine entities just to test the resolver.
- **Never use `sleep()`** unless testing file mtime. If you do, keep it under
  100 ms.
- **Clean up temporary files** in `tearDown()`. Never write to the project's
  `var/` directory from a test.

### What not to test

- Trivial getters and setters.
- Constants and enums members.
- `__toString()` implemented with a single concatenation.
- Symfony's own container compilation (test the compiler pass output instead).
- Third-party code.

### Running a single test

```bash
vendor/bin/phpunit tests/Registry/FixtureRegistryTest.php
vendor/bin/phpunit --filter testThrowsOnUnknownFixtureName
```

### Adding a test for a new feature

1. Write the failing test first if you can.
2. Add the minimal production code to make it pass.
3. Refactor once green.
4. Ensure `composer ci` still passes.

---

## Commit messages

We follow [Conventional Commits](https://www.conventionalcommits.org/).

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types

| Type | When |
|---|---|
| `feat` | A new user-facing feature |
| `fix` | A bug fix |
| `docs` | Documentation only |
| `test` | Adding or fixing tests |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `chore` | Build, tooling, dependencies |
| `perf` | Performance improvement |

### Scopes

Use the class or directory name in `kebab-case`:

```
feat(registry): add support for composite identifiers
fix(resolver): handle pure enums in normalize()
docs(readme): add inheritance example
test(registry-file): cover empty-file case
refactor(compiler-pass): extract entity resolution
chore(deps): bump phpunit to 10.5
```

### Rules

- **Subject line** in imperative mood, lowercase, no trailing period, max 72
  characters.
- **Body** wraps at 72 characters, explains *why* not *what*.
- **Footer** references issues: `Fixes #42`, `Closes #17`.

Example:

```
fix(listener): do not overwrite existing registry entries

When the same factory runs twice with --append, the second persist()
re-ran the registration and silently replaced the first entry's
properties. This broke scenarios that relied on the original values.

We now check has() before registering. Existing keys are preserved.

Fixes #34
```

---

## Pull request process

1. **Open an issue first** for anything beyond a typo or a small fix. Discussing
   the approach avoids wasted work.
2. **Fork** the repository and create a branch off `main`:

   ```bash
   git checkout -b feat/registry-properties
   ```

3. **Make your changes** in small, focused commits. One logical change per
   commit; one feature or fix per pull request.
4. **Run `composer ci`** locally. Fix everything it reports.
5. **Update the docs** if your change affects usage: `README.md`, this file,
   or `CHANGELOG.md` under the `Unreleased` section.
6. **Push** and open a pull request against `main`.
7. **Fill in the PR template**. Describe the problem, the solution, and any
   trade-off you made.
8. **Address review comments** with new commits, not force-pushes. The final
   merge will squash if requested.

### What reviewers look for

- Behaviour: does it solve the stated problem? Are edge cases handled?
- Tests: are they focused, meaningful, and free of trivialities?
- Naming: do class, method, and variable names read well in isolation?
- Documentation: can a new user follow the README without asking questions?
- Backward compatibility: does the change break existing registries or
  scenarios? If yes, is there a migration path?

### Backward compatibility

This project follows [Semantic Versioning](https://semver.org/).

- **Breaking changes** require a major version bump. Announce them in an issue
  before opening a PR.
- **Deprecations** must include a `trigger_error(..., E_USER_DEPRECATED)` and a
  note in `CHANGELOG.md`.
- **The registry JSON format** is a public contract. Adding optional keys is
  fine; removing or renaming keys is a breaking change.
- **The attribute signature** is a public contract. Adding an optional
  constructor argument is fine; reordering or removing arguments is not.

---

## Releasing

Only maintainers release. The process is:

1. Ensure `main` is green.
2. Update `CHANGELOG.md`: move `Unreleased` content under the new version
   heading with today's date.
3. Commit: `chore(release): 1.2.0`.
4. Tag: `git tag -a 1.2.0 -m "1.2.0"`.
5. Push: `git push origin main --tags`.
6. Packagist picks up the tag automatically.

---

## Questions

If something in this document is unclear, or if you are unsure whether a change
is in scope, open an issue with the `question` label. We would rather answer a
quick question than review a PR that cannot be merged.

Thank you for contributing.