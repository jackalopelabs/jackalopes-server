#!/bin/bash

# Jackalopes Server - Node.js Setup Script
# This script automatically downloads and configures the appropriate Node.js binary
# for the current platform.

NODE_VERSION="18.18.0"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo "🔍 Detecting platform..."
OS="$(uname -s)"
ARCH="$(uname -m)"

case "$OS" in
  Linux)
    echo "🐧 Linux detected ($ARCH)"
    NODE_DIR="linux-bin"
    if [ "$ARCH" == "x86_64" ]; then
      NODE_URL="https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-x64.tar.gz"
    elif [ "$ARCH" == "aarch64" ] || [ "$ARCH" == "arm64" ]; then
      NODE_URL="https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-arm64.tar.gz"
    else
      echo "❌ Unsupported architecture: $ARCH"
      exit 1
    fi
    ;;
    
  Darwin)
    echo "🍎 macOS detected ($ARCH)"
    NODE_DIR="bin"
    if [ "$ARCH" == "x86_64" ]; then
      NODE_URL="https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-darwin-x64.tar.gz"
    elif [ "$ARCH" == "arm64" ]; then
      NODE_URL="https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-darwin-arm64.tar.gz" 
    else
      echo "❌ Unsupported architecture: $ARCH"
      exit 1
    fi
    ;;
    
  *)
    echo "❌ Unsupported operating system: $OS"
    exit 1
    ;;
esac

echo "📥 Downloading Node.js v${NODE_VERSION} for $OS ($ARCH)..."
cd "$PLUGIN_DIR"
mkdir -p "$NODE_DIR"

# Download and extract Node.js
curl -sL "$NODE_URL" -o "node-temp.tar.gz"
if [ $? -ne 0 ]; then
  echo "❌ Failed to download Node.js binary"
  exit 1
fi

tar -xzf "node-temp.tar.gz" -C "$NODE_DIR" --strip-components=1
if [ $? -ne 0 ]; then
  echo "❌ Failed to extract Node.js binary"
  rm -f "node-temp.tar.gz"
  exit 1
fi
rm -f "node-temp.tar.gz"

# Set executable permissions
chmod +x "$NODE_DIR/bin/node"
if [ $? -ne 0 ]; then
  echo "❌ Failed to set executable permissions"
  exit 1
fi

echo "✅ Successfully installed Node.js v${NODE_VERSION} to $NODE_DIR"
echo "🚀 You can now run the server using ./restart-server.sh"

# Create symbolic links in bin directory for compatibility
if [ "$NODE_DIR" != "bin" ]; then
  echo "🔗 Creating symbolic links in bin directory for compatibility..."
  mkdir -p bin
  if [ -e "bin/node" ]; then
    rm "bin/node"
  fi
  ln -sf "../$NODE_DIR/bin/node" "bin/node"
  echo "✅ Links created."
fi

exit 0 