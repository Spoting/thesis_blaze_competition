# Common Usage 
docker run --rm --network host -i grafana/k6 run - <stress_tests/test.js


# Execute Scenario Spike Test
`docker run --rm --network host -i grafana/k6 run --scenario spikeTest - <stress_tests/stress_test.js`

# Execute Scenario Constant Test
`docker run --rm --network host -i grafana/k6 run --scenario constantTest - <stress_tests/stress_test.js`

<!-- # Convert Har of Grafana extention
docker pull grafana/har-to-k6:latest

docker run grafana/har-to-k6:latest archive.har > my-k6-script.js -->
