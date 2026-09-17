<?php

declare(strict_types=1);

namespace Treast\BehatFixtureBridge\EventListener;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Treast\BehatFixtureBridge\Registry\FixtureRegistry;

final readonly class RegistryFlushListener implements EventSubscriberInterface
{
    public function __construct(private FixtureRegistry $registry)
    {
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        $this->registry->flush();
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->registry->flush();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->registry->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::postFlush => 'postFlush',
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }
}
