#!/bin/sh
set -eu

: "${CONSUL_HTTP_ADDR:=http://autodiscovery:8500}"
: "${CONSUL_SERVICE_NAME:?CONSUL_SERVICE_NAME is required}"
: "${CONSUL_SERVICE_PORT:=9000}"

# Find this container's Docker network IP.
SERVICE_IP="$(hostname -i | awk '{print $1}')"

if [ -z "$SERVICE_IP" ]; then
    echo "Could not determine the service IP" >&2
    exit 1
fi

echo "Registering ${CONSUL_SERVICE_NAME} at ${SERVICE_IP}:${CONSUL_SERVICE_PORT}"

# Wait for Consul to become reachable.
until curl -fsS "${CONSUL_HTTP_ADDR}/v1/status/leader" >/dev/null; do
    echo "Waiting for Consul..."
    sleep 2
done

# Register this service and its TCP health check.
curl --fail --silent --show-error \
    --request PUT \
    --header "Content-Type: application/json" \
    --data "{
      \"Name\": \"${CONSUL_SERVICE_NAME}\",
      \"ID\": \"${CONSUL_SERVICE_NAME}\",
      \"Address\": \"${SERVICE_IP}\",
      \"Port\": ${CONSUL_SERVICE_PORT},
      \"Tags\": [\"symfony\", \"php-fpm\"],
      \"Check\": {
        \"TCP\": \"${SERVICE_IP}:${CONSUL_SERVICE_PORT}\",
        \"Interval\": \"10s\",
        \"Timeout\": \"2s\",
        \"DeregisterCriticalServiceAfter\": \"1m\"
      }
    }" \
    "${CONSUL_HTTP_ADDR}/v1/agent/service/register"

echo "Registered ${CONSUL_SERVICE_NAME}"

# Start PHP-FPM in the foreground.
exec php-fpm -F
