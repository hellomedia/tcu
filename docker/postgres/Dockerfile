FROM postgres:17

RUN bash -c 'echo -e "C.UTF-8 UTF-8\nen_US.UTF-8 UTF-8\nfr_FR.UTF-8 UTF-8" > /etc/locale.gen' && \
    locale-gen


# Install PostGIS for geolocation features
RUN apt-get update && \
    apt-get install -y postgis postgresql-17-postgis-3 && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*


# # POSTGRES CONTAINER UPGRADE

# # backup data
# docker exec -t <container_name> pg_dumpall -c -U hellokot > backup.sql

# # stop containers
# docker compose stop

# # remove container
# docker rm tcu-database-1

# # upgrade container to new version IN THIS FILE
# FROM postgres:xx

# # build 
# docker compose build database

# # Restart
# docker compose up -d


# -------------------------

# # Sometimes, the data volume is not compatible with the new postgres version
# # In that case, we need to remove the data volume

# # Remove db-data volume
# docker volume ls

# docker volume rm tcu_db-data

# # if volume still attached to a container, need to first remove that container
# docker ps -a
# docker rm <container_id>

# # Once the data volume is removed, build a new one
# docker compose build database

# docker-compose up -d

# # Or restore data from backup.sql
# cat backup.sql | docker exec -i <container_name> psql -U hellokot


# ----------------------

# Trouble shooting corrupted password for user hellokot

# # Access the PostgreSQL container
# docker exec -it <container_name> psql -U hellokot

# # Check if user exists
# \du

# # Change the password for user 'hellokot'
# ALTER USER hellokot WITH PASSWORD '000000';
