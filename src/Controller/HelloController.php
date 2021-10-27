<?php

namespace App\Controller;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenTelemetry\SDK\Trace\Tracer;
use OpenTelemetry\Instrumentation\Symfony\OtelSdkBundle;

class HelloController extends AbstractController
{
    private const TEMPLATE = 'hello/index.html.twig';
    private const DEFAULT_TRACER = OtelSdkBundle\DependencyInjection\Tracer::DEFAULT_KEY;

    private TracerProvider $provider;
    private string $jaegerGuiUrl;
    private string $zipkinGuiUrl;

     public function __construct(TracerProvider $provider, string $jaegerGuiUrl, string $zipkinGuiUrl)
     {
         $this->provider = $provider;
         $this->jaegerGuiUrl = $jaegerGuiUrl;
         $this->zipkinGuiUrl = $zipkinGuiUrl;
     }

     /**
     * @Route("/hello", name="hello")
     */
    public function index(): Response
    {
        $controllerSpan = null;
        $templateSpan = null;
        // check if we should sample
        if ($this->shouldSample()) {
            // main controller span
            $controllerSpan = $this->startSpan(__METHOD__);
        }

        $controllerSpan->addEvent('Start doing stuff');
        // simulate some computation
        usleep(50000);
        $controllerSpan->addEvent('Finished doing stuff');

        // check if we should sample
        if ($this->shouldSample()) {
            // template render span
            $templateSpan = $this->startSpan('render:'.self::TEMPLATE);
        }
        // render HTML
        $result = $this->render(self::TEMPLATE, [
            'jaeger_gui_url' => $this->jaegerGuiUrl,
            'zipkin_gui_url' =>$this->zipkinGuiUrl,
            'controller_span_id' => $controllerSpan ? $controllerSpan->getContext()->getSpanId() : 'not-sampled',
            'template_span_id' => $templateSpan ? $templateSpan->getContext()->getSpanId() : 'not-sampled'
        ]);

        // end spans if they have been created
        foreach ([$templateSpan, $controllerSpan] as $span) {
            if ($span instanceof SpanInterface) {
                $span->end();
            }
        }

        // return rendered HTML
        return $result;
    }

    private function startSpan(string $name): SpanInterface
    {
        return $this->provider->getTracer(self::DEFAULT_TRACER)
            ->spanBuilder($name)
            ->startSpan();
    }

    private function shouldSample(): bool
    {
        return SamplingResult::RECORD_AND_SAMPLE === $this->provider->getSampler()->shouldSample(
                Context::getCurrent(),
                (new RandomIdGenerator())->generateTraceId(),
                '',
                SpanKind::KIND_INTERNAL
            )->getDecision();
    }
}
