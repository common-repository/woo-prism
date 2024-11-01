#!/bin/sh -e

# NOTE:
# this script depends on:
# - php + composer
# - tar
# - unzip
# - rsync
# preparing deps (prism-php-sdk) is out of this script's concern

PLUGIN_NAME=woocommerce-prismappio
VERSION=`./wp-version.sh`
ARCHIVE_NAME="${PLUGIN_NAME}.${VERSION}"

# DEBUG info
echo "PLUGIN_NAME: ${PLUGIN_NAME}"
echo "VERSION: ${VERSION}"

# BUILD artifacts
rm -rf build
mkdir -p build/$PLUGIN_NAME
rsync -r * --exclude-from=.distignore --exclude=build/ build/$PLUGIN_NAME

(
  cd build \
  && tar -czf "${ARCHIVE_NAME}.tar.gz" $PLUGIN_NAME \
  && zip -r "${ARCHIVE_NAME}.zip" $PLUGIN_NAME \
)
