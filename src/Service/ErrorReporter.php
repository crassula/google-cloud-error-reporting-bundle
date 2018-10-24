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
use Google\Cloud\ErrorReporting\V1beta1\ReportErrorEventResponse;
use Google\Cloud\ErrorReporting\V1beta1\ReportErrorsServiceClient;
use Google\Cloud\ErrorReporting\V1beta1\ServiceContext;
use Google\Protobuf\Timestamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @author Vladislav Nikolayev <luxemate1@gmail.com>
 */
class ErrorReporter
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param array           $config
     * @param TokenStorage    $tokenStorage
     * @param LoggerInterface $logger
     */
    public function __construct(
        array $config,
        TokenStorage $tokenStorage,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
    }

    /**
     * @param \Exception $exception
     * @param array      $options
     *
     * @return null|ReportErrorEventResponse
     */
    public function report(\Exception $exception, array $options = []): ?ReportErrorEventResponse
    {
        if (!$this->config['enabled']) {
            return null;
        }

        if ($exception == null || !$exception instanceof \Exception) {
            return null;
        }

        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve($options);

        $errorEvent = $this->createErrorEvent($exception);
        $errorContext = $errorEvent->getContext();

        if (null !== $options['http_request']) {
            $httpRequestContext = $this->createHttpRequestContext(
                $options['http_request'],
                $options['http_response']
            );
            $errorContext->setHttpRequest($httpRequestContext);
        }

        if (null === $options['user'] && null !== $options['http_request']) {
            $username = $this->getUsernameFromToken();
            $errorContext->setUser($username);
        } elseif (null !== $options['user']) {
            $errorContext->setUser($options['user']);
        }

        return $this->reportErrorEvent($errorEvent, $options);
    }

    /**
     * @param \Exception $exception
     *
     * @return ReportedErrorEvent
     */
    private function createErrorEvent(\Exception $exception): ReportedErrorEvent
    {
        $errorEvent = new ReportedErrorEvent();

        $exceptionAsString = (string) $exception;
        $errorEvent->setMessage('PHP Fatal error: '.$exceptionAsString);

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

    /**
     * @param ReportedErrorEvent $errorEvent
     * @param array              $options
     *
     * @return null|ReportErrorEventResponse
     */
    private function reportErrorEvent(ReportedErrorEvent $errorEvent, array $options = []): ?ReportErrorEventResponse
    {
        try {
            $client = new ReportErrorsServiceClient($this->config['client_options']);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);

            return null;
        }

        try {
            $projectName = ReportErrorsServiceClient::projectName(
                $this->config['project_id']
            );

            return $client->reportErrorEvent($projectName, $errorEvent, $options['request_options']);
        } catch (\Exception $e) {
            $client->close();

            $this->logger->error($e->getMessage(), [
                'exception' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * @return string
     */
    private function getUsernameFromToken(): string
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return '';
        }

        $user = $token->getUser();

        if ($user === null) {
            return '';
        }

        return $token->getUsername();
    }

    /**
     * @param Request  $request
     * @param null|Response $response
     *
     * @return HttpRequestContext
     */
    private function createHttpRequestContext(Request $request, ?Response $response = null): HttpRequestContext
    {
        $httpRequestContext = new HttpRequestContext();
        $httpRequestContext->setMethod($request->getMethod());
        $httpRequestContext->setRemoteIp($request->getClientIp());
        $httpRequestContext->setUrl($request->getUri());
        $httpRequestContext->setReferrer($request->headers->get('Referer', ''));
        $httpRequestContext->setUserAgent($request->headers->get('User-Agent', ''));

        if (null !== $response) {
            $httpRequestContext->setResponseStatusCode($response->getStatusCode());
        }

        return $httpRequestContext;
    }

    /**
     * @param OptionsResolver $resolver
     */
    private function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'http_request' => null,
            'http_response' => null,
            'user' => null,
            'request_options' => [],
        ]);

        $resolver->setAllowedTypes('http_request', ['null', Request::class]);
        $resolver->setAllowedTypes('http_response', ['null', Response::class]);
        $resolver->setAllowedTypes('user', ['null', 'string']);
        $resolver->setAllowedTypes('request_options', 'array');
    }
}
