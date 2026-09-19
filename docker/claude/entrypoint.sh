#!/bin/bash
# Apply the egress allowlist firewall before anything else, then run the
# requested command (normally `claude`). Fails closed: if the firewall can't
# be applied (e.g. NET_ADMIN missing in docker-compose.yaml), the container
# refuses to start rather than running unprotected.
set -euo pipefail
sudo /usr/local/bin/init-firewall.sh
exec "$@"
