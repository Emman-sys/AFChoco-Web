#!/bin/bash
# Start both Node.js and PHP servers for AFChoco-Web

echo "🍫 Starting AFChoco-Web Servers..."
echo ""

# Check if Node.js server is already running
if lsof -ti:3000 > /dev/null 2>&1; then
    echo "⚠️  Port 3000 is already in use. Killing existing process..."
    lsof -ti:3000 | xargs kill -9
    sleep 1
fi

# Check if PHP server is already running  
if lsof -ti:8000 > /dev/null 2>&1; then
    echo "⚠️  Port 8000 is already in use. Killing existing process..."
    lsof -ti:8000 | xargs kill -9
    sleep 1
fi

echo ""
echo "✅ Starting Node.js API Server on port 3000..."
npm run dev > /dev/null 2>&1 &
NODE_PID=$!
sleep 2

# Check if Node.js started successfully
if curl -s http://localhost:3000/api/health > /dev/null 2>&1; then
    echo "   ✓ Node.js API Server: http://localhost:3000"
else
    echo "   ✗ Failed to start Node.js server"
    exit 1
fi

echo ""
echo "✅ Starting PHP Server on port 8000..."
php -S localhost:8000 > /dev/null 2>&1 &
PHP_PID=$!
sleep 1

# Check if PHP started successfully
if curl -sI http://localhost:8000/MainPage.php 2>&1 | grep -q "200 OK"; then
    echo "   ✓ PHP Server: http://localhost:8000"
else
    echo "   ✗ Failed to start PHP server"
    kill $NODE_PID
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎉 Both servers are running!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🛍️  Main Shop:     http://localhost:8000/MainPage.php"
echo "📡 API Server:    http://localhost:3000/api"
echo "📊 Health Check:  http://localhost:3000/api/health"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Press Ctrl+C to stop both servers..."
echo ""

# Wait for Ctrl+C
trap 'echo ""; echo "🛑 Stopping servers..."; kill $NODE_PID $PHP_PID 2>/dev/null; echo "✅ Servers stopped."; exit 0' INT

# Keep script running
wait
