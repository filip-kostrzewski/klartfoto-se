<?php

namespace App\Actions;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class SanitizeSvgLogo
{
    /** @var list<string> */
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'defs', 'title', 'desc', 'lineargradient', 'radialgradient', 'stop',
        'clippath',
    ];

    /** @var list<string> */
    private const ATTRIBUTES = [
        'xmlns', 'viewbox', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y',
        'x1', 'y1', 'x2', 'y2', 'points', 'transform', 'opacity', 'fill-opacity', 'stroke-opacity',
        'id', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform',
        'spreadmethod', 'preserveaspectratio', 'font-size', 'font-family', 'font-weight',
        'text-anchor', 'dominant-baseline', 'clip-path', 'clip-rule', 'fill-rule',
    ];

    public function handle(string $contents): ?string
    {
        if (strlen($contents) > 500_000 || preg_match('/<!DOCTYPE|<!ENTITY/i', $contents) === 1) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true || ! $document->documentElement instanceof DOMElement) {
            return null;
        }

        if (strtolower($document->documentElement->localName) !== 'svg') {
            return null;
        }

        if (! $this->nodeIsSafe($document->documentElement)) {
            return null;
        }

        $this->limitDimensions($document->documentElement);
        $xml = $document->saveXML($document->documentElement);

        if (! is_string($xml) || ! str_starts_with($xml, '<svg')) {
            return null;
        }

        return $xml;
    }

    private function nodeIsSafe(DOMNode $node): bool
    {
        if ($node instanceof DOMElement && ! $this->attributesAreSafe($node)) {
            return false;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText || $child instanceof DOMComment) {
                continue;
            }

            if ($child instanceof DOMCdataSection) {
                return false;
            }

            if (! $child instanceof DOMElement) {
                return false;
            }

            if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                return false;
            }

            if (! $this->nodeIsSafe($child)) {
                return false;
            }
        }

        return true;
    }

    private function attributesAreSafe(DOMElement $element): bool
    {
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (str_starts_with($name, 'on') || str_contains($name, 'href') || ! in_array($name, self::ATTRIBUTES, true)) {
                return false;
            }

            if (preg_match('/javascript:|expression\s*\(|data:/i', $attribute->value) === 1) {
                return false;
            }

            if (preg_match('/url\s*\(\s*[\'"]?(?!#)/i', $attribute->value) === 1) {
                return false;
            }
        }

        return true;
    }

    private function limitDimensions(DOMElement $svg): void
    {
        $maxEdge = max(1, (int) config('klartfoto.logo_max_edge', 800));

        foreach (['width', 'height'] as $name) {
            $value = $svg->getAttribute($name);

            if ($value !== '' && preg_match('/^\d+(\.\d+)?$/', $value) === 1 && (float) $value > $maxEdge) {
                $svg->setAttribute($name, (string) $maxEdge);
            }
        }
    }
}
