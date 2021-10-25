<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenTelemetry\SDK\Trace\Tracer;

class HelloController extends AbstractController
{
    private const TEMPLATE = 'hello/index.html.twig';

    private Tracer $tracer;
    private string $jaegerGuiUrl;
    private string $zipkinGuiUrl;

     public function __construct(Tracer $tracer, string $jaegerGuiUrl, string $zipkinGuiUrl)
     {
         $this->tracer = $tracer;
         $this->jaegerGuiUrl = $jaegerGuiUrl;
         $this->zipkinGuiUrl = $zipkinGuiUrl;
     }

     /**
     * @Route("/hello", name="hello")
     */
    public function index(): Response
    {
        // main controller span
        $controllerSpan = $this->tracer->spanBuilder(__METHOD__)->startSpan();
        // simulate some computation
        usleep(50000);

        // template render span
        $templateSpan = $this->tracer->spanBuilder('render:'.self::TEMPLATE)->startSpan();
        $result=  $this->render(self::TEMPLATE, [
            'jaeger_gui_url' => $this->jaegerGuiUrl,
            'zipkin_gui_url' =>$this->zipkinGuiUrl,
            'controller_span_id' => $controllerSpan->getContext()->getSpanId(),
            'template_span_id' => $templateSpan->getContext()->getSpanId()
        ]);
        $templateSpan->end();
        $controllerSpan->end();

        return $result;
    }

    /**
     * @Route("/i", name="i")
     */
    public function info()
    {
        $controllerSpan = $this->tracer->spanBuilder(__METHOD__)->startSpan();
        ob_start();
        phpinfo();
        $res = ob_get_flush();
        $controllerSpan->end();

        return new Response($res);
    }
}
