#!/bin/bash

# Script to download and set up Node.js binaries for the Jackalopes Server plugin
# This script should be run during development to prepare the plugin for distribution

NODE_VERSION="18.19.1"  # LTS version
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN_DIR="$PLUGIN_DIR/bin"

# Create bin directory if it doesn't exist
mkdir -p "$BIN_DIR"

# Detect OS and architecture
OS=$(uname -s)
ARCH=$(uname -m)

# Set download URL based on OS and architecture
if [[ "$OS" == "Linux" ]]; then
    if [[ "$ARCH" == "x86_64" ]]; then
        DOWNLOAD_URL="https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-linux-x64.tar.gz"
    elif [[ "$ARCH" == "aarch64" || "$ARCH" == "arm64" ]]; then
        DOWNLOAD_URL="https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-linux-arm64.tar.gz"
    else
        echo "Unsupported architecture: $ARCH"
        exit 1
    fi
elif [[ "$OS" == "Darwin" ]]; then
    if [[ "$ARCH" == "x86_64" ]]; then
        DOWNLOAD_URL="https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-darwin-x64.tar.gz"
    elif [[ "$ARCH" == "arm64" ]]; then
        DOWNLOAD_URL="https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-darwin-arm64.tar.gz"
    else
        echo "Unsupported architecture: $ARCH"
        exit 1
    fi
else
    echo "Unsupported OS: $OS"
    exit 1
fi

echo "Downloading Node.js v$NODE_VERSION for $OS $ARCH..."
TEMP_DIR=$(mktemp -d)
DOWNLOAD_FILE="$TEMP_DIR/node.tar.gz"

# Download Node.js
curl -L "$DOWNLOAD_URL" -o "$DOWNLOAD_FILE"

# Extract the binary files we need
echo "Extracting Node.js..."
tar -xzf "$DOWNLOAD_FILE" -C "$TEMP_DIR"

# Get the extracted directory name
EXTRACTED_DIR=$(find "$TEMP_DIR" -maxdepth 1 -type d -name "node-*" | head -n 1)

# Copy only the necessary binaries
echo "Copying Node.js binaries to plugin..."
cp "$EXTRACTED_DIR/bin/node" "$BIN_DIR/node"

# Make binaries executable
chmod +x "$BIN_DIR/node"

# Clean up
rm -rf "$TEMP_DIR"

echo "Node.js binary installed in: $BIN_DIR"
echo "Setup complete! Node.js v$NODE_VERSION is now bundled with the plugin."

# Create a simple shell script wrapper for npm
cat > "$BIN_DIR/npm" << 'EOF'
#!/bin/bash
# This is a simple wrapper for npm that uses the bundled Node.js
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$DIR/node" "$(which npm)" "$@"
EOF

chmod +x "$BIN_DIR/npm" 