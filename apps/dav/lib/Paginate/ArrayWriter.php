<?php


declare(strict_types=1);

namespace OCA\DAV\Paginate;

use InvalidArgumentException;
use JsonException;
use LogicException;
use Override;
use ReturnTypeWillChange;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlSerializable;

/**
 * Writer implementation that stores XML in a clark-notation array
 * instead of an XML string.
 *
 * The generated structure can be fed back to a normal Writer via
 * \Sabre\Xml\Writer::write().
 */
class ArrayWriter extends Writer {
	/**
	 * Holds the document tree in the standard array format.
	 *
	 * @var array<int, mixed>
	 */
	protected array $document = [];

	/**
	 * Stack of open elements.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	protected array $elementStack = [];

	/**
	 * Holds the current element, if any.
	 */
	protected ?array $currentElement = null;

	#[Override]
	public static function toStream($stream): static {
		throw new LogicException('Operation on ArrayWriter not supported');
	}

	#[Override]
	public static function toUri(string $uri): static {
		throw new LogicException('Operation on ArrayWriter not supported');
	}

	#[Override]
	public static function toMemory(): ArrayWriter {
		return new self();
	}

	#[Override]
	public function startDocument(?string $version = '', ?string $encoding = null, ?string $standalone = null): bool {
		$this->openMemory();

		return true;
	}

	/**
	 * Initializes the writer.
	 */
	#[Override]
	public function openMemory(): bool {
		$this->document = [];
		$this->elementStack = [];

		return true;
	}

	#[Override]
	public function endDocument(): bool {
		return true;
	}

	#[Override]
	public function writeElement($name, $content = null): bool {
		$this->startElement($name);
		if ($content !== null) {
			$this->write($content);
		}
		$this->endElement();

		return true;
	}

	#[Override]
	public function startElement($name): bool {
		$element = ['name' => $name, 'value' => [], 'attributes' => []];

		if (!empty($this->elementStack)) {
			$this->pushToParent($element);
		} else {
			$this->setRootElement($element);
		}
		$this->pushToElementStack($element);
		$this->currentElement = &$element;

		return true;
	}

	/**
	 * Pushes an element inside the parent element.
	 */
	private function pushToParent(array &$element): void {
		$parent = &$this->elementStack[count($this->elementStack) - 1]['value'];
		$parent[] = &$element;
	}

	/**
	 * Sets the element that is at the root of the document.
	 */
	private function setRootElement(array &$element): void {
		$this->document[] = &$element;
	}

	public function pushToElementStack(array &$element): void {
		$this->elementStack[] = &$element;
	}

	#[Override]
	public function write(mixed $value): void {
		if (is_scalar($value) || $value === null) {
			$this->text((string)($value ?? ''));
		} elseif ($value instanceof XmlSerializable) {
			$value->xmlSerialize($this);
		} elseif (is_array($value)) {
			$this->decomposeArray($value);
		} elseif (is_callable($value)) {
			$value($this);
		} elseif (is_object($value) && isset($this->classMap[get_class($value)])) {
			$this->classMap[get_class($value)]($this, $value);
		} else {
			throw new InvalidArgumentException('The writer cannot serialize values of type: ' . gettype($value));
		}
	}

	#[Override]
	public function text(string $content): bool {
		if ($this->currentElement === null) {
			return false;
		}

		$this->currentElement['value'][] = $content;

		return true;
	}

	private function decomposeArray(array $array): void {
		// Array format 2 with name, attributes, value
		if (array_key_exists('name', $array)) {
			// array with name, attributes and value keys
			$this->decomposeElement(
				$array['name'],
				$array['attributes'] ?? null,
				$array['value'] ?? null
			);
			return;
		}

		$isHref = array_key_exists('href', $array);
		// Array format 1 with key => value.
		foreach ($array as $name => $value) {
			if (!$isHref && is_int($name)) {
				// simple array with children
				$this->write($value);
			} elseif (is_array($value) && isset($value['attributes'])) {
				// array with attributes
				$this->decomposeElement($name, $value['attributes'], $value['value'] ?? null);
			} else {
				$this->writeElement($name, $value);
			}
		}
	}

	private function decomposeElement($name, $attributes, $value): void {
		$this->startElement($name);
		if ($attributes !== null) {
			$this->writeAttributes($attributes);
		}
		if ($value !== null) {
			$this->write($value);
		}
		$this->endElement();
	}

	#[Override]
	public function writeAttributes(array $attributes): void {
		foreach ($attributes as $name => $value) {
			$this->writeAttribute($name, $value);
		}
	}

	#[Override]
	public function writeAttribute($name, $value): bool {
		if ($this->currentElement === null) {
			return false;
		}

		$this->currentElement['attributes'][$name] = $value;

		return true;
	}

	#[Override]
	public function endElement(): bool {
		array_pop($this->elementStack);
		$this->currentElement = &$this->elementStack[count($this->elementStack) - 1];

		return true;
	}

	/**
	 * Returns an array structure representing the XML document in a format
	 * that is serializable by \Sabre\Xml\Writer into an XML document.
	 */
	public function getDocument(): array {
		return $this->document;
	}

	#[ReturnTypeWillChange]
	public function openUri($uri) {
		$this->unsupported(__METHOD__);
	}

	/**
	 * Throws a LogicException for unsupported XMLWriter methods.
	 */
	private function unsupported(string $method): never {
		throw new LogicException($method . ' is not supported by ' . __CLASS__);
	}

	#[Override]
	public function setIndent(bool $enable): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function setIndentString(string $indentation): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startComment(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endComment(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startAttribute($name): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endAttribute(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startAttributeNs($prefix, $name, $namespace): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeAttributeNs($prefix, $name, $namespace, $value = null): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startElementNs($prefix, $name, $namespace): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeElementNs($prefix, $name, $namespace, $content = null): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function fullEndElement(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startPi($target): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endPi(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writePi($target, $content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startCdata(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endCdata(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeCdata($content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeRaw($content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeComment($content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startDtd($qualifiedName, $publicId = null, $systemId = null): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endDtd(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeDtd($name, $publicId = null, $systemId = null, $content = null): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startDtdElement($qualifiedName): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endDtdElement(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeDtdElement($name, $content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startDtdAttlist($name): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endDtdAttlist(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeDtdAttlist($name, $content): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function startDtdEntity($name, $isParam = false): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function endDtdEntity(): bool {
		$this->unsupported(__METHOD__);
	}

	#[Override]
	public function writeDtdEntity($name, $content, $isParam = false, $publicId = null, $systemId = null, $notationData = null): bool {
		$this->unsupported(__METHOD__);
	}

	/**
	 * @return string the content of the writer in json_encoded format.
	 * @throws JsonException
	 */
	public function flush(bool $empty = true): string {
		return $this->outputMemory($empty);
	}

	/**
	 * Overrides XMLWriter::outputMemory.
	 *
	 * Instead of returning XML, this returns a json_encoded version of the
	 * document compatible with \Sabre\Xml\Writer once json_decoded.
	 *
	 * @throws JsonException
	 */
	#[Override]
	public function outputMemory(bool $flush = true): string {
		$result = $this->document;
		if ($flush) {
			$this->elementStack = [];
			$this->document = [];
		}

		return json_encode($result, JSON_THROW_ON_ERROR);
	}
}
