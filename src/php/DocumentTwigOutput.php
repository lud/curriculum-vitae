<?php

namespace CV;

class DocumentTwigOutput {

    private $document;
    private $key;
    private $docBodyFilter;

    public function __construct(\Spatie\YamlFrontMatter\Document $document, string $key)
    {
        $this->document = $document;
        $this->key = $key;
    }

    public function setBodyFilter(DocumentBodyFilter $filter = null)
    {
        $this->docBodyFilter = $filter;
    }

    public function body()
    {
        $filter = $this->docBodyFilter;
        $md = $this->document->body();
        $parts = $this->computeBodyParts($md);
        $filteredParts = [];
        foreach ($parts as $part) {
            if ($part->satisfy($filter)) {
                $filteredParts[] = $part;
            }
        }
        $md2 = implode("\n", $filteredParts);
        $parser = new \ParsedownExtra();
        $html = $parser->text($md2);
        return new \Twig_Markup($html, 'UTF-8');
    }

    private function computeBodyParts(string $md)
    {
        preg_match_all('/^<!--\s*(@.*)-->$/mu', $md, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $sectionHeaders = $matches;
        if (0 === count($sectionHeaders)) {
            // only one single part
            return [new DocumentBodySection($md)];
        }

        // $sectionHeaders:
        //
        // [0]=> // first match :
        //  array(2) {
        //    [0]=> // full match
        //    array(2) {
        //      [0]=> string(18) "<!--@topic sig -->"
        //      [1]=> int(42)
        //    }
        //    [1]=> // first capture
        //    array(2) {
        //      [0]=> string(11) "@topic sig "
        //      [1]=> int(46)
        //    }
        //  }
        // we reduce the parts in reverse, so each part is treated after the
        // following part in text order, so it knows its successor offset and
        // can stop here.
        $reversed = array_reverse($sectionHeaders);
        $parts = [];
        $reducer = function($endOffset, $match) use ($md, &$parts) {
            list($matchComment, $matchDocstring) = $match;
            list($comment, $commentOffset) = $matchComment;
            list($docstring) = $matchDocstring;
            $contentOffset = $commentOffset + strlen($comment);
            $md2 = substr($md, $contentOffset, $endOffset - $contentOffset);
            array_unshift($parts, new DocumentBodySection($md2, $docstring));
            // return the start offset as endOffset for the previous section
            return $commentOffset;
        };
        $firstMatchStart = array_reduce($reversed, $reducer, strlen($md));
        $firstContent = substr($md, 0, $firstMatchStart);
        $firstPart = new DocumentBodySection($firstContent);
        array_unshift($parts, $firstPart);
        return $parts;
    }

    public function __call($key, $_)
    {
        $value = $this->document->matter($key, "[$key]");
        if (!is_string($value)) {
            $value = json_encode($value, JSON_PRETTY_PRINT);
        }
        return $value;
    }

    public function key()
    {
        return $this->key;
    }

}
