#!/usr/bin/env bash
#
# Check Code Standards before merge into master.
set -ex
$(git fetch origin master:refs/remotes/origin/master)
CHANGED_FILES=$(git diff origin/master --name-only --diff-filter=ACMR | grep .php$ || true; )
if [[ "${CHANGED_FILES}" != "" ]]; then
dependencies/vendor/bin/phpcs -s ${CHANGED_FILES}
fi
