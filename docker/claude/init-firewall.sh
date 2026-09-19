#!/bin/bash
# Egress allowlist firewall for the claude-code container.
#
# Default-deny on outbound traffic: only the domains listed below, DNS,
# localhost and the docker compose network (pgsql/php/nginx) are reachable.
# This is the structural defense against data exfiltration if the agent is
# ever manipulated by malicious content (prompt injection) — no network
# path out means secrets can't leave, whatever the agent is tricked into.
# Adapted from Anthropic's reference devcontainer:
# https://github.com/anthropics/claude-code/tree/main/.devcontainer
#
# Runs as root at container start (entrypoint.sh; requires NET_ADMIN,
# granted in docker-compose.yaml). Domain IPs are resolved once at start —
# if an allowed service rotates IPs mid-session and requests start failing,
# refresh with:   sudo /usr/local/bin/init-firewall.sh
#
# To allow a new domain: add it below, then `docker compose build claude-code`
# (the script is baked into the image; the repo copy is only the source).
# Bare domains cover their www. twin automatically (and vice versa) —
# one entry per site is enough.
# Add domains deliberately — every entry is a potential exfiltration path.
set -euo pipefail

ALLOWED_DOMAINS=(
    # Claude Code itself: API, login, telemetry
    api.anthropic.com
    claude.ai
    console.anthropic.com
    statsig.com
    sentry.io
    # package registries (yarn + composer)
    registry.npmjs.org
    registry.yarnpkg.com
    repo.packagist.org
    packagist.org
    # official docs the agent consults while working
    symfony.com
    docs.claude.com
    # tcu
    tcuniversite.be
    # tennis
    mon-classement-tennis.be
    tennis.tppwb.be
    # other
    google.be
)

# --- Gather IPs first (needs network, so before any DROP rule) -------------

# GitHub publishes its address ranges; allow git/api/web wholesale
# (composer dist downloads, gh api). https://api.github.com/meta
GH_RANGES=$(curl -fsS --connect-timeout 10 https://api.github.com/meta || true)

RESOLVED=""
for domain in "${ALLOWED_DOMAINS[@]}"; do
    # each entry covers its www./bare twin automatically — sites answer
    # on both names and their DNS may differ. Only two-label entries get
    # a www. twin (api.anthropic.com stays exactly api.anthropic.com).
    variants="$domain"
    if [[ "$domain" == www.* ]]; then
        variants="$variants ${domain#www.}"
    elif [[ "$domain" != *.*.* ]]; then
        variants="$variants www.$domain"
    fi

    ips=""
    for variant in $variants; do
        ips="$ips $(dig +short A "$variant" | grep -E '^[0-9.]+$' || true)"
    done

    if [ -z "${ips// /}" ]; then
        echo "init-firewall: WARNING — could not resolve $domain (nor its www twin)" >&2
    fi
    RESOLVED="$RESOLVED $ips"
done

# --- Build the allowlist set -----------------------------------------------

# create-if-missing + flush (not destroy/create: destroy fails once the
# set is referenced by live iptables rules, i.e. on every re-run)
ipset create -exist allowed-hosts hash:net
ipset flush allowed-hosts

if [ -n "$GH_RANGES" ]; then
    for cidr in $(echo "$GH_RANGES" | jq -r '(.git + .api + .web)[]' | grep -v ':'); do
        ipset add -exist allowed-hosts "$cidr"
    done
else
    echo "init-firewall: WARNING — could not fetch GitHub IP ranges" >&2
fi

for ip in $RESOLVED; do
    ipset add -exist allowed-hosts "$ip"
done

# --- Apply the rules -------------------------------------------------------

# The compose network (e.g. 172.18.0.0/16), so pgsql/php/nginx stay reachable
IFACE=$(ip route | awk '/default/ {print $5; exit}')
SUBNET=$(ip -o -f inet addr show "$IFACE" | awk '{print $4}')

iptables -F OUTPUT
iptables -F INPUT

iptables -A OUTPUT -o lo -j ACCEPT
# DNS (docker's embedded resolver). NB: this does not block DNS tunneling —
# accepted limitation of this threat model.
iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
iptables -A OUTPUT -p tcp --dport 53 -j ACCEPT
iptables -A OUTPUT -d "$SUBNET" -j ACCEPT
iptables -A OUTPUT -m set --match-set allowed-hosts dst -j ACCEPT
iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -P OUTPUT DROP

iptables -A INPUT -i lo -j ACCEPT
iptables -A INPUT -s "$SUBNET" -j ACCEPT
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
iptables -P INPUT DROP

# --- Verify ----------------------------------------------------------------

if curl -s --connect-timeout 5 https://example.com >/dev/null 2>&1; then
    echo "init-firewall: FAILED — example.com is still reachable, firewall not effective" >&2
    exit 1
fi
if ! curl -s --connect-timeout 10 https://api.anthropic.com >/dev/null 2>&1; then
    echo "init-firewall: FAILED — api.anthropic.com unreachable, allowlist broken" >&2
    exit 1
fi
echo "init-firewall: active — outbound restricted to allowlist + compose network"
