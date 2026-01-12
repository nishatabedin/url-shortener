<?php

namespace App\Http\Controllers\Observability;

use App\Observability\Metrics\PrometheusMetrics;
use Prometheus\RenderTextFormat;

class MetricsController
{
    public function __invoke(PrometheusMetrics $metrics)
    {
        $renderer = new RenderTextFormat();
        $content = $renderer->render($metrics->registry()->getMetricFamilySamples());

        return response($content, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
