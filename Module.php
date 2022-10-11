<?php

namespace Modules\TextToUrl;

use Core\CModule;
use CController;
use DOMDocument;
use DOMNode;
use DOMNodeList;
use DOMText;

class Module extends CModule {

    public const MATCH_RULE = '/(?<href>https?:\/\/[^\\s]+)/';

    protected $link_attributes = [
        'target'    => '_blank',
        'rel'       => 'noreferrer'
    ];

    public function onTerminate(CController $action): void {
        echo $this->modifyResponse(ob_get_clean());
    }

    protected function modifyResponse($content): string {
        $json = json_decode($content, true);

        if ($json === null) {
            return $this->processHTMLContent($content);
        }

        if (array_key_exists('body', $json)) {
            $json['body'] = html_entity_decode($this->processHTMLContent($json['body']));
        }

        return json_encode($json);
    }

    protected function processHTMLContent($html): string {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        if (!$dom->loadHTML($html)) {
            return $html;
        }

        $this->processNodesRecirsive($dom->getElementsByTagName('td'));
        
        return $dom->saveHTML();
    }

    protected function processNodesRecirsive(DOMNodeList $list): DOMNodeList {
        /** @var \DOMNode $node */
        foreach ($list as $node) {
            if ($node->childNodes->length) {
                $this->processNodesRecirsive($node->childNodes);
                continue;
            }

            if (in_array(strtolower($node->tagName), ['a', 'input', 'button', 'textarea', 'label'])) {
                continue;
            }

            $matches = [];
            $innerText = $node->textContent;
            $i = 0;

            if (!preg_match_all(static::MATCH_RULE, $innerText, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches['href'] as $match) {
                if ($i !== $match[1]) {
                    $text = new DOMText(substr($innerText, $i, $match[1] - $i));
                    $node->parentNode->insertBefore($text, $node);
                }

                $link = $node->ownerDocument->createElement('a', $match[0]);
                $link->setAttribute('href', $match[0]);

                foreach ($this->link_attributes as $attr => $value) {
                    $link->setAttribute($attr, $value);
                }

                $node->parentNode->insertBefore($link, $node);
                $i = strlen($match[0]) + $match[1];
            }

            $node->parentNode->removeChild($node);
        }

        return $list;
    }
}
