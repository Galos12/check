#!/bin/bash
set -e

# Ensure /data exists and is writable
mkdir -p /data
chown www-data:www-data /data || true

# Run migration to create SQLite schema if DB missing
if [ ! -f /data/data.sqlite ]; then
  echo "Creating SQLite database at /data/data.sqlite"
  php migrate.php || true
fi

exec "$@"
