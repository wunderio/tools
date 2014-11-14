# behat 2.x runner

## setup

Run the following:

```
php composer.phar install
```

## to run tests:

```
./start-phantomjs-selenium.sh
```

You can also manually start the phantomjs webdriver and run single tests with

```
bin/behat behat_features/featurename.feature
```

## Different setup (composer.json)

### behat 2 + drupal extension

```
{
    "require": {
        "behat/behat": "2.5.*@stable",
        "drupal/drupal-extension": "1.0.*@stable"
    },
    "minimum-stability": "dev",
    "config": {
        "bin-dir": "bin/"
    }
}
```

### behat 3 (official) + drupal extension
without junit output

```
{
    "require": {
        "behat/behat": "~3.0.6",
        "drupal/drupal-extension": "master"
    },
    "minimum-stability": "dev",
    "config": {
        "bin-dir": "bin/"
    }
}
```
### behat 3 (pull request not merged) + drupal extension
behat3 no official, but with junit output working until it will be merge to behat3

```
{
     "repositories": [
      {
          "type": "vcs",
          "url": "https://github.com/james75/Behat"
      }],
    "require": {
        "behat/behat": "dev-formatter_junit_1 as 3.0.6",
        "drupal/drupal-extension": "master"
    },
    "minimum-stability": "dev",
    "config": {
        "bin-dir": "bin/"
    }
}
```
