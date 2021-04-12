# CrassulaGoogleCloudErrorReportingBundle

This bundle provides integration with [Google Cloud Error Reporting](https://cloud.google.com/error-reporting/) in your Symfony project.

__Note:__ This bundle uses [Google Cloud Error Reporting for PHP](https://github.com/googleapis/google-cloud-php-errorreporting) package, which currently is in alpha stage, so BC breaks to be expected.

## Prerequisites

This bundle requires Symfony 3.3+. Additionally you may want to install grpc and protobuf PECL extensions.

## Installation

Add [`crassula/google-cloud-error-reporting-bundle`](https://packagist.org/packages/crassula/google-cloud-error-reporting-bundle) to your composer.json file:

```bash
$ composer require crassula/google-cloud-error-reporting-bundle
```

Register the bundle in app/AppKernel.php:

```php
public function registerBundles()
{
    return array(
        // ...
        new Crassula\Bundle\GoogleCloudErrorReportingBundle\CrassulaGoogleCloudErrorReportingBundle(),
    );
}
```

## Authentication

Please see [Google's Authentication guide](https://github.com/googleapis/google-cloud-php/blob/master/AUTHENTICATION.md) for information on authenticating the client. Once authenticated, you'll be ready to start making requests.

## Configuration

Minimal configuration in your `app/config/config.yml`:

```yaml
crassula_google_cloud_error_reporting:
    enabled: true
    project_id: project-12345
    service: app_name
    client_options:
        credentials: /etc/gcp_credentials.json
```

By default error reporting is disabled, so you have to explicitly enable it where you need it (e.g. in `app/config/config_prod.yml`).

For full configuration reference run:

```bash
$ bin/console config:dump-reference CrassulaGoogleCloudErrorReportingBundle
```

## Sample

```php
use Crassula\Bundle\GoogleCloudErrorReportingBundle\Service\ErrorReporter;

try {
    $this->doFaultyOperation();
} catch (\Exception $e) {
    $container->get(ErrorReporter::class)->report($e);
}
```

You can additionally pass options as a second argument:

| Name | Description
| --- | ---
| http_request              | Instance of `Symfony\Component\HttpFoundation\Request` to report HTTP method, URL, user agent, referrer and remote IP address. If not set, bundle will attempt to retrieve master request from request stack.
| http_response_status_code | Response status code.
| user                      | Affected user's name, email, login or other username. If not set, bundle will attempt to retrieve username from token storage.
| request_options           | Options related to Google Cloud Error Reporting package: <br><ul><li>retrySettings - See `\Google\ApiCore\RetrySettings::__construct` for available options</li></ul>

## Notes

#### About automatic error reporting

When config option `use_listeners` is enabled, bundle registers event listeners for `kernel.exception` and `console.error` events with priority _2048_.

Errors are reported on `kernel.terminate` and `console.terminate`.

This option is enabled by default. 

#### Exception handling

Reporter catches and logs exceptions related to bad configuration of Google Cloud Error Reporting package.

## License
This package is available under the [MIT license](LICENSE).
