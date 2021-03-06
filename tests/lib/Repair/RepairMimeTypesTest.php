<?php
/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * Copyright (c) 2014-2015 Olivier Paroz owncloud@oparoz.com
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
namespace Test\Repair;

use OC\Files\Storage\Temporary;
use OCP\Files\IMimeTypeLoader;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Tests for the converting of legacy storages to home storages.
 *
 * @group DB
 *
 * @see \OC\Repair\RepairMimeTypes
 */
class RepairMimeTypesTest extends \Test\TestCase {

	/** @var IRepairStep */
	private $repair;

	/** @var Temporary */
	private $storage;

	/** @var IMimeTypeLoader */
	private $mimetypeLoader;

	protected function setUp() {
		parent::setUp();

		$this->savedMimetypeLoader = \OC::$server->getMimeTypeLoader();
		$this->mimetypeLoader = \OC::$server->getMimeTypeLoader();

		/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject $config */
		$config = $this->getMockBuilder('OCP\IConfig')
			->disableOriginalConstructor()
			->getMock();
		$config->expects($this->any())
			->method('getSystemValue')
			->with('version')
			->will($this->returnValue('11.0.0.0'));

		$this->storage = new \OC\Files\Storage\Temporary([]);

		$this->repair = new \OC\Repair\RepairMimeTypes($config);
	}

	protected function tearDown() {
		$this->storage->getCache()->clear();
		$sql = 'DELETE FROM `*PREFIX*storages` WHERE `id` = ?';
		\OC_DB::executeAudited($sql, [$this->storage->getId()]);
		$this->clearMimeTypes();

		parent::tearDown();
	}

	private function clearMimeTypes() {
		$sql = 'DELETE FROM `*PREFIX*mimetypes`';
		\OC_DB::executeAudited($sql);
		$this->mimetypeLoader->reset();
	}

	private function addEntries($entries) {
		// create files for the different extensions, this
		// will also automatically create the corresponding mime types
		foreach ($entries as $entry) {
			$this->storage->getCache()->put(
				$entry[0],
				[
					'size' => 0,
					'mtime' => 0,
					'mimetype' => $entry[1]
				]
			);
		}

	}

	private function checkEntries($entries) {
		foreach ($entries as $entry) {
			$data = $this->storage->getCache()->get($entry[0]);
			$this->assertEquals($entry[1], $data['mimetype']);
		}
	}

