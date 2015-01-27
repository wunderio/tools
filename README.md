# Behat 3.x runner with DrupalExtension 3

## setup

Run the following:

```
php composer.phar install
```

## to run tests:

```
./run-test-on-phantomjs.sh
```

You can also manually start the phantomjs webdriver and run single tests with

```
phantomjs --webdriver=8643
bin/behat features/featurename.feature
```

