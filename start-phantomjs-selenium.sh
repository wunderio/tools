#!/bin/bash

# Run dphantomjs with webdriver
echo 'Starting phantomjs'
phantomjs --webdriver=8643 >/dev/null 2>&1 &
phantom_pid=$!

# Run the tests
echo 'Running the tests'
bin/behat

# Kill phantomjs process.
echo 'Stopping phantomjs'
kill $phantom_pid