	private function renameMimeTypes($currentMimeTypes, $fixedMimeTypes) {
		$this->addEntries($currentMimeTypes);

		/** @var IOutput | \PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->getMockBuilder('\OCP\Migration\IOutput')
			->disableOriginalConstructor()
			->getMock();

		$this->repair->run($outputMock);

		// force mimetype reload
		$this->mimetypeLoader->reset();

		$this->checkEntries($fixedMimeTypes);
	}

	/**
	 * Test renaming the richdocuments additional office mime types
	 */
	public function testRenameWindowsProgramTypes() {
		$currentMimeTypes = [
			['test.htaccess', 'application/octet-stream'],
			['.htaccess', 'application/octet-stream'],
			['test.bat', 'application/octet-stream'],
			['test.cmd', 'application/octet-stream'],
		];

		$fixedMimeTypes = [
			['test.htaccess', 'text/plain'],
			['.htaccess', 'text/plain'],
			['test.bat', 'application/x-msdos-program'],
			['test.cmd', 'application/cmd'],
		];

		$this->renameMimeTypes($currentMimeTypes, $fixedMimeTypes);
	}

	/**
	 * Test that nothing happens and no error happens when all mimetypes are
	 * already correct and no old ones exist..
	 */
	public function testDoNothingWhenOnlyNewFiles() {
		$currentMimeTypes = [
			['test.doc', 'application/msword'],
			['test.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
			['test.xls', 'application/vnd.ms-excel'],
			['test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
			['test.ppt', 'application/vnd.ms-powerpoint'],
			['test.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
			['test.apk', 'application/vnd.android.package-archive'],
			['test.ttf', 'application/font-sfnt'],
			['test.otf', 'application/font-sfnt'],
			['test.pfb', 'application/x-font'],
			['test.eps', 'application/postscript'],
			['test.ps', 'application/postscript'],
			['test.arw', 'image/x-dcraw'],
			['test.cr2', 'image/x-dcraw'],
			['test.dcr', 'image/x-dcraw'],
			['test.dng', 'image/x-dcraw'],
			['test.erf', 'image/x-dcraw'],
			['test.iiq', 'image/x-dcraw'],
			['test.k25', 'image/x-dcraw'],
			['test.kdc', 'image/x-dcraw'],
			['test.mef', 'image/x-dcraw'],
			['test.nef', 'image/x-dcraw'],
			['test.orf', 'image/x-dcraw'],
			['test.pef', 'image/x-dcraw'],
			['test.raf', 'image/x-dcraw'],
			['test.rw2', 'image/x-dcraw'],
			['test.srf', 'image/x-dcraw'],
			['test.sr2', 'image/x-dcraw'],
			['test.xrf', 'image/x-dcraw'],
			['test.DNG', 'image/x-dcraw'],
			['test.jps', 'image/jpeg'],
			['test.MPO', 'image/jpeg'],
			['test.conf', 'text/plain'],
			['test.cnf', 'text/plain'],
			['test.yaml', 'application/yaml'],
			['test.yml', 'application/yaml'],
			['test.java', 'text/x-java-source'],
			['test.class', 'application/java'],
			['test.hpp', 'text/x-h'],
			['test.rss', 'application/rss+xml'],
			['test.rtf', 'text/rtf'],
			['test.lwp', 'application/vnd.lotus-wordpro'],
			['test.one', 'application/msonenote'],
			['test.vsd', 'application/vnd.visio'],
			['test.wpd', 'application/vnd.wordperfect'],
			['test.htaccess', 'text/plain'],
			['.htaccess', 'text/plain'],
			['test.bat', 'application/x-msdos-program'],
			['test.cmd', 'application/cmd'],
		];

		$fixedMimeTypes = [
			['test.doc', 'application/msword'],
			['test.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
			['test.xls', 'application/vnd.ms-excel'],
			['test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
			['test.ppt', 'application/vnd.ms-powerpoint'],
			['test.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
			['test.apk', 'application/vnd.android.package-archive'],
			['test.ttf', 'application/font-sfnt'],
			['test.otf', 'application/font-sfnt'],
			['test.pfb', 'application/x-font'],
			['test.eps', 'application/postscript'],
			['test.ps', 'application/postscript'],
			['test.arw', 'image/x-dcraw'],
			['test.cr2', 'image/x-dcraw'],
			['test.dcr', 'image/x-dcraw'],
			['test.dng', 'image/x-dcraw'],
			['test.erf', 'image/x-dcraw'],
			['test.iiq', 'image/x-dcraw'],
			['test.k25', 'image/x-dcraw'],
			['test.kdc', 'image/x-dcraw'],
			['test.mef', 'image/x-dcraw'],
			['test.nef', 'image/x-dcraw'],
			['test.orf', 'image/x-dcraw'],
			['test.pef', 'image/x-dcraw'],
			['test.raf', 'image/x-dcraw'],
			['test.rw2', 'image/x-dcraw'],
			['test.srf', 'image/x-dcraw'],
			['test.sr2', 'image/x-dcraw'],
			['test.xrf', 'image/x-dcraw'],
			['test.DNG', 'image/x-dcraw'],
			['test.jps', 'image/jpeg'],
			['test.MPO', 'image/jpeg'],
			['test.conf', 'text/plain'],
			['test.cnf', 'text/plain'],
			['test.yaml', 'application/yaml'],
			['test.yml', 'application/yaml'],
			['test.java', 'text/x-java-source'],
			['test.class', 'application/java'],
			['test.hpp', 'text/x-h'],
			['test.rss', 'application/rss+xml'],
			['test.rtf', 'text/rtf'],
			['test.lwp', 'application/vnd.lotus-wordpro'],
			['test.one', 'application/msonenote'],
			['test.vsd', 'application/vnd.visio'],
			['test.wpd', 'application/vnd.wordperfect'],
			['test.htaccess', 'text/plain'],
			['.htaccess', 'text/plain'],
			['test.bat', 'application/x-msdos-program'],
			['test.cmd', 'application/cmd'],
		];

		$this->renameMimeTypes($currentMimeTypes, $fixedMimeTypes);
	}

	/**
	 * Test that mime type renaming does not affect folders
	 */
	public function testDoNotChangeFolderMimeType() {
		$currentMimeTypes = [
			['test.conf', 'httpd/unix-directory'],
			['test.cnf', 'httpd/unix-directory'],
		];

		$fixedMimeTypes = [
			['test.conf', 'httpd/unix-directory'],
			['test.cnf', 'httpd/unix-directory'],
		];

		$this->renameMimeTypes($currentMimeTypes, $fixedMimeTypes);
	}
}

