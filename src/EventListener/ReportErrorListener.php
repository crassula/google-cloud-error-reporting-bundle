<?php

/*
 * This file is part of the crassula/google-cloud-error-reporting-bundle package.
 *
 * (c) Crassula <hello@crassula.io>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Crassula\Bundle\GoogleCloudErrorReportingBundle\EventListener;

use Crassula\Bundle\GoogleCloudErrorReportingBundle\Service\ErrorReporter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Vladislav Nikolayev <luxemate1@gmail.com>
 */
class ReportErrorListener implements EventSubscriberInterface
{
    /**
     * @var ErrorReporter
     */
    private $errorReporter;

    /**
     * @var \Throwable
     */
    private $error;

    /**
     * Constructor.
     *
     * @param ErrorReporter $errorReporter
     */
    public function __construct(ErrorReporter $errorReporter)
    {
        $this->errorReporter = $errorReporter;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2048],
            KernelEvents::TERMINATE => 'reportError',
            ConsoleEvents::ERROR => ['onConsoleError', 2048],
            ConsoleEvents::TERMINATE => 'reportError',
        ];
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $this->error = $event->getException();
    }

    /**
     * @param ConsoleErrorEvent $event
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->error = $event->getError();
    }

    /**
     * @param Event $event
     */
    public function reportError(Event $event): void
    {
        if ($this->error === null || !$this->error instanceof \Throwable) {
            return;
        }

        $request = null;
        $responseStatusCode = null;

        if ($event instanceof PostResponseEvent) {
            $request = $event->getRequest();
            $responseStatusCode = $event->getResponse()->getStatusCode();
        }

        $this->errorReporter->report($this->error, [
            'http_request' => $request,
            'http_response_status_code' => $responseStatusCode,
        ]);
    }
}
