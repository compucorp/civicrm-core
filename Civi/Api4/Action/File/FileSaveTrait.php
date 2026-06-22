<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Action\File;

trait FileSaveTrait {

  /**
   * @inheritDoc
   */
  protected function write(array $items) {
    foreach ($items as &$file) {
      // In update mode, the uri cannot be changed.
      if (!empty($file['id']) && isset($file['uri'])) {
        $existingUri = \CRM_Core_DAO_File::getDbVal('uri', $file['id']);
        if ($existingUri !== $file['uri']) {
          throw new \CRM_Core_Exception("Uri of existing file cannot be changed.");
        }
        unset($file['uri']);
      }
      // In create mode, uri cannot be set.
      if (isset($file['uri'])) {
        throw new \CRM_Core_Exception("Setting file URI is not permitted. Use file_name instead.");
      }
      // Security: Validate existing URI before writing content
      if (!empty($file['content']) && !empty($file['id'])) {
        $existingUri = \CRM_Core_DAO_File::getDbVal('uri', $file['id']);
        $this->validateUri($existingUri);
      }
    }
    return \CRM_Core_BAO_File::writeRecords($items);
  }

  /**
   * Validate that a URI/filename doesn't contain directory separators or path traversal.
   *
   * @param string $uri
   * @throws \CRM_Core_Exception
   */
  private function validateUri(string $uri): void {
    if ($uri !== basename($uri)) {
      throw new \CRM_Core_Exception('Invalid URI: must not contain directory separators or path traversal sequences');
    }
  }

}
