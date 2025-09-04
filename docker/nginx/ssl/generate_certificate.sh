#!/bin/sh

# Called in Dockerfile when (re)building the container 
# Generates server.key + server.crt unique to that container build, based on a developer rootCA.
# With this process, once the dev has added his rootCA.key to his browser trusted Authorities,
# the local server certificate will always be recognized even if the container is rebuilt.
# ACTION REQUIRED:
# Before building nginx container the first time,
# copy your rootCA.key and rootCA.crt in this directory (project_root/docker/nginx/ssl/ )
# For convenience when rebuilding the container, the sensitive files can be left here, they are ignored from version.

# Called from Dockerfile => current working directory = container root
cd /etc/nginx/ssl/

# 1. Generate the Server Key
# This key will be regenerated every time nginx container is rebuilt.
# It is specific to the local container and ignored from version.
openssl genrsa -out server.key 2048

# 2. Generate Certificate Signing Request
# Only needed for the process, removed afterwards
openssl req -new -key server.key -out server.csr -config server.conf

# 3. Sign the Server CSR with your Root CA and generate the Server Certificate:
# Needed: your personal rootCA.key and rootCA.crt
# Add them to this directory locally, they are ignored from version.
openssl x509 -req -in server.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out server.crt -days 3500 -sha256 -extfile server.conf -extensions v3_req

# Restore working directory
cd /