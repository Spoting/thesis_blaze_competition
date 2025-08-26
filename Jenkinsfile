pipeline {
    agent any

    environment {
        REGISTRY_URL = "192.168.56.11:32000"
        IMAGE_NAME   = "app-php-prod"
        IMAGE_TAG    = "v1.0.${BUILD_NUMBER}"
    }

    stages {
        stage('Build and Push Image') {
            steps {
                // Step 1: Build the image using your compose files
                echo "Building image from compose files..."
                sh "docker compose -f compose.yaml -f compose.prod.yaml build"

                // Step 2: Tag the built image for the registry
                // The source image is 'app-php-prod:latest' as defined by your compose files
                echo "Tagging image as ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
                sh "docker tag ${IMAGE_NAME}:latest ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"

                // Step 3: Push the newly tagged image
                echo "Pushing image to registry..."
                sh "docker push ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
            }
        }

        // You would add your 'Deploy to Kubernetes' stage here
    }
}