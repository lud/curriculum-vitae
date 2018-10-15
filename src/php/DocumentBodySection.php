<?php

namespace CV;

class DocumentBodySection implements MetaStorageInterface {

    private $md;
    use MetaStorageTrait;

    public function __construct(string $md, $docstring = '')
    {
        $this->md = $md;

        $docstring = trim($docstring);
        if ('' !== $docstring) {
            $this->parseDocString($docstring);
        }
    }

    private static function explodeTrim(string $sep, string $str)
    {
        $str = explode($sep, $str);
        $str = array_map('trim', $str);
        return $str;
    }

    public function parseDocString(string $docstring)
    {
        $defs = self::explodeTrim(';', $docstring);
        // each def is "@key value1 [value2 [... valueN"
        $meta = [];
        $pattern = '/
            ^@                  # docstring header
            (?:                 # full definition optional, a single "@" is ok
                ([a-zA-Z_]      # tag key starting with a letter or _
                 [a-zA-Z0-9_]*) # rest of the tag key
                (?:             # value is optional
                    \s          # whitespace before the values
                    (.*)        # match any value string
                )?
            )?
        /x';
        foreach ($defs as $def) {
            $matches = [];
            preg_match($pattern, $def, $matches);
            if (count($matches) === 3) {
                list(, $key, $values) = $matches;
                $this->meta($key, self::explodeTrim(' ', $values));
            } elseif (count($matches) && $matches[0] === '@') {
                // This is a dummy def to close the previous section
            } elseif ('' === $def) {
                // Empty def, maybe a trailing ';'
            } elseif (count($matches) === 2) {
                // No values defined but the tag key is set ... the value is
                // a flag
                list(, $key) = $matches;
                $this->meta($key, true);
            } else {
                throw new Exception("Impossible to parse '$def'");
            }
        }
    }

    public function satisfy(DocumentBodyFilter $filter = null)
    {
        if (null === $filter) {
            return true;
        }
        return $filter->accept($this);
    }

    public function __toString()
    {
        // return '<pre>' . print_r((array) $this->_metaStorage, true) . "</pre>\n\n" . $this->md;
        return $this->md;
    }
}
