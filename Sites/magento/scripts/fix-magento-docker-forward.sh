#!/usr/bin/env bash
set -euo pipefail

NETWORK_NAME="${1:-magento_default}"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker CLI is not available."
  exit 1
fi

if ! docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
  echo "Docker network '$NETWORK_NAME' does not exist. Start the project first."
  exit 0
fi

NETWORK_ID="$(docker network inspect "$NETWORK_NAME" -f '{{.Id}}')"
BRIDGE_NAME="$(docker network inspect "$NETWORK_NAME" -f '{{ index .Options "com.docker.network.bridge.name" }}')"
SUBNET="$(docker network inspect "$NETWORK_NAME" -f '{{range .IPAM.Config}}{{.Subnet}}{{end}}')"

if [ -z "$BRIDGE_NAME" ] || [ "$BRIDGE_NAME" = "<no value>" ]; then
  BRIDGE_NAME="br-${NETWORK_ID:0:12}"
fi

if [ -z "$SUBNET" ] || [ "$SUBNET" = "<no value>" ]; then
  echo "Cannot detect subnet for Docker network '$NETWORK_NAME'."
  exit 1
fi

echo "Network: $NETWORK_NAME"
echo "Bridge:  $BRIDGE_NAME"
echo "Subnet:  $SUBNET"

sysctl -w net.ipv4.ip_forward=1 >/dev/null

iptables -N DOCKER-USER 2>/dev/null || true

if ! iptables -C DOCKER-USER -o "$BRIDGE_NAME" -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT 2>/dev/null; then
  iptables -I DOCKER-USER 1 -o "$BRIDGE_NAME" -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
fi

if ! iptables -C DOCKER-USER -i "$BRIDGE_NAME" -j ACCEPT 2>/dev/null; then
  iptables -I DOCKER-USER 1 -i "$BRIDGE_NAME" -j ACCEPT
fi

if ! iptables -t nat -C POSTROUTING -s "$SUBNET" ! -o "$BRIDGE_NAME" -j MASQUERADE 2>/dev/null; then
  iptables -t nat -A POSTROUTING -s "$SUBNET" ! -o "$BRIDGE_NAME" -j MASQUERADE
fi

echo "Docker outbound forwarding rules are installed."
