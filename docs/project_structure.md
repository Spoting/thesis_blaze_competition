# Project Structure

## App
- `src/`: Application Code
- `public/`: Containts index.php and bundles
- `config/`: Symfony Configurations yamls
- `assets/`: CSS/JS files
- `templates/`: Twigs
- `translations/`: Translations
- `migrations/`: Doctrine's Migration File

## Infrastructure/Services
- `frankenphp/`: Frankenphp/Caddy Configurations, divided for dev/prod. Also contains Custom Certifications `certs/`
- `jenkins/`: Contains Dockerfile for setting up Jenskins and instructions.
- `k8s/`: Contains Manifests and Kustomizations for Kubernetes.
- `monitoring/`: Contains Configuration for Grafana/Prometheus Images.

