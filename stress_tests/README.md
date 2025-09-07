# Common Usage 
docker run --rm --network host -i grafana/k6 run - <stress_tests/test.js


# Execute Scenario Spike Test
`docker run --rm --network host -i grafana/k6 run --scenario spikeTest - <stress_tests/stress_test.js`

# Execute Scenario Constant Test
`docker run --rm --network host -i grafana/k6 run --scenario constantTest - <stress_tests/stress_test.js`

<!-- # Convert Har of Grafana extention
docker pull grafana/har-to-k6:latest

docker run grafana/har-to-k6:latest archive.har > my-k6-script.js -->


# Clear Data
`kubectl exec -n blaze-competition -it blaze-competition-redis-0  -- /bin/sh`

`redis-cli --raw KEYS "competition:323*" | xargs redis-cli DEL`

`kubectl exec -n blaze-competition -it blaze-competition-db-0  -- /bin/sh`

`psql -h 127.0.0.1 -p 5432 -U app -d app`

`DELETE FROM submission ;`


`kubectl exec -n blaze-competition -it blaze-competition-rabbitmq-0  -- /bin/bash`

`rabbitmqadmin -u guest -p guest list queues name -f tsv | xargs -I {} rabbitmqadmin -u guest -p guest purge queue name={}`
