# Usage
docker run --rm --network host -i grafana/k6 run - <stress_tests/test.js

<!-- # Convert Har of Grafana extention
docker pull grafana/har-to-k6:latest

docker run grafana/har-to-k6:latest archive.har > my-k6-script.js -->
