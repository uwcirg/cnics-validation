#!/bin/sh
set -e

# Write environment variables used by the app
if [ -n "$FHIR_SERVER" ]; then
  echo "{\"FHIR_SERVER\": \"$FHIR_SERVER\"}" > /var/www/html/env.json
fi

exec "$@"
