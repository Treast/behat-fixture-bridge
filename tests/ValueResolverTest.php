<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\Resolver;

use PHPUnit\Framework\TestCase;
use Treast\BehatFixtureBridge\Resolver\ValueResolver;

final class ValueResolverTest extends TestCase
{
    private ValueResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ValueResolver();
    }

    public function testResolvesPublicProperty(): void
    {
        $entity = new class () {
            public int $id = 42;
        };

        $this->assertSame(42, $this->resolver->resolve($entity, self::class, 'id'));
    }

    public function testResolvesGetter(): void
    {
        $entity = new class () {
            public function getTitle(): string
            {
                return 'Germinal';
            }
        };

        $this->assertSame('Germinal', $this->resolver->resolve($entity, self::class, 'title'));
    }

    public function testPrefersGetterOverPublicProperty(): void
    {
        $entity = new class () {
            public string $title = 'raw';
            public function getTitle(): string
            {
                return 'computed';
            }
        };

        $this->assertSame('computed', $this->resolver->resolve($entity, self::class, 'title'));
    }

    public function testResolvesIsser(): void
    {
        $entity = new class () {
            public function isPublished(): bool
            {
                return true;
            }
        };

        $this->assertTrue($this->resolver->resolve($entity, self::class, 'published'));
    }

    public function testResolvesStaticFactoryMethod(): void
    {
        $entity = new class () {
            public string $slug = 'les-miserables';
        };

        $factory = new class () {
            public static function buildKey(object $entity): string
            {
                return 'prefix-' . $entity->slug;
            }
        };

        $this->assertSame(
            'prefix-les-miserables',
            $this->resolver->resolve($entity, $factory::class, '@buildKey')
        );
    }

    public function testThrowsWhenPropertyCannotBeRead(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot read "missing"/');
        $this->resolver->resolve(new \stdClass(), self::class, 'missing');
    }

    public function testThrowsWhenFactoryMethodDoesNotExist(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not exist/');
        $this->resolver->resolve(new \stdClass(), self::class, '@nope');
    }

    public function testThrowsWhenFactoryMethodIsNotStatic(): void
    {
        $factory = new class () {
            public function notStatic(object $entity): string
            {
                return '';
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/must be static/');
        $this->resolver->resolve(new \stdClass(), $factory::class, '@notStatic');
    }

    /**
     * @dataProvider provideNormalizesScalarAndStringableValuesCases
     */
    public function testNormalizesScalarAndStringableValues(mixed $input, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->normalize($input, 'ctx'));
    }

    public static function provideNormalizesScalarAndStringableValuesCases(): iterable
    {
        yield 'string' => ['abc', 'abc'];
        yield 'int' => [42, '42'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, '0'];
        yield 'null' => [null, ''];
        yield 'enum' => [Suit::Hearts, 'H'];
        yield 'stringable' => [new class () {
            public function __toString(): string
            {
                return 'str';
            }
        }, 'str'];
        yield 'array of scalars' => [['a', 1, true], 'a-1-1'];
        yield 'datetime' => [new \DateTimeImmutable('2024-01-02T03:04:05+00:00'), '2024-01-02T03:04:05+00:00'];
    }

    public function testNormalizeRejectsUnserializableObjects(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be scalar/');
        $this->resolver->normalize(new \stdClass(), 'ctx');
    }
}

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}
