# MongoDb Lockless Sessions

[![code-style](https://github.com/colinmollenhour/php-mongodb-lockless-session/actions/workflows/code-style.yml/badge.svg)](https://github.com/colinmollenhour/php-mongodb-lockless-session/actions/workflows/code-style.yml)
[![run-tests](https://github.com/colinmollenhour/php-mongodb-lockless-session/actions/workflows/run-tests.yml/badge.svg)](https://github.com/colinmollenhour/php-mongodb-lockless-session/actions/workflows/run-tests.yml)
![Packagist Version](https://img.shields.io/packagist/v/colinmollenhour/php-mongodb-lockless-session)
![Packagist Downloads](https://img.shields.io/packagist/dt/colinmollenhour/php-mongodb-lockless-session)
![Packagist Dependency Version](https://img.shields.io/packagist/dependency-v/colinmollenhour/php-mongodb-lockless-session/php)
![Packagist Stars](https://img.shields.io/packagist/stars/colinmollenhour/php-mongodb-lockless-session)
![Packagist License](https://img.shields.io/packagist/l/colinmollenhour/php-mongodb-lockless-session)

This library aims to make a compromise between perfect session locking and "good enough" session 
locking by tracking updates to the session so that when it is written to the MongoDb backend,
only the fields that were set or unset are updated and all others are left untouched.


## Features



## Install

```sh
composer require colinmollenhour/php-mongodb-lockless-session
```

## Usage

TODO

## Formatting

```sh
composer lint
```

## Test

```sh
composer test
```

## License

The PHP MongoDb Lockless Session library is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

This library and the author are not affiliated with MongoDB Inc. or its affiliates.
