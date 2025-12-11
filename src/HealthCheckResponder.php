<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks;

use Symfony\Component\HttpFoundation\JsonResponse;
use Vigilant\HealthChecksBase\BuildResponse;

class HealthCheckResponder
{
    public function __construct(
        private readonly BuildResponse $builder,
        private readonly HealthCheckRegistry $registry
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->builder->build(
            $this->registry->getChecks(),
            $this->registry->getMetrics()
        );
    }

    public function sendResponse(): void
    {
        (new JsonResponse($this->payload(), JsonResponse::HTTP_OK))->send();
    }
}
