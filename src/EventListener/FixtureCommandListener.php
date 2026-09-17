<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;

final readonly class FixtureCommandListener implements EventSubscriberInterface
{
    private const array PURGE_COMMANDS = [
        'doctrine:fixtures:load',
        'doctrine:fixtures:load-fixtures',
    ];

    public function __construct(private FixtureRegistry $registry)
    {
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        if (!\in_array($event->getCommand()?->getName(), self::PURGE_COMMANDS, true)) {
            return;
        }

        $input = $event->getInput();
        if ($input->hasParameterOption('--append') || $input->hasParameterOption('-a')) {
            return;
        }

        $this->registry->clear();
    }

    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::COMMAND => 'onConsoleCommand'];
    }
}
