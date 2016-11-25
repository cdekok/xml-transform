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

namespace XmlTransform;

class Mapper
{

    /**
     * @var array
     */
    private $mapping;

    /**
     * @var string
     */
    private $contextXpath;

    /**
     * @var array
     */
    private $namespaces = [];

    /**
     * @var \DOMDocument
     */
    private $doc;

    public function __construct(array $mapping, string $contextXpath, array $namespaces = [])
    {
        $this->mapping = $mapping;
        $this->contextXpath = $contextXpath;
        $this->namespaces = $namespaces;
    }

    /**
     * From path to xml file
     * @param string $filename
     * @return $this
     */
    public function from(string $filename)
    {
        $this->doc = new \DOMDocument();
        $this->doc->load($filename);
        return $this;
    }

    /**
     * From XML string
     *
     * @param string $xml
     * @return $this
     */
    public function fromXml(string $xml)
    {
        $this->doc = new \DOMDocument();
        $this->doc->loadXML($xml);
        return $this;
    }

    /**
     * Return the transformed data
     */
    public function transform():array
    {
        $context = null;
        if ($this->contextXpath) {
            $context = $this->getContext($this->doc, $this->contextXpath, $this->namespaces);
        }

        $data = [];
        foreach ($context as $currentContext) {
            $data[] = $this->map($this->mapping, $currentContext, $this->namespaces);
        }

        return $data;
    }
    
    /**
     * Fetch the first result, use this if you want to map it to a single array
     * @return array
     */
    public function transformOne():array
    {
        $data = $this->transform();
        if (!empty($data)) {
            return $data[0];
        }
        return [];
    }

    private function map($mapping, \DOMNode $context, array $namespaces = [])
    {
        $mapping;

        $data = $this->arrayMapRecursive(
            function ($value) use ($context, $namespaces) {

                if (!isset($value['xpath'])) {
                    return $value;
                }

                $data = $this->getValue($this->doc, $value['xpath'], $context, $namespaces);

                if (isset($value['repeatable']) && $value['repeatable'] === true) {
                    return $data;
                }

                if (empty($data)) {
                    return null;
                }

                return current($data);
            },
            $mapping
        );

        return $data;
    }

    private function arrayMapRecursive(callable $callback, array $array)
    {

        $func = function ($item) use (&$func, &$callback) {
            return !isset($item['xpath']) ? array_map($func, $item) : call_user_func($callback, $item);
        };

        return array_map($func, $array);
    }

    /**
     *
     * @param \DOMDocument $doc
     * @param string $xpath
     * @param \DOMNode $context
     */
    private function getValue(\DOMDocument $doc, string $xpath, $context = null, array $namespaces = [])
    {
        $result = $this->getXpath($doc, $namespaces)->query($xpath, $context);
        $return = [];

        foreach ($result as $node) {
            $return[] = $node->data;
        }

        return $return;
    }

    private function getContext(\DOMDocument $doc, string $xpath, array $namespaces = []):\DOMNodeList
    {
        $context = $this->getXpath($doc, $namespaces)->query($xpath);
        if (!$context->length) {
            throw new Exception\ContextNotFound('No context found with: ' . $xpath);
        }

        return $context;
    }

    private function getXpath(\DOMDocument $doc, array $namespaces = []):\DOMXPath
    {
        $xpath = new \DOMXPath($doc);
        foreach ($namespaces as $prefix => $ns) {
            $xpath->registerNamespace($prefix, $ns);
        }
        return $xpath;
    }
}
