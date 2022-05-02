# Create a new Ske Package
Develop a new Ske package from a template.

## Installation
Install from [Packagist](https://packagist.org/packages/ske/pkg) using [Composer](https://getcomposer.org)
- as template:
```bash
composer create-project ske/pkg
```
- as dependency:
```bash
composer require ske/pkg
```

## Usage
```php
<?php

$autoloader = require_once __DIR__ . '/php_packages/autoload.php';

//Add your code here...

```

## Requirements
PHP 8.0.0 or above (at least 8.0.18 recommended to avoid potential bugs)

## Security Reports
Please send any sensitive issue to [report@foss.sikessem.com](mailto:report@foss.sikessem.com). Thanks!

## License
ske/pkg is licensed under the [Apache 2.0 License](http://www.apache.org/licenses/) - see the [LICENSE file](./LICENSE) for details.
