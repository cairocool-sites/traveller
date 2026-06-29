<?php

namespace App\Services\Supplier\Transport;

use SimpleXMLElement;

interface SupplierXmlTransport
{
    public function parse(string $xml): SimpleXMLElement;
}
