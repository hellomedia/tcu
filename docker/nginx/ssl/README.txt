**Local self signed SSL certificates**

***Generating certificates***

ACTION REQUIRED: Add your dev rootCA.key and rootCA.crt in projet_root/docker/nginx/ssl/

and run

   ```sh
   $ docker compose build nginx
   ```

This runs project_root/docker/nginx/Dockerfile, which copies related files inside the container
and run generate_certificate.sh from there,
generating a Server Certificate server.crt inside your nginx container, signed by your rootCA.

For convenience, rootCA.key and rootCA.crt can be left in your local copy, they are ignored from version.


***Adding your dev root CA to your browser***

**Chrome**:
   - Open Chrome settings.
   - Navigate to "Privacy and security" > "Security".
   - Click on "Manage certificates".
   - Import the `rootCA.crt` from /hellokot/docker/nginx/ under "Authorities".

**Firefox**:

   - Open Firefox settings.
   - Navigate to "Privacy & Security".
   - Scroll down to "Certificates" and click on "View Certificates".
   - Import the ca.crt from /hellokot/docker/nginx/ into the "Authorities" tab.



***Generating new certificates - Full Steps***

For reference only.

In our setup, only the steps above are necessary, the rest is automated.

1. **Generate a Root CA Certificate**:

   ```sh
   openssl genrsa -out rootCA.key 2048
   openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 3000 -out rootCA.crt -subj "/C=BE/ST=Liege/L=Liege/O=You/OU=IT Department/CN=You Root CA"
   ```

2. **Create the `server.conf` file**:
   ```ini
   [req]
   distinguished_name = req_distinguished_name
   req_extensions = v3_req
   prompt = no

   [req_distinguished_name]
   C = BE
   ST = Liege
   L = Liege
   O = HelloKot
   OU = IT Department
   CN = localhost

   [v3_req]
   keyUsage = critical, digitalSignature, keyEncipherment
   extendedKeyUsage = serverAuth
   subjectAltName = @alt_names

   [alt_names]
   DNS.1 = localhost
   DNS.2 = site1.local
   DNS.3 = site2.local
   DNS.4 = subdomain.site1.local
   DNS.5 = *.site3.local
   ```

3. **Generate the Server Signing Request**:

   ```sh
   openssl genrsa -out server.key 2048
   openssl req -new -key server.key -out server.csr -config server.conf
   ```

4. **Sign the Server CSR with the Root CA**:

   ```sh
   openssl x509 -req -in server.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out server.crt -days 500 -sha256 -extfile server.conf -extensions v3_req
   ```

   Once server.crt is generated, remove server.csr

5. **Update Your Dockerfile**:

   ```Dockerfile
   FROM nginx:1.10

   RUN mkdir -p /etc/nginx/ssl/

   COPY rootCA.crt /etc/nginx/ssl/
   COPY server.crt /etc/nginx/ssl/
   COPY server.key /etc/nginx/ssl/

   COPY nginx.conf /etc/nginx/nginx.conf
   ```

6. **Configure Nginx**:

   ```nginx
   server {
       listen 443 ssl;
       server_name localhost site1.local site2.local site3.local *.site1.local *.site2.local *.site3.local;

       ssl_certificate /etc/nginx/ssl/server.crt;
       ssl_certificate_key /etc/nginx/ssl/server.key;

       # Other configuration...
   }
   ```

7. **Import the Root CA Certificate into your browser**