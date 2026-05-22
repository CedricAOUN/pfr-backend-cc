#!/bin/bash
set -e

FRESH=false
SEED=false

for arg in "$@"; do
  case $arg in
    --fresh) FRESH=true ;;
    --seed)  SEED=true ;;
  esac
done

COMMAND="php artisan"

if [ "$FRESH" = true ]; then
  COMMAND="$COMMAND migrate:fresh"
else
  COMMAND="$COMMAND migrate"
fi

if [ "$SEED" = true ]; then
  COMMAND="$COMMAND --seed"
fi

COMMAND="$COMMAND --force"

docker exec -it backend $COMMAND
