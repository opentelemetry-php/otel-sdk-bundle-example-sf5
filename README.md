# OTEL SDK-BUNDLE EXAMPLE

This is a Symfony demo application for the OpenTelemetry SdkBundle.

This project is based on dunglas' [Symfony Docker](https://github.com/dunglas/symfony-docker) template, so for a
general documentation, please take a look there.

Apart from the demo application code the installation of the PHP gRPC extension has been added to the definition
of the [PHP image](/Dockerfile). Jaeger and Zipkin collector services have been added to the 
[docker-compose.yml](/docker-compose.yml).

## Usage:

- Clone (or fork and clone) this repository
- in the root directory of the project run:
```bash 
docker-compose up
```

- The first time you start the services, the images will be build. Keep in mind, that compiling the gRPC extension can 
take quite a while (10-15 mins), so please be patient (or go grab a coffee or do something else, while the 
images are built). We will address this issue in the future by proving a base image which will already include
the gRPC extension.

- Once the services are starting, wait until the PHP (fpm) service tells that it can accept requests.

- Per default the Caddy front server will listen on the ports 8081 and 8443, but you can change this in the [.env](.env) file.

- Open http://localhost:8443/hello (ignore the security warning and/or accept the local certificate Caddy issued), and you
should see the appropriate demo page.

- Follow the links on the page to the Jaeger and Zipkin GUIs and search under "OtelBundle Demo app" for the tracing reports.
You should see 4 spans (3 child spans), with events and attributes added to the main span.
- Go to the [config file](/config/packages/otel_sdk.yaml) for the bundle and change the sampler from `always_on` to 
`always_off`. Now if you reload the demo page, you will see that no tracing data has been sent to the collectors.

Feel free to play around with the [configuration](/config/packages/otel_sdk.yaml) and the [Subscriber](/src/EventSubscriber/OtelKernelSubscriber.php) 
and [Controller](/src/Controller/HelloController.php) classes. You don't need to restart 
the docker services to see your code changes taking effect.



