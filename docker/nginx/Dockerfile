FROM nginx:latest

# add debugging tools
RUN apt-get update && apt-get install -y \
    iputils-ping \
    curl \
    dnsutils \
    && apt-get clean

RUN mkdir -p /etc/nginx/ssl/

# GENERATE SSL certificate
# 1. script can only be run inside the container,
# so copy it inside, along with related files 
COPY ssl/generate_certificate.sh /etc/nginx/ssl/
COPY ssl/rootCA.key /etc/nginx/ssl/
COPY ssl/rootCA.crt /etc/nginx/ssl/
COPY ssl/server.conf /etc/nginx/ssl/
# 2. Run script
RUN chmod +x /etc/nginx/ssl/generate_certificate.sh
RUN /etc/nginx/ssl/generate_certificate.sh
# 3. cleanup
RUN rm /etc/nginx/ssl/rootCA.key
RUN rm /etc/nginx/ssl/rootCA.crt
RUN rm /etc/nginx/ssl/server.csr

COPY nginx.conf /etc/nginx/
