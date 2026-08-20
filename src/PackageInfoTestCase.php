<?php

declare(strict_types=1);

namespace Live627\ModTests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

abstract class PackageInfoTestCase extends TestCase
{
	/****************
	 * Public methods
	 ****************/

	#[DataProvider('providePackageInfoCases')]
	public function testPackageInfo(string $filename): void
	{
		$this->doTest($filename);
	}

	#[DataProvider('providePackageInfoCases')]
	public function testRootElement(string $filename): void
	{
		$xml = $this->doTest($filename);

		$this->assertSame('package-info', $xml->getName());

		$attributes = $xml->attributes();

		foreach ($attributes as $name => $value) {
			$this->assertContains($name, ['xmlns', 'smf'], \sprintf('Unexpected root attribute "%s".', $name));
		}
	}

	#[DataProvider('providePackageInfoCases')]
	public function testRequiredPackageInformation(string $filename): void
	{
		$xml = $this->doTest($filename);

		foreach (['id', 'name', 'type', 'version'] as $element) {
			$this->assertCount(1, $xml->{$element}, \sprintf('<%s> is required.', $element));
		}

		$this->assertContains((string) $xml->type, ['avatar', 'language', 'modification'], '<type> must be avatar, language, or modification.');

		$this->assertNotSame('', trim((string) $xml->id));
		$this->assertNotSame('', trim((string) $xml->name));
		$this->assertNotSame('', trim((string) $xml->version));
	}

	#[DataProvider('providePackageInfoCases')]
	public function testIdFormat(string $filename): void
	{
		$xml = $this->doTest($filename);

		$this->assertMatchesRegularExpression('/^[^:]+:[^:]+$/', (string) $xml->id, '<id> should use the username:package-name format.');
	}

	#[DataProvider('provideActionsCases')]
	public function testActions(string $action, string $filename): void
	{
		$xml = $this->doTest($filename);

		$this->assertGreaterThanOrEqual($action === 'upgrade' ? 0 : 1, \count($xml->{$action}), \sprintf('At least one <%s> element is required.', $action));

		foreach ($xml->{$action} as $element) {
			$this->assertAllowedAttributes($element, $action === 'upgrade'
					? ['for', 'from']
					: ['for'], );

			$this->assertAllowedChildren($element, [
				'readme',
				'code',
				'database',
				'hook',
				'modification',
				'create-dir',
				'create-file',
				'require-dir',
				'require-file',
				'move-dir',
				'move-file',
				'remove-dir',
				'remove-file',
				'redirect',
				'license',
				'credits',
				'requires',
				'error',
			]);

			$this->validateActionChildren($element, \dirname($filename));
		}
	}

	/***********************
	 * Public static methods
	 ***********************/

	abstract public static function providePackageInfoCases(): iterable;

	public static function provideActionsCases(): iterable
	{
		// PHPUnit annoyingly does not support multiple data providers anymore,
		// so we must manually create the cross product ourselves.
		foreach (static::providePackageInfoCases() as $case) {
			$filename = $case[0];

			yield ['install', $filename];

			yield ['upgrade', $filename];

			yield ['uninstall', $filename];
		}
	}

	/******************
	 * Internal methods
	 ******************/

	final protected function doTest(string $filename): \SimpleXMLElement
	{
		$this->assertFileExists($filename);

		$xml = simplexml_load_file($filename);

		if ($xml === false) {
			$this->fail('PACKAGE_INFO_FILE must contain valid XML.');
		}

		return $xml;
	}

	private function validateActionChildren(\SimpleXMLElement $action, string $package_directory): void
	{
		$this->validateReadmes($action, $package_directory);
		$this->validateCode($action, $package_directory);
		$this->validateDatabase($action, $package_directory);
		$this->validateHooks($action, $package_directory);
		$this->validateModifications($action, $package_directory);
		$this->validateCreateDirectories($action);
		$this->validateCreateFiles($action);
		$this->validateRequireDirectories($action, $package_directory);
		$this->validateRequireFiles($action, $package_directory);
		$this->validateMoveDirectories($action);
		$this->validateMoveFiles($action);
		$this->validateRemoveDirectories($action);
		$this->validateRemoveFiles($action);
		$this->validateRedirects($action, $package_directory);
		$this->validateCredits($action);
		$this->validateLicense($action, $package_directory);
		$this->validateRequires($action);
		$this->validateErrors($action);
	}

