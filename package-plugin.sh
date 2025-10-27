#!/bin/bash
# Package script for Qala Plugin Manager
# Creates a production-ready zip file for WordPress installation
#
# Usage: ./package-plugin.sh
#
# Prerequisites:
# - Assets must be built first (run: npm run build)

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$SCRIPT_DIR"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}Qala Plugin Manager - Package Script${NC}"
echo -e "${BLUE}=====================================${NC}\n"

# Get version from plugin main file
if [ -f "$PLUGIN_DIR/index.php" ]; then
    VERSION=$(grep "Version:" "$PLUGIN_DIR/index.php" | head -1 | awk '{print $3}')
elif [ -f "$PLUGIN_DIR/qala-plugin-manager.php" ]; then
    VERSION=$(grep "Version:" "$PLUGIN_DIR/qala-plugin-manager.php" | head -1 | awk '{print $3}')
fi

if [ -z "$VERSION" ]; then
    VERSION="dev"
    echo -e "${YELLOW}Warning: Could not detect version, using 'dev'${NC}"
fi

echo -e "${BLUE}Plugin version: ${VERSION}${NC}"

# Check if assets are built
if [ ! -f "$PLUGIN_DIR/assets/dist/js/qala-plugin-manager.js" ]; then
    echo -e "${RED}Error: Assets not built!${NC}"
    echo -e "${YELLOW}Please run 'npm run build' first${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Assets verified${NC}"

# Regenerate composer autoloader without dev dependencies
echo -e "\n${BLUE}Regenerating composer autoloader (production only)...${NC}"
cd "$PLUGIN_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}Error: Failed to regenerate composer autoloader${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Autoloader regenerated${NC}"

# Define output filename
OUTPUT_FILE="qala-plugin-manager-v${VERSION}.zip"
OUTPUT_PATH="$PARENT_DIR/$OUTPUT_FILE"

# Remove existing zip if it exists
if [ -f "$OUTPUT_PATH" ]; then
    echo -e "${YELLOW}Removing existing zip file...${NC}"
    rm "$OUTPUT_PATH"
fi

# Create zip file
# Note: composer autoloader already regenerated with --no-dev, so vendor only contains production dependencies
echo -e "\n${BLUE}Creating zip package...${NC}"
cd "$PARENT_DIR"

zip -r "$OUTPUT_FILE" qala-plugin-manager \
  -x "*/node_modules/*" \
  -x "*/tests/*" \
  -x "*/.git/*" \
  -x "*/.github/*" \
  -x "*/.gitlab/*" \
  -x "*/phpunit.xml" \
  -x "*/phpunit.xml.dist" \
  -x "*/phpstan.neon" \
  -x "*/phpstan.neon.dist" \
  -x "*/phpcs.xml" \
  -x "*/composer.json" \
  -x "*/composer.lock" \
  -x "*/package.json" \
  -x "*/package-lock.json" \
  -x "*/.DS_Store" \
  -x "*/assets/src/*" \
  -x "*/assets/js/*" \
  -x "*/assets/css/*" \
  -x "*/webpack.config.js" \
  -x "*/postcss.config.js" \
  -x "*/.gitignore" \
  -x "*/build-assets.sh" \
  -x "*/dependencies/grumphp/*" \
  -x "*/dependencies/scripts/*" \
  > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Package created successfully${NC}\n"

    # Display file info
    echo -e "${BLUE}Package details:${NC}"
    ls -lh "$OUTPUT_PATH"

    echo -e "\n${GREEN}Ready for WordPress installation!${NC}"
    echo -e "${BLUE}Location: ${OUTPUT_PATH}${NC}"
else
    echo -e "${RED}Error creating package${NC}"

    # Restore dev dependencies before exiting
    cd "$PLUGIN_DIR"
    echo -e "\n${YELLOW}Restoring dev dependencies...${NC}"
    COMPOSER_ALLOW_SUPERUSER=1 composer install > /dev/null 2>&1

    exit 1
fi

# Restore dev dependencies for development
echo -e "\n${BLUE}Restoring dev dependencies for development...${NC}"
cd "$PLUGIN_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dev dependencies restored${NC}"
else
    echo -e "${YELLOW}Warning: Failed to restore dev dependencies${NC}"
    echo -e "${YELLOW}Run 'composer install' manually to restore dev tools${NC}"
fi
