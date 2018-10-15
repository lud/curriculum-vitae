<?php

namespace CV;

class DocumentBodyFilter {
    private $excluded = [];

    public static function create()
    {
        return new static();
    }

    public function exclude($key, $values = true)
    {
        $values = (array) $values;
        $this->excluded[$key] = array_merge($this->excluded[$key] ?? [], $values);
        return $this;
    }

    public function accept(MetaStorageInterface $section) : bool
    {
        $accept = true;
        foreach ($this->excluded as $key => $excludedValues) {
            $metaValues = (array) $section->meta($key);
            $DEBUG_EXCLUDED = implode(', ', $excludedValues);
            foreach ($metaValues as $metaValue) {
                if (in_array($metaValue, $excludedValues)) {
                    $accept = false;
                    // break all exclusions since we are excluded
                    break(2);
                }
            }
        }
        return $accept;
    }
}
