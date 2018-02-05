# zf-apigility-doctrine-bulk

Installation
------------

Installation of this module uses composer. For composer documentation, please refer to
[getcomposer.org](http://getcomposer.org/).

```console
$ composer require rradutzu/zf-apigility-doctrine-bulk
```

Usage
------------

Modify your resource from

```php

namespace Api\V1\Rest\ApiExample;

use ZF\Apigility\Doctrine\Server\Resource\DoctrineResource;

class ApiExampleResource extends DoctrineResource

```

to 

```php

namespace Api\V1\Rest\ApiExample;

use ZF\Apigility\Doctrine\Bulk\Server\Resource\DoctrineBulkResource;

class ApiExampleResource extends DoctrineBulkResource

```

The POST operation will now accept a collection of entities as well as a single entity at a time.