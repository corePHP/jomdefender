<?php
/**
 * @package		jomDefender
 * @license		{http://www.gnu.org/licenses/gpl-2.0.html} GNU/GPL, see LICENSE.txt
 */

 define('ML_EXP','/^[^<]*(<(.|\s)+>)[^>]*$/'); require_once 'CssEventHandler.php'; require_once 'QueryPathExtension.php'; function qp($document = NULL, $string = NULL, $options = array()) { $qpClass = isset($options['QueryPath_class']) ? $options['QueryPath_class'] : 'QueryPath'; $qp = new $qpClass($document, $string, $options); return $qp; } class QueryPath implements IteratorAggregate { const VERSION = '2.0.1'; const HTML_STUB = '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
  <html lang="en">
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Untitled</title>
  </head>
  <body></body>
  </html>'; const XHTML_STUB = '<?xml version="1.0"?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml">
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Untitled</title>
  </head>
  <body></body>
  </html>'; const DEFAULT_PARSER_FLAGS = NULL; private $errTypes = 771; private $document = NULL; private $options = array( 'parser_flags' => NULL, 'omit_xml_declaration' => FALSE, 'replace_entities' => FALSE, 'exception_level' => 771, 'ignore_parser_warnings' => FALSE, ); private $matches = array(); private $last = array(); private $ext = array(); public function __construct($document = NULL, $string = NULL, $options = array()) { $string = trim($string); $this->options = $options + QueryPathOptions::get() + $this->options; $parser_flags = isset($options['parser_flags']) ? $options['parser_flags'] : self::DEFAULT_PARSER_FLAGS; if (!empty($this->options['ignore_parser_warnings'])) { $this->errTypes = 257; } elseif (isset($this->options['exception_level'])) { $this->errTypes = $this->options['exception_level']; } if (empty($document)) { $this->document = new DOMDocument(); $this->setMatches(new SplObjectStorage()); } elseif (is_object($document)) { if ($document instanceof QueryPath) { $this->matches = $document->get(NULL, TRUE); if ($this->matches->count() > 0) $this->document = $this->getFirstMatch()->ownerDocument; } elseif ($document instanceof DOMDocument) { $this->document = $document; $this->setMatches($document->documentElement); } elseif ($document instanceof DOMNode) { $this->document = $document->ownerDocument; $this->setMatches($document); } elseif ($document instanceof SimpleXMLElement) { $import = dom_import_simplexml($document); $this->document = $import->ownerDocument; $this->setMatches($import); } elseif ($document instanceof SplObjectStorage) { $this->matches = $document; $this->document = $this->getFirstMatch()->ownerDocument; } else { throw new QueryPathException('Unsupported class type: ' . get_class($document)); } } elseif (is_array($document)) { if (!empty($document) && $document[0] instanceof DOMNode) { $found = new SplObjectStorage(); foreach ($document as $item) $found->attach($item); $this->setMatches($found); $this->document = $this->getFirstMatch()->ownerDocument; } } elseif ($this->isXMLish($document)) { $this->document = $this->parseXMLString($document); $this->setMatches($this->document->documentElement); } else { $context = empty($options['context']) ? NULL : $options['context']; $this->document = $this->parseXMLFile($document, $parser_flags, $context); $this->setMatches($this->document->documentElement); } if (isset($string) && strlen($string) > 0) { $this->find($string); } } public function getOptions() { return $this->options; } public function top($selector = NULL) { $this->setMatches($this->document->documentElement); return !empty($selector) ? $this->find($selector) : $this; } public function find($selector) { $ids = array(); $regex = '/^#([\w-]+)$|^\.([\w-]+)$/'; if (preg_match($regex, $selector, $ids) === 1) { if (!empty($ids[1])) { $xpath = new DOMXPath($this->document); foreach ($this->matches as $item) { $nl = $xpath->query("//*[@id='{$ids[1]}']", $item); if ($nl->length > 0) { $this->setMatches($nl->item(0)); break; } else { $this->noMatches(); } } } else { $xpath = new DOMXPath($this->document); $found = new SplObjectStorage(); foreach ($this->matches as $item) { $nl = $xpath->query("//*[@class]", $item); for ($i = 0; $i < $nl->length; ++$i) { $vals = explode(' ', $nl->item($i)->getAttribute('class')); if (in_array($ids[2], $vals)) $found->attach($nl->item($i)); } } $this->setMatches($found); } return $this; } $query = new QueryPathCssEventHandler($this->matches); $query->find($selector); $this->setMatches($query->getMatches()); return $this; } public function xpath($query) { $xpath = new DOMXPath($this->document); $found = new SplObjectStorage(); foreach ($this->matches as $item) { $nl = $xpath->query($query, $item); if ($nl->length > 0) { for ($i = 0; $i < $nl->length; ++$i) $found->attach($nl->item($i)); } } $this->setMatches($found); return $this; } public function size() { return $this->matches->count(); } public function get($index = NULL, $asObject = FALSE) { if (isset($index)) { return ($this->size() > $index) ? $this->getNthMatch($index) : NULL; } if (!$asObject) { $matches = array(); foreach ($this->matches as $m) $matches[] = $m; return $matches; } return $this->matches; } public function attr($name, $value = NULL) { if (is_array($name)) { foreach ($name as $k => $v) { foreach ($this->matches as $m) $m->setAttribute($k, $v); } return $this; } if (isset($value)) { foreach ($this->matches as $m) $m->setAttribute($name, $value); return $this; } if ($this->matches->count() == 0) return NULL; if ($name == 'nodeType') { return $this->getFirstMatch()->nodeType; } return $this->getFirstMatch()->getAttribute($name); } public function hasAttr($attrName) { foreach ($this->matches as $match) { if (!$match->hasAttribute($attrName)) return FALSE; } return TRUE; } public function css($name = NULL, $value = '') { if (empty($name)) { return $this->attr('style'); } $format = '%s: %s'; if (is_array($name)) { $buf = array(); foreach ($name as $key => $val) { $buf[] = sprintf($format, $key, $val); } $css = implode(';', $buf); } else { $css = sprintf($format, $name, $value); } $this->attr('style', $css); return $this; } public function removeAttr($name) { foreach ($this->matches as $m) { $m->removeAttribute($name); } return $this; } public function eq($index) { $this->setMatches($this->getNthMatch($index)); return $this; } public function is($selector) { foreach ($this->matches as $m) { $q = new QueryPathCssEventHandler($m); if ($q->find($selector)->getMatches()->count()) { return TRUE; } } return FALSE; } public function filter($selector) { $found = new SplObjectStorage(); foreach ($this->matches as $m) if (qp($m, NULL, $this->options)->is($selector)) $found->attach($m); $this->setMatches($found); return $this; } public function filterLambda($fn) { $function = create_function('$index, $item', $fn); $found = new SplObjectStorage(); $i = 0; foreach ($this->matches as $item) if ($function($i++, $item) !== FALSE) $found->attach($item); $this->setMatches($found); return $this; } public function filterCallback($callback) { $found = new SplObjectStorage(); $i = 0; if (is_callable($callback)) { foreach($this->matches as $item) if (call_user_func($callback, $i++, $item) !== FALSE) $found->attach($item); } else { throw new QueryPathException('The specified callback is not callable.'); } $this->setMatches($found); return $this; } public function not($selector) { $found = new SplObjectStorage(); if ($selector instanceof DOMElement) { foreach ($this->matches as $m) if ($m !== $selector) $found->attach($m); } elseif (is_array($selector)) { foreach ($this->matches as $m) { if (!in_array($m, $selector, TRUE)) $found->attach($m); } } elseif ($selector instanceof SplObjectStorage) { foreach ($this->matches as $m) if ($selector->contains($m)) $found->attach($m); } else { foreach ($this->matches as $m) if (!qp($m, NULL, $this->options)->is($selector)) $found->attach($m); } $this->setMatches($found); return $this; } public function index($subject) { $i = 0; foreach ($this->matches as $m) { if ($m === $subject) { return $i; } ++$i; } return FALSE; } public function map($callback) { $found = new SplObjectStorage(); if (is_callable($callback)) { $i = 0; foreach ($this->matches as $item) { $c = call_user_func($callback, $i, $item); if (isset($c)) { if (is_array($c) || $c instanceof Iterable) { foreach ($c as $retval) { if (!is_object($retval)) { $tmp = new stdClass(); $tmp->textContent = $retval; $retval = $tmp; } $found->attach($retval); } } else { if (!is_object($c)) { $tmp = new stdClass(); $tmp->textContent = $c; $c = $tmp; } $found->attach($c); } } ++$i; } } else { throw new QueryPathException('Callback is not callable.'); } $this->setMatches($found, FALSE); return $this; } public function slice($start, $end = 0) { $found = new SplObjectStorage(); if ($start >= $this->size()) { $this->setMatches($found); return $this; } $i = $j = 0; foreach ($this->matches as $m) { if ($i >= $start) { if ($end > 0 && $j >= $end) { break; } $found->attach($m); ++$j; } ++$i; } $this->setMatches($found); return $this; } public function each($callback) { if (is_callable($callback)) { $i = 0; foreach ($this->matches as $item) { if (call_user_func($callback, $i, $item) === FALSE) return $this; ++$i; } } else { throw new QueryPathException('Callback is not callable.'); } return $this; } public function eachLambda($lambda) { $index = 0; foreach ($this->matches as $item) { $fn = create_function('$index, &$item', $lambda); if ($fn($index, $item) === FALSE) return $this; ++$index; } return $this; } public function append($data) { $data = $this->prepareInsert($data); if (isset($data)) { if (empty($this->document->documentElement) && $this->matches->count() == 0) { $this->document->appendChild($data); $found = new SplObjectStorage(); $found->attach($this->document->documentElement); $this->setMatches($found); } else { foreach ($this->matches as $m) { if ($data instanceof DOMDocumentFragment) { foreach ($data->childNodes as $n) $m->appendChild($n->cloneNode(TRUE)); } else { $m->appendChild($data->cloneNode(TRUE)); } } } } return $this; } public function appendTo(QueryPath $dest) { foreach ($this->matches as $m) $dest->append($m); return $this; } public function prepend($data) { $data = $this->prepareInsert($data); if (isset($data)) { foreach ($this->matches as $m) { $ins = $data->cloneNode(TRUE); if ($m->hasChildNodes()) $m->insertBefore($ins, $m->childNodes->item(0)); else $m->appendChild($ins); } } return $this; } public function prependTo(QueryPath $dest) { foreach ($this->matches as $m) $dest->prepend($m); return $this; } public function before($data) { $data = $this->prepareInsert($data); foreach ($this->matches as $m) { $ins = $data->cloneNode(TRUE); $m->parentNode->insertBefore($ins, $m); } return $this; } public function insertBefore(QueryPath $dest) { foreach ($this->matches as $m) $dest->before($m); return $this; } public function insertAfter(QueryPath $dest) { foreach ($this->matches as $m) $dest->after($m); return $this; } public function after($data) { $data = $this->prepareInsert($data); foreach ($this->matches as $m) { $ins = $data->cloneNode(TRUE); if (isset($m->nextSibling)) $m->parentNode->insertBefore($ins, $m->nextSibling); else $m->parentNode->appendChild($ins); } return $this; } public function replaceWith($new) { $data = $this->prepareInsert($new); $found = new SplObjectStorage(); foreach ($this->matches as $m) { $parent = $m->parentNode; $parent->insertBefore($data->cloneNode(TRUE), $m); $found->attach($parent->removeChild($m)); } $this->setMatches($found); return $this; } public function wrap($markup) { $data = $this->prepareInsert($markup); if (empty($data)) { return $this; } foreach ($this->matches as $m) { $copy = $data->firstChild->cloneNode(TRUE); if ($copy->hasChildNodes()) { $deepest = $this->deepestNode($copy); $bottom = $deepest[0]; } else $bottom = $copy; $parent = $m->parentNode; $parent->insertBefore($copy, $m); $m = $parent->removeChild($m); $bottom->appendChild($m); } return $this; } public function wrapAll($markup) { if ($this->matches->count() == 0) return; $data = $this->prepareInsert($markup); if (empty($data)) { return $this; } if ($data->hasChildNodes()) { $deepest = $this->deepestNode($data); $bottom = $deepest[0]; } else $bottom = $data; $first = $this->getFirstMatch(); $parent = $first->parentNode; $parent->insertBefore($data, $first); foreach ($this->matches as $m) { $bottom->appendChild($m->parentNode->removeChild($m)); } return $this; } public function wrapInner($markup) { $data = $this->prepareInsert($markup); if (empty($data)) return $this; if ($data->hasChildNodes()) { $deepest = $this->deepestNode($data); $bottom = $deepest[0]; } else $bottom = $data; foreach ($this->matches as $m) { if ($m->hasChildNodes()) { while($m->firstChild) { $kid = $m->removeChild($m->firstChild); $bottom->appendChild($kid); } } $m->appendChild($data); } return $this; } public function deepest() { $deepest = 0; $winner = new SplObjectStorage(); foreach ($this->matches as $m) { $local_deepest = 0; $local_ele = $this->deepestNode($m, 0, NULL, $local_deepest); if ($local_deepest > $deepest) { $winner = new SplObjectStorage(); foreach ($local_ele as $lele) $winner->attach($lele); $deepest = $local_deepest; } elseif ($local_deepest == $deepest) { foreach ($local_ele as $lele) $winner->attach($lele); } } $this->setMatches($winner); return $this; } protected function deepestNode(DOMNode $ele, $depth = 0, $current = NULL, &$deepest = NULL) { if (!isset($current)) $current = array($ele); if (!isset($deepest)) $deepest = $depth; if ($ele->hasChildNodes()) { foreach ($ele->childNodes as $child) { if ($child->nodeType === XML_ELEMENT_NODE) { $current = $this->deepestNode($child, $depth + 1, $current, $deepest); } } } elseif ($depth > $deepest) { $current = array($ele); $deepest = $depth; } elseif ($depth === $deepest) { $current[] = $ele; } return $current; } protected function prepareInsert($item) { if(empty($item)) { return; } elseif (is_string($item)) { if ($this->options['replace_entities']) { $item = QueryPathEntities::replaceAllEntities($item); } $frag = $this->document->createDocumentFragment(); try { set_error_handler(array('QueryPathParseException', 'initializeFromError'), $this->errTypes); $frag->appendXML($item); } catch (Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); return $frag; } elseif ($item instanceof QueryPath) { if ($item->size() == 0) return; return $this->prepareInsert($item->get(0)); } elseif ($item instanceof DOMNode) { if ($item->ownerDocument !== $this->document) { $item = $this->document->importNode($item, TRUE); } return $item; } elseif ($item instanceof SimpleXMLElement) { $element = dom_import_simplexml($item); return $this->document->importNode($element, TRUE); } throw new QueryPathException("Cannot prepare item of unsupported type: " . gettype($item)); } public function tag() { return ($this->size() > 0) ? $this->getFirstMatch()->tagName : ''; } public function remove($selector = NULL) { if(!empty($selector)) $this->find($selector); $found = new SplObjectStorage(); foreach ($this->matches as $item) { $found->attach($item->parentNode->removeChild($item)); } $this->setMatches($found); return $this; } public function replaceAll($selector, DOMDocument $document) { $replacement = $this->size() > 0 ? $this->getFirstMatch() : $this->document->createTextNode(''); $c = new QueryPathCssEventHandler($document); $c->find($selector); $temp = $c->getMatches(); foreach ($temp as $item) { $node = $replacement->cloneNode(); $node = $document->importNode($node); $item->parentNode->replaceChild($node, $item); } return qp($document, NULL, $this->options); } public function add($selector) { $this->last = $this->matches; foreach (qp($this->document, $selector, $this->options)->get() as $item) $this->matches->attach($item); return $this; } public function end() { $this->matches = $this->last; $this->last = new SplObjectStorage(); return $this; } public function andSelf() { $last = $this->matches; foreach ($this->last as $item) $this->matches->attach($item); $this->last = $last; return $this; } public function removeChildren() { foreach ($this->matches as $m) { while($kid = $m->firstChild) { $m->removeChild($kid); } } return $this; } public function children($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { foreach($m->childNodes as $c) { if ($c->nodeType == XML_ELEMENT_NODE) $found->attach($c); } } if (empty($selector)) { $this->setMatches($found); } else { $this->matches = $found; $this->filter($selector); } return $this; } public function contents() { $found = new SplObjectStorage(); foreach ($this->matches as $m) { foreach ($m->childNodes as $c) { $found->attach($c); } } $this->setMatches($found); return $this; } public function siblings($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { $parent = $m->parentNode; foreach ($parent->childNodes as $n) { if ($n->nodeType == XML_ELEMENT_NODE && $n !== $m) { $found->attach($n); } } } if (empty($selector)) { $this->setMatches($found); } else { $this->matches = $found; $this->filter($selector); } return $this; } public function closest($selector) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { if (qp($m, NULL, $this->options)->is($selector) > 0) { $found->attach($m); } else { while ($m->parentNode->nodeType !== XML_DOCUMENT_NODE) { $m = $m->parentNode; if ($m->nodeType === XML_ELEMENT_NODE && qp($m, NULL, $this->options)->is($selector) > 0) { $found->attach($m); break; } } } } $this->setMatches($found); return $this; } public function parent($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while ($m->parentNode->nodeType !== XML_DOCUMENT_NODE) { $m = $m->parentNode; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector) > 0) { $found->attach($m); break; } } else { $found->attach($m); break; } } } } $this->setMatches($found); return $this; } public function parents($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while ($m->parentNode->nodeType !== XML_DOCUMENT_NODE) { $m = $m->parentNode; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector) > 0) $found->attach($m); } else $found->attach($m); } } } $this->setMatches($found); return $this; } public function html($markup = NULL) { if (isset($markup)) { if ($this->options['replace_entities']) { $markup = QueryPathEntities::replaceAllEntities($markup); } $doc = $this->document->createDocumentFragment(); $doc->appendXML($markup); $this->removeChildren(); $this->append($doc); return $this; } $length = $this->size(); if ($length == 0) { return NULL; } $first = $this->getFirstMatch(); if (!($first instanceof DOMNode)) { return NULL; } if ($first instanceof DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) { return $this->document->saveHTML(); } return $this->document->saveXML($first); } public function innerHTML() { return $this->innerXML(); } public function innerXHTML() { return $this->innerXML(); } public function innerXML() { $length = $this->size(); if ($length == 0) { return NULL; } $first = $this->getFirstMatch(); if (!($first instanceof DOMNode)) { return NULL; } elseif (!$first->hasChildNodes()) { return ''; } $buffer = ''; foreach ($first->childNodes as $child) { $buffer .= $this->document->saveXML($child); } return $buffer; } public function textImplode($sep = ', ', $filterEmpties = TRUE) { $tmp = array(); foreach ($this->matches as $m) { $txt = $m->textContent; $trimmed = trim($txt); if ($filterEmpties) { if (strlen($trimmed) > 0) $tmp[] = $txt; } else { $tmp[] = $txt; } } return implode($sep, $tmp); } public function text($text = NULL) { if (isset($text)) { $this->removeChildren(); $textNode = $this->document->createTextNode($text); foreach($this->matches as $m) $m->appendChild($textNode); return $this; } $buf = ''; foreach ($this->matches as $m) $buf .= $m->textContent; return $buf; } public function val($value = NULL) { if (isset($value)) { $this->attr('value', $value); return $this; } return $this->attr('value'); } public function xhtml($markup = NULL) { return $this->xml($markup); } public function xml($markup = NULL) { $omit_xml_decl = $this->options['omit_xml_declaration']; if ($markup === TRUE) { $omit_xml_decl = TRUE; } elseif (isset($markup)) { if ($this->options['replace_entities']) { $markup = QueryPathEntities::replaceAllEntities($markup); } $doc = $this->document->createDocumentFragment(); $doc->appendXML($markup); $this->removeChildren(); $this->append($doc); return $this; } $length = $this->size(); if ($length == 0) { return NULL; } $first = $this->getFirstMatch(); if (!($first instanceof DOMNode)) { return NULL; } if ($first instanceof DOMDocument || $first->isSameNode($first->ownerDocument->documentElement)) { return ($omit_xml_decl ? $this->document->saveXML($first->ownerDocument->documentElement) : $this->document->saveXML()); } return $this->document->saveXML($first); } public function writeXML($path = NULL) { if ($path == NULL) { print $this->document->saveXML(); } else { try { set_error_handler(array('QueryPathIOException', 'initializeFromError')); $this->document->save($path); } catch (Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); } return $this; } public function writeHTML($path = NULL) { if ($path == NULL) { print $this->document->saveHTML(); } else { try { set_error_handler(array('QueryPathParseException', 'initializeFromError')); $this->document->saveHTMLFile($path); } catch (Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); } return $this; } public function writeXHTML($path = NULL) { return $this->writeXML($path); } public function next($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while (isset($m->nextSibling)) { $m = $m->nextSibling; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector) > 0) { $found->attach($m); break; } } else { $found->attach($m); break; } } } } $this->setMatches($found); return $this; } public function nextAll($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while (isset($m->nextSibling)) { $m = $m->nextSibling; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector) > 0) { $found->attach($m); } } else { $found->attach($m); } } } } $this->setMatches($found); return $this; } public function prev($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while (isset($m->previousSibling)) { $m = $m->previousSibling; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector)) { $found->attach($m); break; } } else { $found->attach($m); break; } } } } $this->setMatches($found); return $this; } public function prevAll($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { while (isset($m->previousSibling)) { $m = $m->previousSibling; if ($m->nodeType === XML_ELEMENT_NODE) { if (!empty($selector)) { if (qp($m, NULL, $this->options)->is($selector)) { $found->attach($m); } } else { $found->attach($m); } } } } $this->setMatches($found); return $this; } public function peers($selector = NULL) { $found = new SplObjectStorage(); foreach ($this->matches as $m) { foreach ($m->parentNode->childNodes as $kid) { if ($kid->nodeType == XML_ELEMENT_NODE && $m !== $kid) { if (!empty($selector)) { if (qp($kid, NULL, $this->options)->is($selector)) { $found->attach($kid); } } else { $found->attach($kid); } } } } $this->setMatches($found); return $this; } public function addClass($class) { foreach ($this->matches as $m) { if ($m->hasAttribute('class')) { $val = $m->getAttribute('class'); $m->setAttribute('class', $val . ' ' . $class); } else { $m->setAttribute('class', $class); } } return $this; } public function removeClass($class) { foreach ($this->matches as $m) { if ($m->hasAttribute('class')) { $vals = explode(' ', $m->getAttribute('class')); if (in_array($class, $vals)) { $buf = array(); foreach ($vals as $v) { if ($v != $class) $buf[] = $v; } if (count($buf) == 0) $m->removeAttribute('class'); else $m->setAttribute('class', implode(' ', $buf)); } } } return $this; } public function hasClass($class) { foreach ($this->matches as $m) { if ($m->hasAttribute('class')) { $vals = explode(' ', $m->getAttribute('class')); if (in_array($class, $vals)) return TRUE; } } return FALSE; } public function branch($selector = NULL) { $temp = qp($this->matches, NULL, $this->options); if (isset($selector)) $temp->find($selector); return $temp; } public function cloneAll() { $found = new SplObjectStorage(); foreach ($this->matches as $m) $found->attach($m->cloneNode(TRUE)); $this->setMatches($found, FALSE); return $this; } public function __clone() { $this->cloneAll(); } protected function isXMLish($string) { $test = substr($string, 0, 255); return (strpos($string, '<') !== FALSE && strpos($string, '>') !== FALSE); } private function parseXMLString($string, $flags = NULL) { $document = new DOMDocument(); $lead = strtolower(substr($string, 0, 5)); try { set_error_handler(array('QueryPathParseException', 'initializeFromError'), $this->errTypes); if ($lead == '<?xml') { if ($this->options['replace_entities']) { $string = QueryPathEntities::replaceAllEntities($string); } $document->loadXML($string, $flags); } else { $document->loadHTML($string); } } catch (Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); if (empty($document)) { throw new QueryPathParseException('Unknown parser exception.'); } return $document; } private function setMatches($matches, $unique = TRUE) { $this->last = $this->matches; if ($matches instanceof SplObjectStorage) { $this->matches = $matches; } elseif (is_array($matches)) { trigger_error('Legacy array detected.'); $tmp = new SplObjectStorage(); foreach ($matches as $m) $tmp->attach($m); $this->matches = $tmp; } else { $found = new SplObjectStorage(); if (isset($matches)) $found->attach($matches); $this->matches = $found; } } private function noMatches() { $this->setMatches(NULL); } private function getNthMatch($index) { if ($index > $this->matches->count()) return; $i = 0; foreach ($this->matches as $m) { if ($i++ == $index) return $m; } } private function getFirstMatch() { $this->matches->rewind(); return $this->matches->current(); } private function parseXMLFile($filename, $flags = NULL, $context = NULL) { if (!empty($context)) { try { set_error_handler(array('QueryPathParseException', 'initializeFromError'), $this->errTypes); $contents = file_get_contents($filename, FALSE, $context); } catch(Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); if ($contents == FALSE) { throw new QueryPathParseException(sprintf('Contents of the file %s could not be retrieved.', $filename)); } return $this->parseXMLString($contents, $flags); } $document = new DOMDocument(); $lastDot = strrpos($filename, '.'); try { set_error_handler(array('QueryPathParseException', 'initializeFromError'), $this->errTypes); if ($lastDot !== FALSE && strtolower(substr($filename, $lastDot)) == '.html') { $r = $document->loadHTMLFile($filename); } else { $r = $document->load($filename, $flags); } } catch (Exception $e) { restore_error_handler(); throw $e; } restore_error_handler(); return $document; } public function __call($name, $arguments) { if (!QueryPathExtensionRegistry::$useRegistry) { throw new QueryPathException("No method named $name found (Extensions disabled)."); } if (empty($this->ext)) { $this->ext = QueryPathExtensionRegistry::getExtensions($this); } if (!empty($this->ext) && QueryPathExtensionRegistry::hasMethod($name)) { $owner = QueryPathExtensionRegistry::getMethodClass($name); $method = new ReflectionMethod($owner, $name); return $method->invokeArgs($this->ext[$owner], $arguments); } throw new QueryPathException("No method named $name found. Possibly missing an extension."); } public function getIterator() { $i = new QueryPathIterator($this->matches); $i->options = $this->options; return $i; } } class QueryPathEntities { protected static $regex = '/&([\w]+);|&#([\d]+);|&#(x[0-9a-fA-F]+);|(&)/m'; public static function replaceAllEntities($string) { return preg_replace_callback(self::$regex, 'QueryPathEntities::doReplacement', $string); } protected static function doReplacement($matches) { $count = count($matches); switch ($count) { case 2: return '&#' . self::replaceEntity($matches[1]) . ';'; case 3: case 4: return '&#' . $matches[$count-1] . ';'; case 5: return '&#38;'; } } public static function replaceEntity($entity) { return self::$entity_array[$entity]; } private static $entity_array = array( 'nbsp' => 160, 'iexcl' => 161, 'cent' => 162, 'pound' => 163, 'curren' => 164, 'yen' => 165, 'brvbar' => 166, 'sect' => 167, 'uml' => 168, 'copy' => 169, 'ordf' => 170, 'laquo' => 171, 'not' => 172, 'shy' => 173, 'reg' => 174, 'macr' => 175, 'deg' => 176, 'plusmn' => 177, 'sup2' => 178, 'sup3' => 179, 'acute' => 180, 'micro' => 181, 'para' => 182, 'middot' => 183, 'cedil' => 184, 'sup1' => 185, 'ordm' => 186, 'raquo' => 187, 'frac14' => 188, 'frac12' => 189, 'frac34' => 190, 'iquest' => 191, 'Agrave' => 192, 'Aacute' => 193, 'Acirc' => 194, 'Atilde' => 195, 'Auml' => 196, 'Aring' => 197, 'AElig' => 198, 'Ccedil' => 199, 'Egrave' => 200, 'Eacute' => 201, 'Ecirc' => 202, 'Euml' => 203, 'Igrave' => 204, 'Iacute' => 205, 'Icirc' => 206, 'Iuml' => 207, 'ETH' => 208, 'Ntilde' => 209, 'Ograve' => 210, 'Oacute' => 211, 'Ocirc' => 212, 'Otilde' => 213, 'Ouml' => 214, 'times' => 215, 'Oslash' => 216, 'Ugrave' => 217, 'Uacute' => 218, 'Ucirc' => 219, 'Uuml' => 220, 'Yacute' => 221, 'THORN' => 222, 'szlig' => 223, 'agrave' => 224, 'aacute' => 225, 'acirc' => 226, 'atilde' => 227, 'auml' => 228, 'aring' => 229, 'aelig' => 230, 'ccedil' => 231, 'egrave' => 232, 'eacute' => 233, 'ecirc' => 234, 'euml' => 235, 'igrave' => 236, 'iacute' => 237, 'icirc' => 238, 'iuml' => 239, 'eth' => 240, 'ntilde' => 241, 'ograve' => 242, 'oacute' => 243, 'ocirc' => 244, 'otilde' => 245, 'ouml' => 246, 'divide' => 247, 'oslash' => 248, 'ugrave' => 249, 'uacute' => 250, 'ucirc' => 251, 'uuml' => 252, 'yacute' => 253, 'thorn' => 254, 'yuml' => 255, 'quot' => 34, 'amp' => 38, 'lt' => 60, 'gt' => 62, 'apos' => 39, 'OElig' => 338, 'oelig' => 339, 'Scaron' => 352, 'scaron' => 353, 'Yuml' => 376, 'circ' => 710, 'tilde' => 732, 'ensp' => 8194, 'emsp' => 8195, 'thinsp' => 8201, 'zwnj' => 8204, 'zwj' => 8205, 'lrm' => 8206, 'rlm' => 8207, 'ndash' => 8211, 'mdash' => 8212, 'lsquo' => 8216, 'rsquo' => 8217, 'sbquo' => 8218, 'ldquo' => 8220, 'rdquo' => 8221, 'bdquo' => 8222, 'dagger' => 8224, 'Dagger' => 8225, 'permil' => 8240, 'lsaquo' => 8249, 'rsaquo' => 8250, 'euro' => 8364, 'fnof' => 402, 'Alpha' => 913, 'Beta' => 914, 'Gamma' => 915, 'Delta' => 916, 'Epsilon' => 917, 'Zeta' => 918, 'Eta' => 919, 'Theta' => 920, 'Iota' => 921, 'Kappa' => 922, 'Lambda' => 923, 'Mu' => 924, 'Nu' => 925, 'Xi' => 926, 'Omicron' => 927, 'Pi' => 928, 'Rho' => 929, 'Sigma' => 931, 'Tau' => 932, 'Upsilon' => 933, 'Phi' => 934, 'Chi' => 935, 'Psi' => 936, 'Omega' => 937, 'alpha' => 945, 'beta' => 946, 'gamma' => 947, 'delta' => 948, 'epsilon' => 949, 'zeta' => 950, 'eta' => 951, 'theta' => 952, 'iota' => 953, 'kappa' => 954, 'lambda' => 955, 'mu' => 956, 'nu' => 957, 'xi' => 958, 'omicron' => 959, 'pi' => 960, 'rho' => 961, 'sigmaf' => 962, 'sigma' => 963, 'tau' => 964, 'upsilon' => 965, 'phi' => 966, 'chi' => 967, 'psi' => 968, 'omega' => 969, 'thetasym' => 977, 'upsih' => 978, 'piv' => 982, 'bull' => 8226, 'hellip' => 8230, 'prime' => 8242, 'Prime' => 8243, 'oline' => 8254, 'frasl' => 8260, 'weierp' => 8472, 'image' => 8465, 'real' => 8476, 'trade' => 8482, 'alefsym' => 8501, 'larr' => 8592, 'uarr' => 8593, 'rarr' => 8594, 'darr' => 8595, 'harr' => 8596, 'crarr' => 8629, 'lArr' => 8656, 'uArr' => 8657, 'rArr' => 8658, 'dArr' => 8659, 'hArr' => 8660, 'forall' => 8704, 'part' => 8706, 'exist' => 8707, 'empty' => 8709, 'nabla' => 8711, 'isin' => 8712, 'notin' => 8713, 'ni' => 8715, 'prod' => 8719, 'sum' => 8721, 'minus' => 8722, 'lowast' => 8727, 'radic' => 8730, 'prop' => 8733, 'infin' => 8734, 'ang' => 8736, 'and' => 8743, 'or' => 8744, 'cap' => 8745, 'cup' => 8746, 'int' => 8747, 'there4' => 8756, 'sim' => 8764, 'cong' => 8773, 'asymp' => 8776, 'ne' => 8800, 'equiv' => 8801, 'le' => 8804, 'ge' => 8805, 'sub' => 8834, 'sup' => 8835, 'nsub' => 8836, 'sube' => 8838, 'supe' => 8839, 'oplus' => 8853, 'otimes' => 8855, 'perp' => 8869, 'sdot' => 8901, 'lceil' => 8968, 'rceil' => 8969, 'lfloor' => 8970, 'rfloor' => 8971, 'lang' => 9001, 'rang' => 9002, 'loz' => 9674, 'spades' => 9824, 'clubs' => 9827, 'hearts' => 9829, 'diams' => 9830 ); } class QueryPathIterator extends IteratorIterator { public $options = array(); public function current() { return qp(parent::current(), NULL, $this->options); } } class QueryPathOptions { static $options = array(); static function set($array) { self::$options = $array; } static function get() { return self::$options; } static function merge($array) { self::$options = $array + self::$options; } static function has($key) { return array_key_exists($key, self::$options); } } class QueryPathException extends Exception {} class QueryPathParseException extends QueryPathException { const ERR_MSG_FORMAT = 'Parse error in %s on line %d column %d: %s (%d)'; const WARN_MSG_FORMAT = 'Parser warning in %s on line %d column %d: %s (%d)'; public function __construct($msg = '', $code = 0, $file = NULL, $line = NULL) { $msgs = array(); foreach(libxml_get_errors() as $err) { $format = $err->level == LIBXML_ERR_WARNING ? self::WARN_MSG_FORMAT : self::ERR_MSG_FORMAT; $msgs[] = sprintf($format, $err->file, $err->line, $err->column, $err->message, $err->code); } $msg .= implode("\n", $msgs); if (isset($file)) { $msg .= ' (' . $file; if (isset($line)) $msg .= ': ' . $line; $msg .= ')'; } parent::__construct($msg, $code); } public static function initializeFromError($code, $str, $file, $line, $cxt) { $class = __CLASS__; throw new $class($str, $code, $file, $line); } } class QueryPathIOException extends QueryPathParseException { public static function initializeFromError($code, $str, $file, $line, $cxt) { $class = __CLASS__; throw new $class($str, $code, $file, $line); } }