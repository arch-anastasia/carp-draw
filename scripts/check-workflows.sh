#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"
# Inherited workflows are inactive pending runner/deployment configuration.
# Validate their retained templates with the pinned actionlint image, read-only.
exec docker run --rm --network none -v "$root:/repo:ro" -w /repo \
    rhysd/actionlint@sha256:b1934ee5f1c509618f2508e6eb47ee0d3520686341fec936f3b79331f9315667 \
    .github/workflows-disabled/*.yml
