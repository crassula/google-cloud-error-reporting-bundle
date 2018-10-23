# CrassulaGoogleCloudErrorReportingBundle

This bundle provides [Google Cloud Error Reporting](https://github.com/googleapis/google-cloud-php-errorreporting) integration with your Symfony project.

## Minimal Configuration

```yaml
crassula_google_cloud_error_reporting:
    enabled: true
    project_id: project-12345
    service: app_name
    client_options:
        credentials: /etc/gcp_credentials.json
```

 For full configuration reference run:


 ```bash
bin/console config:dump-reference CrassulaGoogleCloudErrorReportingBundle
```

## License
This package is available under the [MIT license](LICENSE).
