<?php

namespace App\Branding;

use function in_array;
use Exception;

class LogoConfig
{
    /**
     * [filetypes: mixed[png: nid[], svg: nid[]], styles: [nid:[fg_color:'',bg_color:'',etc.]]]
     */
    protected $logos;

    /**
     * [filename: string[]]
     */
    protected $manifest;

    public static function isLogoPath($path): bool
    {
        return strpos($path, 'images/logos/') === 0;
    }

    public function getPath($path): ?string
    {
        return $this->manifest[$path] ?? null;
    }

    public function isSVGLogo($nid): bool
    {
        return in_array($nid, $this->logos['filetypes']['svg']);
    }

    public function isPNGLogo($nid): bool
    {
        return in_array($nid, $this->logos['filetypes']['png']);
    }

    /**
     * Unserialize an instance from JSON without using serializer->deserialize()
     * @param string $json
     * @throws Exception
     * @return LogoConfig
     */
    public static function fromJSON(string $json): LogoConfig
    {
        $obj = json_decode($json, true);
        if (!$obj) {
            throw new Exception("could not decode json");
        }
        $self = new static();
        $self->logos = $obj['logos'];
        $self->manifest = $obj['manifest'];
        return $self;
    }
}
