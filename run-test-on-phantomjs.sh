#!/bin/bash

# Run dphantomjs with webdriver
echo 'Starting phantomjs'
phantomjs --webdriver=8643 >/dev/null 2>&1 &
phantom_pid=$!

# Run the tests
echo 'Running the tests'
bin/behat --format=pretty --format=html
mv testrun.html testreports/testreport-$(date -d "today" +"%Y-%m-%d_%H-%M").html

# Kill phantomjs process.
echo 'Stopping phantomjs'
kill $phantom_pid
