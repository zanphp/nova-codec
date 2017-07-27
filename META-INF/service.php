<?php

return [
    \ZanPHP\NovaCodec\NovaCodec::class => [
        "interface" => \ZanPHP\Contracts\ServiceChain\ServiceChainer::class,
        "id" => "codec:nova",
        "shared" => true,
    ],
];