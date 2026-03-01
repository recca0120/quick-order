#!/usr/bin/env bash

# Quick Order - WordPress Test Environment Setup
#
# Usage:
#   ./bin/install-wp-tests.sh [db_engine]
#
# Arguments:
#   db_engine: 'sqlite' (default) or 'mysql'

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_ENGINE="${1:-sqlite}"

WP_CORE_DIR="${PLUGIN_DIR}/.wordpress-test/wordpress"
WP_PLUGINS_DIR="${WP_CORE_DIR}/wp-content/plugins"

echo "=== Quick Order Test Environment Setup ==="
echo "Plugin directory: ${PLUGIN_DIR}"
echo "WordPress directory: ${WP_CORE_DIR}"
echo "Database engine: ${DB_ENGINE}"
echo ""

# Check if already installed
if [ -f "${WP_CORE_DIR}/wp-includes/version.php" ]; then
    echo "WordPress already installed. To reinstall, run:"
    echo "  rm -rf ${PLUGIN_DIR}/.wordpress-test"
    echo ""
    echo "To run tests:"
    echo "  composer test"
    exit 0
fi

# Create directories
mkdir -p "${WP_CORE_DIR}"
mkdir -p "${WP_PLUGINS_DIR}"

# Download WordPress
echo "Downloading WordPress..."
curl -sL https://wordpress.org/latest.tar.gz | tar xz --strip-components=1 -C "${WP_CORE_DIR}"

# Download SQLite integration plugin (for SQLite mode)
if [ "${DB_ENGINE}" = "sqlite" ]; then
    echo "Downloading SQLite Database Integration plugin..."
    cd "${WP_PLUGINS_DIR}"
    curl -sL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o sqlite.zip
    unzip -q sqlite.zip
    rm sqlite.zip
fi

# Download WooCommerce
echo "Downloading WooCommerce..."
cd "${WP_PLUGINS_DIR}"
curl -sL https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip -o woocommerce.zip
unzip -q woocommerce.zip
rm woocommerce.zip

# Create symlink to our plugin
echo "Creating symlink to plugin..."
if [ -L "${WP_PLUGINS_DIR}/quick-order" ]; then
    rm "${WP_PLUGINS_DIR}/quick-order"
fi
ln -s "${PLUGIN_DIR}" "${WP_PLUGINS_DIR}/quick-order"

# Install composer dependencies if not already installed
if [ ! -d "${PLUGIN_DIR}/vendor" ]; then
    echo "Installing composer dependencies..."
    cd "${PLUGIN_DIR}"
    composer install
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "To run tests:"
echo "  composer test"
echo ""
echo "Or with coverage:"
echo "  ./vendor/bin/phpunit --coverage-text"
echo ""
if [ "${DB_ENGINE}" = "mysql" ]; then
    echo "Note: Make sure MySQL is running and accessible."
    echo "Configure DB_PASSWORD environment variable if needed."
fi