	private function validateReadmes(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->readme as $element) {
			$this->assertAllowedAttributes($element, ['lang', 'parsebbc', 'type']);

			$this->assertAttributeValues($element, 'type', ['inline', 'file']);
			$this->assertAttributeValues($element, 'parsebbc', ['true', 'false']);

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));
			}
		}
	}

	private function validateCode(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->code as $element) {
			$this->assertAllowedAttributes($element, ['type']);
			$this->assertAttributeValues($element, 'type', ['inline', 'file']);

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));
			}
		}
	}

	private function validateDatabase(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->database as $element) {
			$this->assertAllowedAttributes($element, ['type']);
			$this->assertAttributeValues($element, 'type', ['inline', 'file']);

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));
			}
		}
	}

	private function validateHooks(\SimpleXMLElement $action): void
	{
		foreach ($action->hook as $element) {
			$this->assertAllowedAttributes($element, ['hook', 'function', 'object', 'file', 'reverse']);

			$this->assertRequiredAttributes($element, ['hook', 'function']);

			$this->assertAttributeValues($element, 'object', ['true', 'false']);
			$this->assertAttributeValues($element, 'reverse', ['true', 'false']);
		}
	}

	private function validateModifications(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->modification as $element) {
			$this->assertAllowedAttributes($element, ['type', 'reverse', 'format']);

			$this->assertAttributeValues($element, 'type', ['inline', 'file']);
			$this->assertAttributeValues($element, 'reverse', ['true', 'false']);
			$this->assertAttributeValues($element, 'format', ['xml', 'diff', 'boardmod']);

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));

				// Turn on internal error handling
				$old = libxml_use_internal_errors(true);

				$doc = new \DOMDocument();
				$xml = file_get_contents($package_directory . '/' . trim((string) $element));
				$result = $doc->loadXML($xml);

				if ($result === false) {
					$errors = libxml_get_errors();

					foreach ($errors as $error) {
						$this->assertEmpty($error, $this->display_xml_error($error, $xml));
					}
				}

				// Clear and restore original error setting
				libxml_clear_errors();
				libxml_use_internal_errors($old);
			}
		}
	}

	private function display_xml_error($error, $xml)
	{
		$return  = $xml[$error->line - 1] . "\n";
		$return .= str_repeat('-', $error->column) . "^\n";

		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning {$error->code}: ";
				break;

			case LIBXML_ERR_ERROR:
				$return .= "Error {$error->code}: ";
				break;

			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error {$error->code}: ";
				break;
		}

		$return .= trim($error->message) .
				   "\n  Line: {$error->line}" .
				   "\n  Column: {$error->column}";

		if ($error->file) {
			$return .= "\n  File: {$error->file}";
		}

		return "{$return}\n\n--------------------------------------------\n\n";
	}

	private function validateCreateDirectories(\SimpleXMLElement $action): void
	{
		foreach ($action->{'create-dir'} as $element) {
			$this->assertAllowedAttributes($element, ['name', 'destination' ,  'error']);
			$this->assertRequiredAttributes($element, ['name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateCreateFiles(\SimpleXMLElement $action): void
	{
		foreach ($action->{'create-file'} as $element) {
			$this->assertAllowedAttributes($element, ['name', 'destination', 'error']);
			$this->assertRequiredAttributes($element, ['name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateRequireDirectories(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->{'require-dir'} as $element) {
			$this->assertAllowedAttributes($element, ['from', 'name', 'destination', 'error']);

			$this->assertRequiredAttributes($element, ['name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);

			$source = (string) ($element['from'] ?: $element['name']);

			$this->assertDirectoryExists($package_directory . '/' . $source, '<require-dir> references a directory that does not exist.');
		}
	}

	private function validateRequireFiles(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->{'require-file'} as $element) {
			$this->assertAllowedAttributes($element, ['from', 'name', 'destination', 'error']);

			$this->assertRequiredAttributes($element, ['name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);

			$source = (string) ($element['from'] ?: $element['name']);

			$this->assertFileExists($package_directory . '/' . $source, '<require-file> references a file that does not exist.');
		}
	}

	private function validateMoveDirectories(\SimpleXMLElement $action): void
	{
		foreach ($action->{'move-dir'} as $element) {
			$this->assertAllowedAttributes($element, ['from', 'name', 'destination', 'error']);

			$this->assertRequiredAttributes($element, ['from', 'name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateMoveFiles(\SimpleXMLElement $action): void
	{
		foreach ($action->{'move-file'} as $element) {
			$this->assertAllowedAttributes($element, ['from', 'name', 'destination', 'error']);

			$this->assertRequiredAttributes($element, ['from', 'name', 'destination']);

			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateRemoveDirectories(\SimpleXMLElement $action): void
	{
		foreach ($action->{'remove-dir'} as $element) {
			$this->assertAllowedAttributes($element, ['name', 'error']);
			$this->assertRequiredAttributes($element, ['name']);
			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateRemoveFiles(\SimpleXMLElement $action): void
	{
		foreach ($action->{'remove-file'} as $element) {
			$this->assertAllowedAttributes($element, ['name', 'error']);
			$this->assertRequiredAttributes($element, ['name']);
			$this->assertAttributeValues($element, 'error', ['fail', 'ignore']);
		}
	}

	private function validateRedirects(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->redirect as $element) {
			$this->assertAllowedAttributes($element, ['url', 'type', 'parsebbc', 'timeout']);

			$this->assertRequiredAttributes($element, ['url']);

			$this->assertAttributeValues($element, 'type', ['inline', 'file']);
			$this->assertAttributeValues($element, 'parsebbc', ['true', 'false']);

			if (isset($element['timeout'])) {
				$this->assertMatchesRegularExpression('/^\d+$/', (string) $element['timeout'], '<redirect timeout> must be an integer.');
			}

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));
			}
		}
	}

	private function validateCredits(\SimpleXMLElement $action): void
	{
		foreach ($action->credits as $element) {
			$this->assertAllowedAttributes($element, ['url', 'license', 'licenseurl', 'copyright']);

			$this->assertRequiredAttributes($element, ['url', 'license', 'licenseurl', 'copyright']);
		}
	}

	private function validateLicense(\SimpleXMLElement $action, string $package_directory): void
	{
		foreach ($action->license as $element) {
			$this->assertAllowedAttributes($element, ['lang', 'parsebbc', 'type']);

			$this->assertAttributeValues($element, 'type', ['inline', 'file']);
			$this->assertAttributeValues($element, 'parsebbc', ['true', 'false']);

			if ((string) ($element['type'] ?? 'file') === 'file') {
				$this->assertFileExists($package_directory . '/' . trim((string) $element));
			}
		}
	}

	private function validateRequires(\SimpleXMLElement $action): void
	{
		foreach ($action->requires as $element) {
			$this->assertAllowedAttributes($element, ['id', 'version']);
			$this->assertRequiredAttributes($element, ['id']);
			$this->assertMatchesRegularExpression('/^[^:]+:[^:]+$/', (string) $element['id'], '<requires id="username:package-name" /> must follow the specified format.');
		}
	}

	private function validateErrors(\SimpleXMLElement $action): void
	{
		foreach ($action->error as $element) {
			$this->assertEmpty($element->attributes(), '<error> must not havve any attributes.');
		}
	}

	private function assertAllowedAttributes(\SimpleXMLElement $element, array $allowed): void
	{
		foreach ($element->attributes() as $name => $value) {
			$this->assertContains($name, $allowed, \sprintf('Unexpected attribute "%s" on <%s>.', $name, $element->getName()));
		}
	}

	private function assertRequiredAttributes(\SimpleXMLElement $element, array $required): void
	{
		foreach ($required as $attribute) {
			$this->assertTrue(isset($element[$attribute]), \sprintf('Attribute "%s" is required on <%s>.', $attribute, $element->getName()));

			$this->assertNotSame('', trim((string) $element[$attribute]), \sprintf('Attribute "%s" on <%s> cannot be empty.', $attribute, $element->getName()));
		}
	}

	private function assertAttributeValues(\SimpleXMLElement $element, string $attribute, array $allowed): void
	{
		if (!isset($element[$attribute])) {
			return;
		}

		$this->assertContains((string) $element[$attribute], $allowed, \sprintf('Invalid value "%s" for attribute "%s" on <%s>.', $element[$attribute], $attribute, $element->getName()));
	}

	private function assertAllowedChildren(\SimpleXMLElement $element, array $allowed): void
	{
		foreach ($element->children() as $child) {
			$this->assertContains($child->getName(), $allowed, \sprintf('Unexpected <%s> element inside <%s>.', $child->getName(), $element->getName()));
		}
	}
}
