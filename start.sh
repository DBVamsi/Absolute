#!/bin/bash

# CLI flags
verbose_migrations=false
force_build=false
no_cache=false

# Function to display an error message and exit
error_exit() {
  echo "[ERROR] > $1"
  exit 1
}

# Generate required log files if they don't already exist.
generate_log_files() {
    commit_file="logs/last-commit"
    pdo_errors="logs/pdo_errors.log"
    mysql_errors="logs/mysql_errors.log"

    mkdir -p logs

    [ ! -f "$commit_file" ] && touch "$commit_file"
    [ ! -f "$pdo_errors" ] && touch "$pdo_errors"
    [ ! -f "$mysql_errors" ] && touch "$mysql_errors"
}

# Function to generate development certificates using Certbot
generate_dev_certs() {
  if [ ! -f "certbot/conf/live/yourdomain.com/fullchain.pem" ]; then # Updated domain
    echo "[INFO] Dev certs do not exist. Running certbot script."
    bash certbot/generate.sh

    if [ $? -ne 0 ]; then
      error_exit "Failed to generate development certificates."
    fi
  fi
}

# Function to build Docker containers
build_docker_containers() {
  commit_file="logs/last-commit"
  current_commit=$(git rev-parse --short HEAD)

  if [ "$force_build" = true ] || [ ! -f "$file" ] || [ "$current_commit" != "$(cat $commit_file)" ]; then
    if [ "$no_cache" = true ]; then
        echo "[INFO] Building Docker containers with no cache."

        docker-compose build --no-cache
    else
         echo "[INFO] Building Docker containers from cache."

        docker-compose build
    fi

    echo "$current_commit" > "$commit_file"
  else
    echo "[NOTICE] Already built, not running build script."
  fi
}

# Function to start Docker containers
start_docker_containers() {
  echo "[INFO] Starting Docker containers in the background."

  if ! type "docker-compose" > /dev/null; then
    podman-compose up -d
  else
    docker-compose up -d
  fi

  if [ $? -ne 0 ]; then
    error_exit "Docker compose build failed."
  fi

  echo "[INFO] Docker containers successfully started."
}

# Function to execute SQL migrations inside the MySQL Docker container
execute_sql_migrations() {
  echo "[INFO] Executing SQL migrations."
  migrations=$(docker exec -it ecrpg-mysql bash -c "cd /data/application && ./migrate.sh") # Updated service name

  if [[ "$migrations" != *"[SUCCESS] All migrations executed successfully."* ]]; then
    if [ "$verbose_migrations" = true ]; then
      echo "[LOGS // MIGRATION] $migrations"
    fi

    error_exit "Migrations failed."
  else
    echo " > Migrations ran successfully."

    if [ "$verbose_migrations" = true ]; then
      echo "[LOGS // MIGRATION] $migrations"
    fi
  fi
}

# Parse command-line flags
while getopts "bcv" flag; do
  case $flag in
    v) verbose_migrations=true ;;
    c) no_cache=true ;;
    b) force_build=true ;;
    *) exit 1 ;;
  esac
done

# Generate log files
generate_log_files

# Check for development server certificates
generate_dev_certs

# Build Docker containers
build_docker_containers "$1"

# Start Docker containers
start_docker_containers

# Execute SQL migrations
execute_sql_migrations

echo "[SUCCESS] Server successfully started."
