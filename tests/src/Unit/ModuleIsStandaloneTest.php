<?php

declare(strict_types=1);

namespace Drupal\Tests\saudi_id_validator\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards the module's independence from every other contributed module.
 *
 * This module is infrastructure: it is meant to drop into a customer portal, a
 * CRM, an HR system or a commerce site without being edited. That only stays
 * true while it knows about nothing but Drupal core, and a stray `use` added in
 * a hurry would end it silently — the code would still work in the one site it
 * was written for, and fail to install anywhere else.
 *
 * So the rule is checked rather than trusted. The test names no particular
 * module, because the point is not to ban one neighbour: it is to ban all of
 * them, including modules that do not exist yet.
 */
#[Group('saudi_id_validator')]
final class ModuleIsStandaloneTest extends UnitTestCase {

  /**
   * The Drupal namespaces this module is allowed to depend on.
   *
   * Core's own namespaces, plus the core modules its tests exercise. Anything
   * else means the module has grown a dependency it does not declare.
   */
  private const ALLOWED_NAMESPACES = [
    // Core itself.
    'Core',
    'Component',
    'KernelTests',
    'Tests',
    // Core modules, used only to give the constraint something to validate.
    'field',
    'entity_test',
    // The module's own code.
    'saudi_id_validator',
  ];

  /**
   * No file imports anything outside Drupal core and this module.
   */
  public function testNoDependencyOnOtherModules(): void {
    $offences = [];

    foreach ($this->sourceFiles() as $path) {
      $contents = file_get_contents($path);

      preg_match_all('/^use\s+Drupal\\\\(\w+)\\\\/m', $contents, $matches);

      foreach ($matches[1] as $namespace) {
        if (!in_array($namespace, self::ALLOWED_NAMESPACES, TRUE)) {
          $offences[] = sprintf('%s imports Drupal\\%s', basename($path), $namespace);
        }
      }
    }

    self::assertSame(
      [],
      array_values(array_unique($offences)),
      'saudi_id_validator must not depend on any module outside Drupal core.',
    );
  }

  /**
   * The module declares no dependency on another contributed module.
   */
  public function testInfoFileDeclaresNoModuleDependencies(): void {
    $info = file_get_contents($this->moduleRoot() . '/saudi_id_validator.info.yml');

    self::assertStringNotContainsString(
      'dependencies:',
      $info,
      'saudi_id_validator must install on its own, with nothing else required.',
    );
  }

  /**
   * Every PHP file the module ships, source and tests alike.
   *
   * @return array<int, string>
   *   Absolute paths.
   */
  private function sourceFiles(): array {
    $files = [];
    $directory = new \RecursiveDirectoryIterator($this->moduleRoot(), \FilesystemIterator::SKIP_DOTS);

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator($directory) as $file) {
      if (in_array($file->getExtension(), ['php', 'module', 'install', 'inc'], TRUE)) {
        $files[] = $file->getPathname();
      }
    }

    self::assertNotEmpty($files, 'The module source could not be found.');

    return $files;
  }

  /**
   * The module directory.
   *
   * @return string
   *   The absolute path, derived from this file rather than from the site
   *   layout, so the test travels with the module.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

}
