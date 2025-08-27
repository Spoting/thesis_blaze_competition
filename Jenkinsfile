pipeline {
    agent any

    environment {
        REGISTRY_URL = "192.168.56.11:32000"
        IMAGE_NAME   = "app-php-prod"
        IMAGE_TAG    = "v1.0.${BUILD_NUMBER}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build and Push Image') {
            steps {
                // Build the image using compose.prod since we want production image
                echo "Building image from compose files..."
                sh "docker compose -f compose.yaml -f compose.prod.yaml build"

                // Tag image pr
                echo "Tagging image as ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
                sh "docker tag ${IMAGE_NAME}:latest ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"

                // Push microk8s registry
                echo "Pushing image to registry..."
                sh "docker push ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
            }
        }

        stage('Deploy') {
            steps {
                script {
                    sshagent (credentials: ['github-ssh']) {
                        sh '''
                            git config user.email "jenkins@your-ci.com"
                            git config user.name "jenkins-bot"

                            echo "Rebasing 'main' branch..."
                            git fetch origin main
                            git checkout main
                            git rebase origin/main
                            
                            echo "Updating kustomization.yaml with new tag: ${IMAGE_TAG}"
                            sed -i "s|newTag: .*|newTag: ${IMAGE_TAG}|" k8s/kustomization.yaml

                            echo "Committing manifest changes to Git..."
                            git add k8s/kustomization.yaml
                            git commit -m "ci: Deploy new image ${IMAGE_TAG}" || echo "No changes to commit"
                            git push origin main
                        '''
                    }

                    // -- Apply the manifests to the Kubernetes cluster --
                    echo "Applying manifests to Kubernetes via Kustomize..."
                    sh "kubectl apply -k k8s/"
                }
            }
        }
    }
}
