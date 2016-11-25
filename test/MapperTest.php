<?php

/*
 * The MIT License
 *
 * Copyright 2016 cdekok.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

declare(strict_types=1);

namespace XmlTransform\Test;

class MapperTest extends \PHPUnit\Framework\TestCase {

    public function testNonExistingNode()
    {
        $xml = __DIR__ . '/files/record_default_namespace.xml';
        $namespaces = ['oai' => 'http://www.openarchives.org/OAI/2.0/'];
        $mapping = [
            'id' => [
                'xpath' => './/oai:fake/text()'
            ],
            'array' => [
                'xpath' => './/oai:fake/text()',
                'repeatable' => true
            ],
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transform();

        $this->assertEquals([
                ['id' => null, 'array' => []],
                ['id' => null, 'array' => []],
            ],
            $result
        );
    }

    public function testContext()
    {
        $xml = __DIR__ . '/files/record_default_namespace.xml';
        $this->expectException(\XmlTransform\Exception\ContextNotFound::class);
        $mapper = new \XmlTransform\Mapper([], '//record');
        $mapper->from($xml)->transform();
    }

    public function testDefaultNamespaceMapping()
    {
        $xml = __DIR__ . '/files/record_default_namespace.xml';
        $namespaces = ['oai' => 'http://www.openarchives.org/OAI/2.0/'];
        $mapping = [
            'id' => [
                'xpath' => './/oai:identifier/text()',
                'repeatable' => false // return array of all values or single literal value (default false)
            ]
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transform();
        $this->assertEquals([
                ['id' => '2'],
                ['id' => '1109'],
            ],
            $result
        );
    }

    public function testRepeatable()
    {
        $xml = __DIR__ . '/files/record_default_namespace_repeatable.xml';
        $namespaces = ['oai' => 'http://www.openarchives.org/OAI/2.0/'];
        $mapping = [
            'id' => [
                'xpath' => './/oai:identifier/text()',
                'repeatable' => true
            ]
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transform();
        $this->assertEquals([
                ['id' => ['2', '3']],
                ['id' => ['1109', '1110']],
            ],
            $result
        );
    }

    public function testNested()
    {
        $xml = __DIR__ . '/files/record_default_namespace.xml';
        $namespaces = ['oai' => 'http://www.openarchives.org/OAI/2.0/'];
        $mapping = [
            'id' => [
                'xpath' => './/oai:identifier/text()',
                'repeatable' => true
            ],
            'location' => [
                'country'   => ['xpath' => './/oai:metadata/oai:location/oai:country/text()'],
                'city'      => ['xpath' => './/oai:metadata/oai:location/oai:city/text()'],
            ]
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transform();

        $this->assertEquals([
                [
                    'id' => ['2'],
                    'location' => [
                        'country' => 'the Netherlands',
                        'city' => 'Amsterdam',
                    ]
                ],
                [
                    'id' => ['1109'],
                    'location' => [
                        'country' => null,
                        'city' => null,
                    ]
                ],
            ],
            $result
        );
    }
    
    public function testMultipleNamespaces()
    {
        $xml = __DIR__ . '/files/multiple_namespaces.xml';
        $namespaces = [
            'oai' => 'http://www.openarchives.org/OAI/2.0/',
            'dcterms' => 'http://purl.org/dc/terms/',
        ];
        $mapping = [
            'title' => ['xpath' => './/dcterms:title/text()']
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transform();
        $this->assertEquals([['title' => 'Geruit vlak in een kader (opzetkarton voor postzegels of plaatjes?)']], $result);
    }
    
    public function testOne()
    {
        $xml = __DIR__ . '/files/multiple_namespaces.xml';
        $namespaces = [
            'oai' => 'http://www.openarchives.org/OAI/2.0/',
            'dcterms' => 'http://purl.org/dc/terms/',
        ];
        $mapping = [
            'title' => ['xpath' => './/dcterms:title/text()']
        ];

        $transformer = new \XmlTransform\Mapper($mapping, '//oai:OAI-PMH/oai:ListRecords/oai:record', $namespaces);
        $result = $transformer->from($xml)->transformOne();
        $this->assertEquals(['title' => 'Geruit vlak in een kader (opzetkarton voor postzegels of plaatjes?)'], $result);
    }
    
}