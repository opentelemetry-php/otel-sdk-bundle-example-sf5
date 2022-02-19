<?php

namespace App\EventSubscriber;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Symfony\OtelSdkBundle;
use Symfony\Contracts\EventDispatcher\Event as BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpFoundation\Request;

class OtelKernelSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_ROOT_SPAN_NAME = 'main';

    private TracerProvider $provider;
    private ?SpanInterface $mainSpan = null;
    private ?SpanInterface $requestSpan = null;
    private ?bool $shouldSample = null;

    public function __construct(TracerProvider $provider)
    {
        $this->setTracerProvider($provider);

        if($this->shouldSample()){
            $this->mainSpan = $this->startSpan(self::DEFAULT_ROOT_SPAN_NAME);
            $this->mainSpan->activate();
        }
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::REQUEST => [['onRequestEvent', 10000]],
            KernelEvents::CONTROLLER => [['onControllerEvent', 10000]],
            KernelEvents::CONTROLLER_ARGUMENTS => [['onControllerArgumentsEvent', 10000]],
            KernelEvents::VIEW => [['onViewEvent', 10000]],
            KernelEvents::RESPONSE => [['onResponseEvent', 10000]],
            KernelEvents::FINISH_REQUEST => [['onFinishRequestEvent', -10000]],
            KernelEvents::TERMINATE => [['onTerminateEvent', -10000]],
            KernelEvents::EXCEPTION => [['onExceptionEvent', 10000]],
        ];
    }

    public function onRequestEvent(Event\RequestEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);

        if ($event->isMainRequest()) {
            $this->requestSpan = $this->startSpan('kernel');

            $this->mainSpan->updateName($event->getRequest()->getRequestUri());
            $this->populateRequestAttributes($event->getRequest());
        }
    }

    public function onControllerEvent(Event\ControllerEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);
    }

    public function onControllerArgumentsEvent(Event\ControllerArgumentsEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);
    }

    public function onViewEvent(Event\ViewEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);
    }

    public function onResponseEvent(Event\ResponseEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);
    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);
    }

    public function onTerminateEvent(Event\TerminateEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->addEvent($event);

        if ($event->isMainRequest()) {
            $this->requestSpan->end();
            $this->mainSpan->end();
        }
    }

    public function onExceptionEvent(Event\ExceptionEvent $event): void
    {
        if ($this->shouldSample() === false) {
            return;
        }

        $this->mainSpan->recordException($event->getThrowable());
    }

    private function addEvent(BaseEvent $event): void
    {
        $this->mainSpan->addEvent(get_class($event));
    }

    private function populateRequestAttributes(Request $request): void
    {
        $this->mainSpan->setAttribute('http.method', $request->getMethod());
        $this->mainSpan->setAttribute('http.url', $request->getUri());
        $this->mainSpan->setAttribute('http.target', $request->getPathInfo());
        $this->mainSpan->setAttribute('http.host', $request->getHttpHost());
        $this->mainSpan->setAttribute('http.scheme', $request->getScheme());
        $this->mainSpan->setAttribute('http.flavor', $request->getProtocolVersion());
        $this->mainSpan->setAttribute('http.user_agent', $request->headers->get('user-agent'));
        $this->mainSpan->setAttribute('http.request_content_length', $request->headers->get('content-length'));
    }

    private function setTracerProvider(TracerProvider $provider): void
    {
        $this->provider = $provider;
    }

    private function getTracerProvider(): TracerProvider
    {
        return $this->provider;
    }

    private function getTracer(): TracerInterface
    {
        return $this->getTracerProvider()->getTracer(
            OtelSdkBundle\DependencyInjection\Tracer::DEFAULT_KEY
        );
    }

    private function getSampler(): SamplerInterface
    {
        return $this->getTracerProvider()->getSampler();
    }

    private function getSpanBuilder(string $name): SpanBuilderInterface
    {
        return $this->getTracer()->spanBuilder($name);
    }

    private function startSpan(string $name): SpanInterface
    {
        return $this->getTracer()->spanBuilder($name)->startSpan();
    }

    private function shouldSample(): bool
    {
        return is_bool($this->shouldSample)
            ? $this->shouldSample
            : $this->shouldSample =
                SamplingResult::RECORD_AND_SAMPLE === $this->getSampler()->shouldSample(
                    Context::getCurrent(),
                    (new RandomIdGenerator())->generateTraceId(),
                    '',
                    SpanKind::KIND_INTERNAL
                )->getDecision();
    }
}
