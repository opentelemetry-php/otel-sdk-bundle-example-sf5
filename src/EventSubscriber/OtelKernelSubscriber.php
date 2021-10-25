<?php

namespace App\EventSubscriber;

use App\Kernel;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Trace\Tracer;
use Symfony\Contracts\EventDispatcher\Event as BaseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event;
use Symfony\Component\HttpFoundation\Request;

class OtelKernelSubscriber implements EventSubscriberInterface
{

    private Tracer $tracer;
    private SpanInterface $mainSpan;
    private SpanInterface $requestSpan;

    public function __construct(Tracer $tracer, Kernel $kernel)
    {
        $this->tracer = $tracer;
        $this->mainSpan = $tracer->spanBuilder('main')
            ->startSpan();

        $this->mainSpan->activate();
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

    public function onRequestEvent(Event\RequestEvent $event)
    {
        $this->addEvent($event);

        if ($event->isMainRequest()) {
            $this->requestSpan = $this->tracer->spanBuilder('kernel')->startSpan();
        }

        $this->populateRequestAttributes($event->getRequest());
    }

    public function onControllerEvent(Event\ControllerEvent $event)
    {
        $this->addEvent($event);

    }

    public function onControllerArgumentsEvent(Event\ControllerArgumentsEvent $event)
    {
        $this->addEvent($event);

    }

    public function onViewEvent(Event\ViewEvent $event)
    {
        $this->addEvent($event);

    }

    public function onResponseEvent(Event\ResponseEvent $event)
    {
        $this->addEvent($event);

    }

    public function onFinishRequestEvent(Event\FinishRequestEvent $event)
    {
        $this->addEvent($event);

    }

    public function onTerminateEvent(Event\TerminateEvent $event)
    {
        $this->addEvent($event);

        if ($event->isMainRequest()) {
            $this->requestSpan->end();
            $this->mainSpan->end();
        }
    }

    public function onExceptionEvent(Event\ExceptionEvent $event)
    {
        $this->mainSpan->recordException($event->getThrowable());
    }

    private function addEvent(BaseEvent $event)
    {
        $this->mainSpan->addEvent(get_class($event));
    }

    private function populateRequestAttributes(Request $request)
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
}
