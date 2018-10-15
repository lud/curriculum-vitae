<?php

namespace CV;

trait MetaStorageTrait {

    private $_metaStorage;

    public function meta($key = null, $value = null)
    {
        // we keep $this->_metaStorage null as long as we can for faster serialization
        if ($key === null) {
            return (array) $this->_metaStorage;
        }
        $meta = (array) $this->_metaStorage;
        if ($value === null) {
            return $meta[$key] ?? null;
        }
        $meta[$key] = $value;
        $this->_metaStorage = $meta;
        return $this;
    }

}
