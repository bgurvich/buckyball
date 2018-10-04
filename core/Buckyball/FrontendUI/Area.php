<?php

namespace Buckyball\FrontendUI;

use \Buckyball\Core\Proto\App\Area as ProtoArea;
use \Buckyball\Core\Iface\App\Area as IfaceArea;

class Area extends ProtoArea implements IfaceArea
{
    public function processRequest()
    {
        echo 1;
    }
}