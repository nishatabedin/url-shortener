<?php

namespace App\Observability\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Histogram;
use Prometheus\Counter;
use Prometheus\Storage\InMemory;

class PrometheusMetrics
{
    private CollectorRegistry $registry;
    private Histogram $httpDuration;
    private Counter $httpResponses;
    private Histogram $dbQueryDuration;
    private string $serviceName;

    public function __construct()
    {
        $this->registry = new CollectorRegistry(new InMemory());
        $this->serviceName = (string) config('observability.service_name', 'laravel-service');

        $this->httpDuration = $this->registry->getOrRegisterHistogram(
            'laravel',
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['service', 'method', 'route', 'status'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5]
        );

        $this->httpResponses = $this->registry->getOrRegisterCounter(
            'laravel',
            'http_responses_total',
            'HTTP responses by status code',
            ['service', 'method', 'route', 'status']
        );

        $this->dbQueryDuration = $this->registry->getOrRegisterHistogram(
            'laravel',
            'db_query_duration_seconds',
            'Database query duration in seconds',
            ['service', 'connection'],
            [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1]
        );
    }

    public function registry(): CollectorRegistry
    {
        return $this->registry;
    }

    public function observeRequest(string $method, string $route, int $status, float $durationSeconds): void
    {
        $labels = [$this->serviceName, $method, $route, (string) $status];
        $this->httpDuration->observe($durationSeconds, $labels);
        $this->httpResponses->inc($labels);
    }

    public function observeDbQuery(string $connection, float $durationSeconds): void
    {
        $labels = [$this->serviceName, $connection];
        $this->dbQueryDuration->observe($durationSeconds, $labels);
    }
}
