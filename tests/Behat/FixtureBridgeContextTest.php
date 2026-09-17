<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\Tests\Behat;

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\TestCase;
use Treast\BehatFixtureBridge\Behat\FixtureBridgeContext;
use Treast\BehatFixtureBridge\IdExtractor\IdExtractorInterface;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;
use Treast\BehatFixtureBridge\Registry\RegistryFile;

final class FixtureBridgeContextTest extends TestCase
{
    private FixtureBridgeContext $context;
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bridge-' . bin2hex(random_bytes(6)) . '.json';

        $extractor = new class () implements IdExtractorInterface {
            public function extract(object $entity): array
            {
                return ['id' => 42];
            }
        };

        $registry = new FixtureRegistry(new RegistryFile($this->path), $extractor);
        $registry->register(new \stdClass(), 'film:42', [
            'titre' => 'Les Misérables',
            'annee' => 1982,
        ]);

        $this->context = new FixtureBridgeContext($registry);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testResolvesIdAsRawValue(): void
    {
        // Argument exact : la valeur brute est renvoyée, pas une string.
        $this->assertSame(42, $this->context->resolveFixtures('@[film:42]'));
    }

    public function testResolvesPropertyAsRawValue(): void
    {
        $this->assertSame('Les Misérables', $this->context->resolveFixtures('@[film:42][titre]'));
        $this->assertSame(1982, $this->context->resolveFixtures('@[film:42][annee]'));
    }

    public function testSubstitutesInsideUrl(): void
    {
        $this->assertSame(
            '/film/42/edit',
            $this->context->resolveFixtures('/film/@[film:42]/edit'),
        );
    }

    public function testSubstitutesMultiplePlaceholdersInSameString(): void
    {
        $this->assertSame(
            'Le film "Les Misérables" (1982) est en ligne',
            $this->context->resolveFixtures('Le film "@[film:42][titre]" (@[film:42][annee]) est en ligne'),
        );
    }

    public function testDoesNotTouchEmails(): void
    {
        $input = 'contact@example.com';
        $this->assertSame($input, $this->context->resolveFixtures($input));
    }

    public function testDoesNotTouchSocialHandles(): void
    {
        $input = 'Suivez @johndoe sur Mastodon';
        $this->assertSame($input, $this->context->resolveFixtures($input));
    }

    public function testExpandsPlaceholdersInTableNode(): void
    {
        $table = new TableNode([
            ['slug', 'titre'],
            ['@[film:42]', '@[film:42][titre]'],
        ]);

        $expanded = $this->context->iExpandFixturesInTable($table);

        $this->assertSame(
            [
                ['slug', 'titre'],
                ['42', 'Les Misérables'],
            ],
            $expanded->getRows(),
        );
    }
}
