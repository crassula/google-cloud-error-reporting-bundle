<?php

declare(strict_types=1);

namespace Crassula\Bundle\GoogleCloudErrorReportingBundle\Service;

interface ErrorReporter
{
    public function report(\Throwable $error, array $options = []): bool;
}
