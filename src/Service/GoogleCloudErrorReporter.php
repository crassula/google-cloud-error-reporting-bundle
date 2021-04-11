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

namespace Crassula\Bundle\GoogleCloudErrorReportingBundle\Service;

use Google\Cloud\ErrorReporting\V1beta1\ErrorContext;
use Google\Cloud\ErrorReporting\V1beta1\HttpRequestContext;
use Google\Cloud\ErrorReporting\V1beta1\ReportedErrorEvent;
use Google\Cloud\ErrorReporting\V1beta1\ReportErrorsServiceClient;
use Google\Cloud\ErrorReporting\V1beta1\ServiceContext;
use Google\Protobuf\Timestamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class GoogleCloudErrorReporter implements ErrorReporter
{
    private array $config;
    private TokenStorageInterface $tokenStorage;
    private LoggerInterface $logger;
    private RequestStack $requestStack;

    public function __construct(
        array $config,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        RequestStack $requestStack
    ) {
        $this->config = $config;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
    }

    public function report(\Throwable $error, array $options = []): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        if ($this->isErrorIgnored($error)) {
            return false;
        }

        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve($options);

        $errorEvent = $this->createErrorEvent($error);
        $errorContext = $errorEvent->getContext();

        $this->addHttpRequestContext($errorContext, $options);
        $this->addUser($errorContext, $options);

        return $this->reportErrorEvent($errorEvent, $options);
    }

    private function createErrorEvent(\Throwable $error): ReportedErrorEvent
    {
        $errorEvent = new ReportedErrorEvent();

        $errorAsString = (string) $error;
        $errorEvent->setMessage('PHP Fatal error: '.$errorAsString);

        $eventTime = new Timestamp();
        $utcDate = new \DateTime('now', new \DateTimeZone('UTC'));
        $eventTime->setSeconds($utcDate->format('U'));
        $eventTime->setNanos((int) $utcDate->format('u'));
        $errorEvent->setEventTime($eventTime);

        $errorContext = new ErrorContext();
        $errorEvent->setContext($errorContext);

        $serviceContext = new ServiceContext();
        $serviceContext->setService($this->config['service']);
        $errorEvent->setServiceContext($serviceContext);

        return $errorEvent;
    }

    private function reportErrorEvent(ReportedErrorEvent $errorEvent, array $options = []): bool
    {
        $client = null;

        try {
            $projectName = ReportErrorsServiceClient::projectName(
                $this->config['project_id']
            );

            $client = new ReportErrorsServiceClient($this->config['client_options']);
            $client->reportErrorEvent($projectName, $errorEvent, $options['request_options']);
        } catch (\Throwable $e) {
            $this->logClientError($e);

            if ($client !== null) {
                $client->close();
            }

            return false;
        }

        return true;
    }

    private function getUsernameFromToken(): string
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null || $token->getUser() === null) {
            return '';
        }

        return $token->getUsername();
    }

    private function createHttpRequestContext(
        Request $request = null,
        ?int $responseStatusCode = null
    ): HttpRequestContext {
        $httpRequestContext = new HttpRequestContext();
        $httpRequestContext->setMethod($request->getMethod());
        $httpRequestContext->setRemoteIp($request->getClientIp());
        $httpRequestContext->setUrl($request->getUri());
        $httpRequestContext->setReferrer($request->headers->get('Referer', ''));
        $httpRequestContext->setUserAgent($request->headers->get('User-Agent', ''));

        if ($responseStatusCode !== null) {
            $httpRequestContext->setResponseStatusCode($responseStatusCode);
        }

        return $httpRequestContext;
    }

    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'http_request' => function (Options $options): ?Request {
                return $this->requestStack->getMasterRequest();
            },
            'http_response_status_code' => null,
            'user' => null,
            'request_options' => [],
        ]);

        $resolver->setAllowedTypes('http_request', ['null', Request::class]);
        $resolver->setAllowedTypes('http_response_status_code', ['null', 'int']);
        $resolver->setAllowedTypes('user', ['null', 'string']);
        $resolver->setAllowedTypes('request_options', 'array');
    }

    private function addHttpRequestContext(ErrorContext $errorContext, array $options): void
    {
        $httpRequest = $options['http_request'];

        if ($httpRequest === null) {
            return;
        }

        $httpResponseCode = $options['http_response_status_code'];
        $httpRequestContext = $this->createHttpRequestContext(
            $httpRequest,
            $httpResponseCode
        );
        $errorContext->setHttpRequest($httpRequestContext);
    }

    private function addUser(ErrorContext $errorContext, array $options): void
    {
        if ($options['user'] === null && $options['http_request'] !== null) {
            $username = $this->getUsernameFromToken();
            $errorContext->setUser($username);
        } elseif ($options['user'] !== null) {
            $errorContext->setUser($options['user']);
        }
    }

    private function logClientError(\Throwable $error): void
    {
        $message = sprintf('%s: %s', get_class($error), $error->getMessage());

        $this->logger->error($message, [
            'trace' => $error->getTraceAsString(),
        ]);
    }

    private function isErrorIgnored(\Throwable $error): bool
    {
        foreach ($this->config['ignored_errors'] as $className) {
            if ($error instanceof $className) {
                return true;
            }
        }

        return false;
    }
}
