<?php

namespace App\Services\Supplier\Transport;

use App\Services\Supplier\Exceptions\InvalidSupplierResponseException;
use SimpleXMLElement;

class SecureSupplierXmlTransport implements SupplierXmlTransport
{
    public function parse(string $xml): SimpleXMLElement
    {
        if (preg_match('/<!ENTITY|<!DOCTYPE/i', $xml)) {
            throw new InvalidSupplierResponseException('Unsafe XML document type or entity declaration was rejected.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $document instanceof SimpleXMLElement) {
            throw new InvalidSupplierResponseException('Malformed XML response.');
        }

        return $document;
    }
}
