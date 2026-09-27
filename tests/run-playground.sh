#!/bin/sh
set -eu

plugin_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
theme_dir=${1:-"$(dirname -- "$plugin_dir")/23 Web Website"}

if [ ! -f "$theme_dir/style.css" ]; then
	echo "23 Theme was not found at: $theme_dir" >&2
	echo "Usage: tests/run-playground.sh /absolute/path/to/23-theme" >&2
	exit 1
fi

for blueprint in blueprint-6.6.json blueprint-current.json; do
	result_dir=$(mktemp -d "${TMPDIR:-/tmp}/twentythree-core-qa.XXXXXX")
	echo "Running $blueprint"
	npx --yes @wp-playground/cli@latest run-blueprint \
		--mount "$plugin_dir:/wordpress/wp-content/plugins/twentythree-core" \
		--mount "$theme_dir:/wordpress/wp-content/themes/twentythree-theme" \
		--mount "$result_dir:/wordpress/qa-results" \
		--define TWENTYTHREE_QA_OUTPUT /wordpress/qa-results/report.json \
		--define-bool WP_DEBUG true \
		--blueprint "$plugin_dir/tests/$blueprint" \
		--verbosity normal
	cat "$result_dir/report.json"
	echo "Report retained at: $result_dir/report.json"
done
