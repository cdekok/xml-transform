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

    /**
     * Filter empty values
     *
     * @var boolean
     */
    private $filter = false;

    /**
     * <code>
     * new \XmlTransform\Mapper([
     *      'id' => [
     *              'xpath' => './/oai:identifier/text()'
     *          ],
     *          'material' => [
     *              'xpath' => './/oai:material/text()',
     *              'repeatable' => true
     *          ],
     *      ],
     *      '//oai:OAI-PMH/oai:ListRecords/oai:record',
     *      ['oai' => 'http://www.openarchives.org/OAI/2.0/'
     * )
     * <code>
     *
     * @param array $mapping mapping of the array with xpath queries
     * @param string $contextXpath xpath query of the elements to search in
     * @param array $namespaces associative array of namespaces
     */
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
    public function fromXml(string $xml):self
    {
        $this->doc = new \DOMDocument();
        $this->doc->loadXML($xml);
        return $this;
    }

    /**
     * From dom document, use this if you already have a DomDocument object around
     *
     * @param \DOMDocument $doc
     * @return $this
     */
    public function fromDomDocument(\DOMDocument $doc):self
    {
        $this->doc = $doc;
        return $this;
    }

    /**
     * Filter empty values from transformed array
     * @param bool $value
     * @return $this;
     */
    public function filter(bool $value = true):self
    {
        $this->filter = $value;
        return $this;
    }

    /**
     * Get filter setting
     * @return bool
     */
    public function getFilter():bool
    {
        return $this->filter;
    }

    /**
     * Return the transformed data
     *
     * @return array Returns the mapped array
     */
    public function transform():array
    {
        if (!$this->doc) {
            throw new Exception\MissingDocument('Set the XML / Dom document first with the from methods');
        }

        return $this->transformData($this->doc, $this->mapping, $this->contextXpath, $this->namespaces);
    }

    /**
     * Fetch the first result, use this if you want to map it to a single array
     *
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

    /**
     *
     * @param \DOMDocument $dom
     * @param array $mapping
     * @param string $contextXpath
     * @param array $namespaces
     * @param \DOMNode $contextNode
     * @return array
     */
    private function transformData(
        \DOMDocument $dom,
        array $mapping,
        string $contextXpath,
        array $namespaces,
        $contextNode = null
    ):array {

        $context = $this->getContext($dom, $contextXpath, $namespaces, $contextNode);

        $data = [];

        foreach ($context as $currentContext) {
            $data[] = $this->map($mapping, $currentContext, $namespaces);
        }

        if ($this->filter) {
            $data = $this->arrayFilterRecursive($data);
        }

        return $data;
    }

    /**
     * Map the array to values from XML
     *
     * @param array $mapping
     * @param \DOMNode $context
     * @param array $namespaces
     * @return type
     */
    private function map(array $mapping, \DOMNode $context, array $namespaces = [])
    {
        $mapping;

        $data = $this->arrayMapRecursive(
            function ($value) use ($context, $namespaces) {

                if (isset($value['context']) && $value['values']) {
                    // Get new context
                    $nested = $this->transformData(
                        $this->doc,
                        $value['values'],
                        $value['context'],
                        $namespaces,
                        $context
                    );

                    if (isset($value['repeatable']) && $value['repeatable'] === true) {
                        return $nested;
                    }
                    return current($nested);
                }

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

    /**
     * Recursively map the values to the array
     *
     * @param \XmlTransform\callable $callback
     * @param array $array
     * @return array
     */
    private function arrayMapRecursive(callable $callback, array $array):array
    {

        $func = function ($item) use (&$func, $callback) {
            if (is_array($item)
                    && !isset($item['xpath'])
                    && (!isset($item['context']) && !isset($item['values']))) {
                return array_map($func, $item);
            } else {
                return call_user_func($callback, $item);
            }
        };

        return array_map($func, $array);
    }

    /**
     *
     * @param array $input
     * @return array
     */
    private function arrayFilterRecursive(array $input):array
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->arrayFilterRecursive($value);
            }
        }

        return array_filter($input, function ($val) {
            if (is_scalar($val)) {
                return !is_null($val);
            }
            if (is_array($val)) {
                return !empty($val);
            }
        });
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
            $return[] = $node->nodeValue;
        }

        return $return;
    }

    /**
     * Get the current context
     *
     * @param \DOMDocument $doc
     * @param string $xpath
     * @param array $namespaces
     * @param \DOMNode $contextNode
     * @return \DOMNodeList
     * @throws Exception\ContextNotFound
     */
    private function getContext(
        \DOMDocument $doc,
        string $xpath,
        array $namespaces = [],
        $contextNode = null
    ):\DOMNodeList {

        $context = $this->getXpath($doc, $namespaces)->query($xpath, $contextNode);
        if (!$context->length) {
            throw new Exception\ContextNotFound('No context found with: ' . $xpath);
        }

        return $context;
    }

    /**
     * Get xpath query with provided namespaces
     *
     * @param \DOMDocument $doc
     * @param array $namespaces
     * @return \DOMXPath
     */
    private function getXpath(\DOMDocument $doc, array $namespaces = []):\DOMXPath
    {
        $xpath = new \DOMXPath($doc);
        foreach ($namespaces as $prefix => $ns) {
            $xpath->registerNamespace($prefix, $ns);
        }
        return $xpath;
    }
}
