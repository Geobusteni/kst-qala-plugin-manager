#!/bin/bash
# Build script for Qala Plugin Manager assets
# Processes CSS and JS files for production deployment

PLUGIN_DIR="/root/gits/kst-qala-plugin-manager/sources/qala-manager/qala-plugin-manager"
ASSETS_DIR="$PLUGIN_DIR/assets"
DIST_DIR="$ASSETS_DIR/dist"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Building Qala Plugin Manager assets...${NC}"

# Create dist directories
mkdir -p "$DIST_DIR/css"
mkdir -p "$DIST_DIR/js"

# Get version from plugin main file
VERSION=$(grep "Version:" "$PLUGIN_DIR/qala-plugin-manager.php" | head -1 | awk '{print $3}')
if [ -z "$VERSION" ]; then
    VERSION="1.0.0"
fi

echo -e "${BLUE}Plugin version: ${VERSION}${NC}"

# Function to minify CSS
minify_css() {
    local input=$1
    local output=$2
    local filename=$(basename "$input")

    echo -e "Processing CSS: ${filename}"

    # Add version header
    echo "/**" > "$output"
    echo " * Qala Plugin Manager - ${filename}" >> "$output"
    echo " * Version: ${VERSION}" >> "$output"
    echo " * Built: $(date '+%Y-%m-%d %H:%M:%S')" >> "$output"
    echo " */" >> "$output"

    # Simple CSS minification: remove comments and excess whitespace
    sed -e 's:/\*[^*]*\*\+\([^/*][^*]*\*\+\)*/::g' \
        -e 's/^[ \t]*//g' \
        -e 's/[ \t]*$//g' \
        -e '/^$/d' \
        -e 's/[ \t]\+/ /g' \
        "$input" >> "$output"

    echo -e "${GREEN}✓ Generated: ${output}${NC}"
}

# Function to minify JS
minify_js() {
    local input=$1
    local output=$2
    local filename=$(basename "$input")

    echo -e "Processing JS: ${filename}"

    # Add version header
    echo "/**" > "$output"
    echo " * Qala Plugin Manager - ${filename}" >> "$output"
    echo " * Version: ${VERSION}" >> "$output"
    echo " * Built: $(date '+%Y-%m-%d %H:%M:%S')" >> "$output"
    echo " */" >> "$output"

    # Simple JS minification: remove comments and excess whitespace
    sed -e 's://.*$::g' \
        -e 's:/\*[^*]*\*\+\([^/*][^*]*\*\+\)*/::g' \
        -e 's/^[ \t]*//g' \
        -e 's/[ \t]*$//g' \
        -e '/^$/d' \
        "$input" >> "$output"

    echo -e "${GREEN}✓ Generated: ${output}${NC}"
}

# Process CSS files
echo -e "\n${BLUE}Processing CSS files...${NC}"
minify_css "$ASSETS_DIR/css/admin-page.css" "$DIST_DIR/css/admin-page.css"
minify_css "$ASSETS_DIR/css/admin-bar-toggle.css" "$DIST_DIR/css/admin-bar-toggle.css"

# Process JS files
echo -e "\n${BLUE}Processing JS files...${NC}"
minify_js "$ASSETS_DIR/js/admin-page.js" "$DIST_DIR/js/admin-page.js"
minify_js "$ASSETS_DIR/js/admin-bar-toggle.js" "$DIST_DIR/js/admin-bar-toggle.js"

# Display summary
echo -e "\n${GREEN}Build complete!${NC}"
echo -e "\n${BLUE}Generated files:${NC}"
ls -lh "$DIST_DIR/css/"
ls -lh "$DIST_DIR/js/"

echo -e "\n${GREEN}Assets ready for production deployment${NC}"
