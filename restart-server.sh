#!/bin/bash

# Jackalopes WebSocket Server restart script
# Usage: bash restart-server.sh

echo "🔄 Restarting Jackalopes WebSocket Server..."

# Stop any running server process
if [ -f server.pid ]; then
  PID=$(cat server.pid)
  if ps -p $PID > /dev/null; then
    echo "📥 Stopping server process with PID: $PID"
    kill $PID
    sleep 2
    
    # Check if process is still running and force kill if necessary
    if ps -p $PID > /dev/null; then
      echo "⚠️ Process still running, using force kill..."
      kill -9 $PID
      sleep 1
    fi
  else
    echo "🔍 Process with PID: $PID not found (server may have crashed)"
  fi
  rm -f server.pid
else
  echo "🔍 No PID file found, checking for Node.js processes..."
  # Try to find any node server.js processes - check all possible binary locations
  NODE_PID=$(ps aux | grep -E "(node|./bin/node|./linux-bin/bin/node).*server.js" | grep -v grep | awk '{print $2}')
  if [ ! -z "$NODE_PID" ]; then
    echo "📥 Found Node.js process: $NODE_PID, stopping it..."
    kill $NODE_PID
    sleep 2
    # Force kill if still running
    if ps -p $NODE_PID > /dev/null; then
      kill -9 $NODE_PID
    fi
  fi
fi

# Clear old log file
echo "🧹 Clearing old server log..."
echo "" > server.log

# Determine which Node.js binary to use based on platform
NODE_BIN="./bin/node"  # Default to macOS binary

# Check for Linux-compatible binary if on Linux
if [ "$(uname)" == "Linux" ]; then
  if [ -x "./linux-bin/bin/node" ]; then
    NODE_BIN="./linux-bin/bin/node"
    echo "🐧 Linux detected, using Linux-compatible Node.js binary"
  else
    echo "⚠️ Linux detected but Linux-compatible Node.js binary not found"
    echo "⚠️ To install Linux binary, run:"
    echo "curl -sL https://nodejs.org/dist/v18.18.0/node-v18.18.0-linux-x64.tar.gz -o node-linux.tar.gz && mkdir -p linux-bin && tar -xzf node-linux.tar.gz -C linux-bin --strip-components=1 && rm node-linux.tar.gz && chmod +x linux-bin/bin/node"
  fi
fi

# Check for system-installed Node.js if no binary is available or executable
if [ ! -x "$NODE_BIN" ]; then
  if command -v node &> /dev/null; then
    NODE_BIN="node"
    echo "🌐 Using system-installed Node.js"
  else
    echo "❌ No executable Node.js binary found. Server cannot start."
    exit 1
  fi
fi

# Start the server in the background
echo "🚀 Starting WebSocket server using $NODE_BIN..."
$NODE_BIN server.js > server.log 2>&1 &

# Save the new PID
NEW_PID=$!
echo $NEW_PID > server.pid
echo "✅ Server started with PID: $NEW_PID"
echo "📋 View logs with: tail -f server.log" 