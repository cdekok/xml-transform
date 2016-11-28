[![Build Status](https://travis-ci.org/cdekok/xml-transform.svg?branch=develop)](https://travis-ci.org/cdekok/xml-transform)
[![Coverage Status](https://coveralls.io/repos/github/cdekok/xml-transform/badge.svg?branch=master)](https://coveralls.io/github/cdekok/xml-transform?branch=master)

# PHP XML Transformer

This library is useful to map xml values to array values, with xpath queries.

## Installation

`composer require cdekok/xml-transform`

## Usage

### List of data

```php

// Optional add namespaces in the XML
$namespaces = ['oai' => 'http://www.openarchives.org/OAI/2.0/'];

// Define the mapping for the array that you want to have filled
$mapping = [
    'id' => [
        'xpath' => './/oai:identifier/text()'
    ],
    'material' => [
        'xpath' => './/oai:material/text()',
        'repeatable' => true // If elements are repeatable set this option so an array will be returned
    ],
];

$data = (new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces))
    ->from('somefile.xml')
    ->transform();

// $data will contain something like
[
    ['id' => '12', 'material' => ['paint', 'pencil']],
    ['id' => '13', 'material' => ['pen', 'pencil']],
]
```

### Single array

For convience it's also possible to only map to 1 array instead of a list of results.

```php
$data = (new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces))
    ->from('somefile.xml')
    ->transformOne();

// $data will contain something like
['id' => '12', 'material' => ['paint', 'pencil']]

```

### Repeatable nested elements

```php
$mapping = [
    'id' => ['xpath' => './/oai:objectid/text()'],
    'creator' => [
        'repeatable' => true, // Mark the element as repeatable
        'context' => './/oai:constituent', // new context for the nested elements
        'values' => [
            'name' => ['xpath' => './/text()'],
            'death_date' => ['xpath' => './/@death_date'],
        ]
    ]
];

$transformer = new \XmlTransform\Mapper($mapping, '//oai:record', $namespaces);
$result = $transformer->from($xml)->transformOne();

// Result will contain something like this
[
    'id' => '3517',
    'creator' => [
        ['name' => 'Rembrandt', 'death_date' => '1669'],
        ['name' => 'Johannes Mock', 'death_date' => '1884'],
        ['name' => 'Georg Friedrich Schmidt', 'death_date' => '1775'],
    ]
]
```

### Filter values

Filter empty values from the returned array

```php
$transformer->from($xml)->filter()->transform();
```

## Development ##

After running `composer install` grumphp will watch codestyles and unit tests before commits.

To manually check the code style / unit tests run `composer run test`

To format the code automatically run `composer run format`

To generate test coverage run `composer run report`

This project follows [git flow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) for commits
