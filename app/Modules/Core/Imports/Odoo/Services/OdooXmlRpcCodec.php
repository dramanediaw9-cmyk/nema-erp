<?php

namespace App\Modules\Core\Imports\Odoo\Services;

use DOMDocument;
use DOMElement;
use RuntimeException;
use SimpleXMLElement;

class OdooXmlRpcCodec
{
    public function encode(string $method, array $params): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $call = $document->appendChild($document->createElement('methodCall'));
        $call->appendChild($document->createElement('methodName'))->appendChild($document->createTextNode($method));
        $paramsNode = $call->appendChild($document->createElement('params'));

        foreach ($params as $param) {
            $paramNode = $paramsNode->appendChild($document->createElement('param'));
            $this->appendValue($document, $paramNode, $param);
        }

        return (string) $document->saveXML();
    }

    public function decode(string $xml): mixed
    {
        $previous = libxml_use_internal_errors(true);
        $response = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $response) {
            throw new RuntimeException('La reponse XML-RPC Odoo est invalide.');
        }

        if (isset($response->fault->value)) {
            $fault = $this->decodeValue($response->fault->value);
            $message = is_array($fault) ? ($fault['faultString'] ?? json_encode($fault)) : (string) $fault;
            throw new RuntimeException('Odoo XML-RPC: '.$message);
        }

        if (! isset($response->params->param->value)) {
            throw new RuntimeException('La reponse XML-RPC Odoo ne contient aucune valeur.');
        }

        return $this->decodeValue($response->params->param->value);
    }

    private function appendValue(DOMDocument $document, DOMElement $parent, mixed $value): void
    {
        $valueNode = $parent->appendChild($document->createElement('value'));

        if ($value === null) {
            $valueNode->appendChild($document->createElement('nil'));

            return;
        }

        if (is_bool($value)) {
            $valueNode->appendChild($document->createElement('boolean', $value ? '1' : '0'));

            return;
        }

        if (is_int($value)) {
            $valueNode->appendChild($document->createElement('int', (string) $value));

            return;
        }

        if (is_float($value)) {
            $valueNode->appendChild($document->createElement('double', (string) $value));

            return;
        }

        if (is_array($value) && ! array_is_list($value)) {
            $struct = $valueNode->appendChild($document->createElement('struct'));
            foreach ($value as $key => $item) {
                $member = $struct->appendChild($document->createElement('member'));
                $member->appendChild($document->createElement('name'))->appendChild($document->createTextNode((string) $key));
                $this->appendValue($document, $member, $item);
            }

            return;
        }

        if (is_array($value)) {
            $data = $valueNode->appendChild($document->createElement('array'))->appendChild($document->createElement('data'));
            foreach ($value as $item) {
                $this->appendValue($document, $data, $item);
            }

            return;
        }

        $string = $valueNode->appendChild($document->createElement('string'));
        $string->appendChild($document->createTextNode((string) $value));
    }

    private function decodeValue(SimpleXMLElement $value): mixed
    {
        if (isset($value->boolean)) {
            return (string) $value->boolean === '1';
        }
        if (isset($value->int)) {
            return (int) $value->int;
        }
        if (isset($value->i4)) {
            return (int) $value->i4;
        }
        if (isset($value->double)) {
            return (float) $value->double;
        }
        if (isset($value->nil)) {
            return null;
        }
        if (isset($value->base64)) {
            return (string) $value->base64;
        }
        if (isset($value->array->data)) {
            $items = [];
            foreach ($value->array->data->value as $item) {
                $items[] = $this->decodeValue($item);
            }

            return $items;
        }
        if (isset($value->struct)) {
            $items = [];
            foreach ($value->struct->member as $member) {
                $items[(string) $member->name] = $this->decodeValue($member->value);
            }

            return $items;
        }
        if (isset($value->dateTime) || isset($value->{'dateTime.iso8601'})) {
            return (string) ($value->{'dateTime.iso8601'} ?? $value->dateTime);
        }

        return isset($value->string) ? (string) $value->string : (string) $value;
    }
}
