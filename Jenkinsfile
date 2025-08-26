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
                // Step 1: Build the image using compose.prod since we want production image
                echo "Building image from compose files..."
                sh "docker compose -f compose.yaml -f compose.prod.yaml build"

                // Step 2: Tag the built image for the microk8s registry
                echo "Tagging image as ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
                sh "docker tag ${IMAGE_NAME}:latest ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"

                // Step 3: Push the newly tagged image to microk8s registry
                echo "Pushing image to registry..."
                sh "docker push ${REGISTRY_URL}/${IMAGE_NAME}:${IMAGE_TAG}"
            }
        }

        stage('Deploy') {
            steps {
                script {
                    // -- Step 1: Update the Kustomization manifest --
                    echo "Checking out the main branch..."
                    sh "git checkout main"

		            echo "Updating kustomization.yaml with new tag: ${IMAGE_TAG}"
                    // Use sed to find the 'newTag:' line and replace it
                    sh "sed -i 's|newTag: .*|newTag: ${IMAGE_TAG}|' k8s/kustomization.yaml"

                    // -- Step 2: Commit and push the updated manifest to Git --
                    echo "Committing manifest changes to Git..."
                    sshagent (credentials: ['github-ssh']) {
                        sh '''
                            git config user.email "jenkins@your-ci.com"
                            git config user.name "jenkins-bot"

                            git add k8s/kustomization.yaml
                            git commit -m "ci: Deploy new image ${IMAGE_TAG}" || echo "No changes to commit"
                            git push origin main
                        '''
                    }

                    // -- Step 3: Apply the manifests to the Kubernetes cluster --
                    echo "Applying manifests to Kubernetes via Kustomize..."
                    // The -k flag tells kubectl to use the kustomization.yaml file
                    sh "kubectl apply -k k8s/"
                }
            }
        }
    }
}
