# PHP XML Transformer #

This library is useful to map xml values to array values, with xpath queries.

## Usage ##

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
For convience it's also possible to only map to 1 array instead of a list of results.

```
$data = (new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces))
    ->from('somefile.xml')
    ->transformOne();

// $data will contain something like
['id' => '12', 'material' => ['paint', 'pencil']]

```

## Development ##

After running `composer install` grumphp will watch codestyles and unit tests before commits.

To manually check the code style / unit tests run `composer run test`

To format the code automatically run `composer run format`

To generate test coverage run `composer run report`

This project follows [git flow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) for commits
