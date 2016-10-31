<?php
/**
 * Windows Oss based file backend.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup FileBackend
 * @author Aaron Schulz
 * @author Markus Glaser
 * @author Robert Vogel
 * @author Thai Phan
 */
use MediaWiki\Logger\LoggerFactory;
use OSS\Core\OssException;


/**
 * @brief Class for a Windows Oss based file backend
 *
 * This requires the WindowOssSDK extension in order to work. Information on
 * how to install and set up this extension are all located at
 * http://www.mediawiki.org/wiki/Extension:WindowsOssSDK.
 *
 * @ingroup FileBackend
 * @since 1.22
 */
class OssFileBackend extends FileBackendStore {
	/** @var IBlob */
	private $ossClient;
	/** @var string */
	private $connectionString;
	/**
	 * @see FileBackendStore::__construct()
	 * Additional $config params include:
	 *   - OssAccount : Aliyun Oss storage account
	 *   - OssKey     : Aliyun Oss storage account key
	 */
	public function __construct( array $config ) {
		parent::__construct( $config );
		// Generate connection string to Windows Oss storage account
		global $wgOssEndpoint;
		$accessKeyId = Confidential::$aliyunKey;
        $accessKeySecret = Confidential::$aliyunSecret;
        $endpoint = $wgOssEndpoint;
        $this->logger = $logger = LoggerFactory::getInstance( 'filesystem' );
        try {
            $this->ossClient = new Oss\OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (OssException $e) {
            $this->logger->warn($e);
        }
	}
	/**
	 * @see FileBackendStore::resolveContainerName()
	 * @return string|null
	 */
	protected function resolveContainerName( $container ) {
		$container = strtolower( $container );
		// $container = preg_replace( '#[^a-z0-9\-]#', '', $container );
		// $container = preg_replace( '#^-#', '', $container );
		// $container = preg_replace( '#-$#', '', $container );
		// $container = preg_replace( '#-{2,}#', '-', $container );
		$container = preg_replace( '#.*temp$#', 'huiji-temp', $container);
		$container = preg_replace( '#.*public$#', 'huiji-public', $container);
		$container = preg_replace( '#.*deleted$#', 'huiji-deleted', $container);
		$container = preg_replace( '#.*thumb$#', 'huiji-thumb', $container);
		return $container;
	}
	/**
	 * @see FileBackendStore::resolveContainerPath()
	 * @return null
	 */
	protected function resolveContainerPath( $container, $relStoragePath ) {
		if ( !mb_check_encoding( $relStoragePath, 'UTF-8' ) ) {
			return null;
		} elseif ( strlen( urlencode( $relStoragePath ) ) > 1024 ) {
			return null;
		}
		return $relStoragePath;
	}
	/**
	 * @see FileBackendStore::isPathUsableInternal()
	 * @return bool
	 */
	public function isPathUsableInternal( $storagePath ) {
		list( $container, $rel ) = $this->resolveStoragePathReal( $storagePath );
		if ( $rel === null ) {
			return false; // invalid
		}
		try {
			return $this->ossClient->doesBucketExist( $container );
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					break;
				default: // some other exception?
					$this->handleException( $e, null, __METHOD__, array( 'path' => $storagePath ) );
			}
			return false;
		}
	}
	/**
	 * @see FileBackendStore::doCreateInternal()
	 * @return Status
	 */
	protected function doCreateInternal( array $params ) {
		$status = Status::newGood();
		list( $dstCont, $dstRel ) = $this->resolveStoragePathReal( $params['dst'] );
		if ( $dstRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['dst'] );
			return $status;
		}
		// (a) Get a SHA-1 hash of the object
		$sha1Hash = Wikimedia\base_convert( sha1( $params['content'] ), 16, 36, 31 );
		// (b) Actually create the object
		try {
			$options = [];
			$options['x-oss-meta-sha1base36'] = $sha1Hash;
			$headers[OSS\OssClient::OSS_HEADERS] = $options;
			$this->ossClient->putObject( $dstCont, $dstRel, (string)$params['content'], $headers );
			// $this->modifyMetaForObject($dstCont, $dstRel, $options);
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					$status->fatal( 'backend-fail-create', $params['dst'] );
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
			
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doStoreInternal()
	 * @return Status
	 */
	protected function doStoreInternal( array $params ) {
		$status = Status::newGood();
		list( $dstCont, $dstRel ) = $this->resolveStoragePathReal( $params['dst'] );
		if ( $dstRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['dst'] );
			return $status;
		}
		// (a) Get a SHA-1 hash of the object
		wfSuppressWarnings();
		$sha1Hash = sha1_file( $params['src'] );
		wfRestoreWarnings();
		if ( $sha1Hash === false ) { // source doesn't exist?
			$status->fatal( 'backend-fail-store', $params['src'], $params['dst'] );
			return $status;
		}
		$sha1Hash = Wikimedia\base_convert( $sha1Hash, 16, 36, 31 );
		// (b) Actually store the object
		try {
			$options['x-oss-meta-sha1base36'] = $sha1Hash;
			$headers[OSS\OssClient::OSS_HEADERS] = $options;
			wfSuppressWarnings();
			$fp = fopen( $params['src'], 'rb' );
			$content = stream_get_contents($fp);
			wfRestoreWarnings();
			
			$this->ossClient->putObject( $dstCont, $dstRel, $content, $headers );
			// $this->modifyMetaForObject($dstCont, $dstRel, $options);
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					$status->fatal( 'backend-fail-store', $params['src'], $params['dst'] );
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doCopyInternal()
	 * @return Status
	 */
	protected function doCopyInternal( array $params ) {
		$status = Status::newGood();
		list( $srcCont, $srcRel ) = $this->resolveStoragePathReal( $params['src'] );
		if ( $srcRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['src'] );
			return $status;
		}
		list( $dstCont, $dstRel ) = $this->resolveStoragePathReal( $params['dst'] );
		if ( $dstRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['dst'] );
			return $status;
		}
		try {
			$this->ossClient->copyObject( $srcCont, $srcRel, $dstCont, $dstRel );
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					if ( empty( $params['ignoreMissingSource'] ) ) {
						$status->fatal( 'backend-fail-copy', $params['src'], $params['dst'] );
					}
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doDeleteInternal()
	 * @return Status
	 */
	protected function doDeleteInternal( array $params ) {
		$status = Status::newGood();
		list( $srcCont, $srcRel ) = $this->resolveStoragePathReal( $params['src'] );
		if ( $srcRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['src'] );
			return $status;
		}
		try {
			$this->ossClient->deleteObject( $srcCont, $srcRel );
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					if ( empty( $params['ignoreMissingSource'] ) ) {
						$status->fatal( 'backend-fail-delete', $params['src'] );
					}
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doPrepareInternal()
	 * @return Status
	 */
	protected function doPrepareInternal( $fullCont, $dir, array $params ) {
		$status = Status::newGood();
		try {
			$cont = $this->resolveContainerName( $fullCont );
			if ( !empty( $params['noAccess'] ) ) {
				$this->ossClient->createBucket( $cont, OSS\OssClient::OSS_ACL_TYPE_PRIVATE);
				// Make container private to end-users...
				$status->merge( $this->doSecureInternal( $cont, $dir, $params ) );
			} else {
				$this->ossClient->createBucket( $cont, OSS\OssClient::OSS_ACL_TYPE_PUBLIC_READ);
				// Make container public to end-users...
				$status->merge( $this->doPublishInternal( $cont, $dir, $params ) );
			}
		} catch ( OssException $e ) {
			switch ( $e->getCode() ) {
				case 404:
				case 409: // container already exists
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doSecureInternal()
	 * @return Status
	 */
	protected function doSecureInternal( $fullCont, $dir, array $params ) {
		$status = Status::newGood();
		if ( empty( $params['noAccess'] ) ) {
			return $status; // nothing to do
		}
		// Restrict container from end-users...
		try {
			$acl = OSS\OssClient::OSS_ACL_TYPE_PRIVATE;
			$this->ossClient->putBucketAcl( $fullCont, $acl );
		} catch ( OssException $e ) {
			$this->handleException( $e, $status, __METHOD__, $params );
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doPublishInternal()
	 * @return Status
	 */
	protected function doPublishInternal( $fullCont, $dir, array $params ) {
		$status = Status::newGood();
		// Unrestrict container from end-users...
		try {
			$acl = OSS\OssClient::OSS_ACL_TYPE_PUBLIC_READ;
			$this->ossClient->putBucketAcl( $fullCont, $acl );
		} catch ( OssException $e ) {
			$this->handleException( $e, $status, __METHOD__, $params );
		}
		return $status;
	}
	/**
	 * 修改Object Meta
	 * 利用copyObject接口的特性：当目的object和源object完全相同时，表示修改object的meta信息
	 *
	 * @param OssClient $ossClient OssClient实例
	 * @param string $bucket 存储空间名称
	 * @return null
	 */
	private function modifyMetaForObject( $bucket, $obj, $options )
	{
		$status = Status::newGood();
	    $fromBucket = $bucket;
	    $fromObject = $obj;
	    $toBucket = $bucket;
	    $toObject = $fromObject;
	    $copyOptions = array(
	        OSS\OssClient::OSS_HEADERS => $options
	    );
	    try {
	        $this->ossClient->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $copyOptions);
	    } catch (OssException $e) {
	        $this->handleException( $e, $status, __METHOD__, $copyOptions );
	    }
	}
	/**
	 * @see FileBackendStore::doFileExists()
	 * @return array|bool|null
	 */
	protected function doGetFileStat( array $params ) {
		list( $srcCont, $srcRel ) = $this->resolveStoragePathReal( $params['src'] );
		if ( $srcRel === null ) {
			return false; // invalid storage path
		}
		try {
			
			$metadata = $this->ossClient->getObjectMeta($srcCont, $srcRel);
			// @TODO: pass $metadata to addMissingMetadata() to avoid round-trips
			$this->addMissingMetadata( $srcCont, $srcRel, $params['src'], $metadata );
			$timestamp = $metadata[strtolower(OSS\OssClient::OSS_LAST_MODIFIED)];
			$size = $metadata[strtolower(OSS\OssClient::OSS_CONTENT_LENGTH)];
			$sha1 = $metadata['x-oss-meta-sha1base36'];
			$stat = array(
				'mtime' => wfTimestamp( TS_MW, $timestamp ),
				'size'  => $size,
				'sha1'  => $sha1
			);
			$this->logger->debug("doGetFileStat is returning ", ['metadata'=>$metadata, 'src' => $params, 'stat' => $stat]);
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					$stat = false;
					break;
				default: // some other exception?
					$stat = null;
					$this->handleException( $e, null, __METHOD__, $params );
			}
		}
		return $stat;
	}
	/**
	 * Fill in any missing blob metadata and save it to Oss
	 *
	 * @param $srcCont string Container name
	 * @param $srcRel string Blob name
	 * @param $path string Storage path to object
	 * @return bool Success
	 * @throws Exception Oss Storage service exception
	 */
	protected function addMissingMetadata( $srcCont, $srcRel, $path, $metadata ) {
		//$metadata = $this->ossClient->getObjectMeta($srcCont, $srcRel);
		if ( isset( $metadata['x-oss-meta-sha1base36'] ) ) {
			return true; // nothing to do
		}
		wfProfileIn( __METHOD__ );
		trigger_error( "$path was not stored with SHA-1 metadata.", E_USER_WARNING );
		$status = Status::newGood();
		$scopeLockS = $this->getScopedFileLocks( array( $path ), LockManager::LOCK_UW, $status );
		if ( $status->isOK() ) {
			$tmpFile = $this->getLocalCopy( array( 'src' => $path, 'latest' => 1 ) );
			if ( $tmpFile ) {
				$hash = $tmpFile->getSha1Base36();
				if ( $hash !== false ) {
					$this->modifyMetaForObject( $srcCont, $srcRel, array( 'x-oss-meta-sha1base36' => $hash ) );
					wfProfileOut( __METHOD__ );
					return true; // success
				}
			}
		}
		trigger_error( "Unable to set SHA-1 metadata for $path", E_USER_WARNING );
		// Set the SHA-1 metadata to 0 (setting to false doesn't seem to work)
		// @TODO: don't permanently set the object metadata here, just make sure this PHP
		//        request doesn't keep trying to download the file again and again.
		$this->modifyMetaForObject( $srcCont, $srcRel, array( 'x-oss-meta-sha1base36' => 0 ) );
		wfProfileOut( __METHOD__ );
		return false; // failed
	}
	/**
	 * @see FileBackendStore::doDirectoryExists()
	 * @return bool|null
	 */
	protected function doDirectoryExists( $fullCont, $dir, array $params ) {
		try {
			$prefix = ( $dir == '' ) ? null : "{$dir}/";
			$delimiter = '/';
			$nextMarker = '';
			$maxKeys = 1;
			$options = array(
				'delimiter' => $delimiter,
				'prefix' => $prefix,
				'max-keys' => $maxKeys,
				'marker' => $nextMarker,
			);
			$blobs = $this->ossClient->listObjects( $fullCont, $options );
			return ( count( $blobs->getObjectList() ) > 0 || count( $blobs->getPrefixList() ) > 0 );
		} catch ( OssException $e ) {
			switch ( $e->getHTTPStatus() ) {
				case 404:
					return false;
				default: // some other exception?
					$this->handleException( $e, null, __METHOD__,
						array( 'cont' => $fullCont, 'dir' => $dir ) );
					return null;
			}
		}
	}
	/**
	 * @see FileBackendStore::getDirectoryListInternal()
	 * @return OssFileBackendDirList
	 */
	public function getDirectoryListInternal( $fullCont, $dir, array $params ) {
		return new OssFileBackendDirList( $this, $fullCont, $dir, $params );
	}
	/**
	 * @see FileBackendStore::getFileListInternal()
	 * @return OssFileBackendFileList
	 */
	public function getFileListInternal( $fullCont, $dir, array $params ) {
		return new OssFileBackendFileList( $this, $fullCont, $dir, $params );
	}
	public function getDirListPageInternal( $fullCont, $dir, &$after, $limit, array $params ) {
		$dirs = array();
		if ( $after === INF ) {
			return $dirs;
		}
		wfProfileIn( __METHOD__ . '-' . $this->name );
		try {
			$prefix = ( $dir == '' ) ? null : "{$dir}/";
			$delimiter = '/';
			// $nextMarker = '';
			$maxKeys = $limit;
			$options = array(
				'delimiter' => $delimiter,
				'prefix' => $prefix,
				'max-keys' => $maxKeys,
				'marker' => $after,
			);
			$objects = array();
			if ( !empty( $params['topOnly'] ) ) {
				// Blobs are listed in alphabetical order in the response body, with
				// upper-case letters listed first.
				// @TODO: use prefix+delimeter here
				$blobs = $this->ossClient->listObjects( $fullCont, $options )->getObjectList();
				foreach ( $blobs as $blob ) {
					$name = $blob->getKey();
					if ( $prefix === null ) {
						if ( !preg_match( '#\/#', $name ) ) {
							continue;
						}
						$dirray = preg_split( '#\/#', $name );
						$name = $dirray[0] . '/';
						$objects[] = $name;
					}
					$name = preg_replace( '#[^/]*$#', '', $name );
					if ( preg_match( '#^' . $prefix . '(\/|)$#', $name ) ) continue;
					$dirray = preg_split( '#\/#', $name );
					$elements = count( preg_split( '#\/#', $prefix ) );
					$name = '';
					for ( $i = 0; $i < $elements; $i++ ) {
						$name = $name . $dirray[$i] . '/';
					}
					$objects[] =  $name;
				}
				$dirs = array_unique( $objects );
			} else {
				// Get directory from last item of prior page
				$lastDir = $this->getParentDir( $after ); // must be first page
				$blobs = $this->ossClient->listObjects( $fullCont, $options )->getObjectList();
				// Generate an array of blob names
				foreach ( $blobs as $blob ) {
					array_push( $objects, $blob->getKey() );
				}
				foreach ( $objects as $object ) { // files
					$objectDir = $this->getParentDir( $object ); // directory of object
					if ( $objectDir !== false && $objectDir !== $dir ) {
						if ( strcmp( $objectDir, $lastDir ) > 0 ) {
							$pDir = $objectDir;
							do { // add dir and all its parent dirs
								$dirs[] = "{$pDir}/";
								$pDir = $this->getParentDir( $pDir );
							} while ( $pDir !== false // sanity
								&& strcmp( $pDir, $lastDir ) > 0 // not done already
								&& strlen( $pDir ) > strlen( $dir ) // within $dir
							);
						}
						$lastDir = $objectDir;
					}
				}
			}
			if ( count( $objects ) < $limit ) {
				$after = INF; // avoid a second RTT
			} else {
				$after = end( $objects ); // update last item
			}
		} catch ( OssException $e ) {
			switch ( $e->getCode() ) {
				case 404:
					break;
				default: // some other exception?
					$this->handleException( $e, null, __METHOD__,
						array( 'cont' => $fullCont, 'dir' => $dir ) );
			}
		}
		wfProfileOut( __METHOD__ . '-' . $this->name );
		return $dirs;
	}
	protected function getParentDir( $path ) {
		return ( strpos( $path, '/' ) !== false ) ? dirname( $path ) : false;
	}
	/**
	 * Do not call this function outside of OssFileBackendFileList
	 *
	 * @return array List of relative paths of files under $dir
	 */
	public function getFileListPageInternal( $fullCont, $dir, &$after, $limit, array $params ) {
		$files = array();
		if ( $after === INF ) {
			return $files;
		}
		wfProfileIn( __METHOD__ . '-' . $this->name );
		try {
			$prefix = ( $dir == '' ) ? null : "{$dir}/";
			$delimiter = '/';
			// $nextMarker = '';
			$maxKeys = $limit;
			$options = array(
				'delimiter' => $delimiter,
				'prefix' => $prefix,
				'max-keys' => $maxKeys,
				'marker' => $after,
			);
			$objects = array();
			if ( !empty( $params['topOnly'] ) ) {
				$options->setDelimiter( '/' );
				$blobs = $this->ossClient->listObjects( $fullCont, $options )->getObjectList();
				foreach ( $blobs as $blob ) {
					array_push( $objects, $blob->getKey() );
				}
				foreach ( $objects as $object ) {
					if ( substr( $object, -1 ) !== '/' ) {
						$files[] = $object;
					}
				}
			} else {
				$blobs = $this->ossClient->listObjects( $fullCont, $options )->getObjectList();
				foreach ( $blobs as $blob ) {
					array_push( $objects, $blob->getKeys() );
				}
				$files = $objects;
			}
			if ( count( $objects ) < $limit ) {
				$after = INF;
			} else {
				$after = end( $objects );
			}
		} catch ( OssException $e ) {
			switch ( $e->getCode() ) {
				case 404:
					break;
				default: // some other exception?
					$this->handleException( $e, null, __METHOD__,
						array( 'cont' => $fullCont, 'dir' => $dir ) );
			}
		}
		wfProfileOut( __METHOD__ . '-' . $this->name );
		return $files;
	}
	/**
	 * @see FileBackendStore::doGetFileSha1base36()
	 * @return bool
	 */
	protected function doGetFileSha1base36( array $params ) {
		$stat = $this->getFileStat( $params );
		if ( $stat ) {
			return $stat['sha1'];
		} else {
			return false;
		}
	}
	/**
	 * @see FileBackendStore::doStreamFile()
	 * @return Status
	 */
	protected function doStreamFile( array $params ) {
		$status = Status::newGood();
		list( $srcCont, $srcRel ) = $this->resolveStoragePathReal( $params['src'] );
		if ( $srcRel === null ) {
			$status->fatal( 'backend-fail-invalidpath', $params['src'] );
		}
		try {
			$contents = $this->ossClient->getObject( $srcCont, $srcRel );
			file_put_contents( 'php://output', $contents );
		} catch ( OssException $e ) {
			switch ( $e->getCode() ) {
				case 404:
					$status->fatal( 'backend-fail-stream', $params['src'] );
					break;
				default: // some other exception?
					$this->handleException( $e, $status, __METHOD__, $params );
			}
		}
		return $status;
	}
	/**
	 * @see FileBackendStore::doGetLocalCopyMulti()
	 * @return null|TempFSFile
	 */
	protected function doGetLocalCopyMulti( array $params ) {
		$tmpFiles = array();
		$ep = array_diff_key( $params, array( 'srcs' => 1 ) ); // for error logging
		// Blindly create tmp files and stream to them, catching any exception if the file does
		// not exist. Doing a stat here is useless causes infinite loops in addMissingMetadata().
		foreach ( array_chunk( $params['srcs'], $params['concurrency'] ) as $pathBatch ) {
			foreach ( $pathBatch as $path ) { // each path in this concurrent batch
				list( $srcCont, $srcRel ) = $this->resolveStoragePathReal( $path );
				if ( $srcRel === null ) {
					$tmpFiles[$path] = null;
					continue;
				}
				$tmpFile = null;
				try {
					// Get source file extension
					$ext = FileBackend::extensionFromPath( $path );
					// Create a new temporary file...
					$tmpFile = TempFSFile::factory( 'localcopy_', $ext );
					if ( $tmpFile ) {
						$tmpPath = $tmpFile->getPath();
						$contents = $this->ossClient->getObject( $srcCont, $srcRel );
						file_put_contents( $tmpPath, $contents );
					}
				} catch ( OssException $e ) {
					$tmpFile = null;
					switch ( $e->getCode() ) {
						case 404:
							break;
						default: // some other exception?
							$this->handleException( $e, null, __METHOD__, array( 'src' => $path ) + $ep );
					}
				}
				$tmpFiles[$path] = $tmpFile;
			}
		}
		return $tmpFiles;
	}
	/**
	 * @see FileBackendStore::directoriesAreVirtual()
	 * @return bool
	 */
	protected function directoriesAreVirtual() {
		return true;
	}
	/**
	 * Log an unexpected exception for this backend.
	 * This also sets the Status object to have a fatal error.
	 *
	 * @param $e Exception
	 * @param $status Status|null
	 * @param $func string
	 * @param $params Array
	 * @return void
	 */
	protected function handleException( Exception $e, $status, $func, array $params ) {
		if ( $status instanceof Status ) {
			$status->fatal( 'backend-fail-internal', $this->name );
		}
		if ( $e->getMessage() ) {
			trigger_error( "$func:" . $e->getMessage(), E_USER_WARNING );
		}
		$this->logger->error(
			get_class( $e ) . " in '{$func}' (given '" . FormatJson::encode( $params ) . "')" .
			( $e->getMessage() ? ": {$e->getMessage()}" : "" ) 
		);
		// wfDebugLog( 'OssFileBackend',
		// 	get_class( $e ) . " in '{$func}' (given '" . FormatJson::encode( $params ) . "')" .
		// 	( $e->getMessage() ? ": {$e->getMessage()}" : "" )
		// );
	}
}
/*
 * OssFileBackend helper class to page through listsings.
 * Do not use this class from places outside OssFileBackend.
 *
 * @ingroup FileBackend
 */
abstract class OssFileBackendList implements Iterator {
	/** @var Array */
	protected $bufferIter = array();
	protected $bufferAfter = null; // string; list items *after* this path
	protected $pos = 0; // integer
	/** @var Array */
	protected $params = array();
	/** @var OssFileBackend */
	protected $backend;
	protected $container; // string; container name
	protected $dir; // string; storage directory
	protected $suffixStart; // integer
	const PAGE_SIZE = 9000; // file listing buffer size
	/**
	 * @param $backend OssFileBackend
	 * @param $fullCont string Resolved container name
	 * @param $dir string Resolved directory relative to container
	 * @param $params Array
	 */
	public function __construct( OssFileBackend $backend, $fullCont, $dir, array $params ) {
		$this->backend = $backend;
		$this->container = $fullCont;
		$this->dir = $dir;
		if ( substr( $this->dir, -1 ) === '/' ) {
			$this->dir = substr( $this->dir, 0, -1 ); // remove trailing slash
		}
		if ( $this->dir == '' ) { // whole container
			$this->suffixStart = 0;
		} else { // dir within container
			$this->suffixStart = strlen( $this->dir ) + 1; // size of "path/to/dir/"
		}
		$this->params = $params;
	}
	/**
	 * @see Iterator::key()
	 * @return integer
	 */
	public function key() {
		return $this->pos;
	}
	/**
	 * @see Iterator::next()
	 * @return void
	 */
	public function next() {
		// Advance to the next file in the page
		next( $this->bufferIter );
		++$this->pos;
		// Check if there are no files left in this page and
		// advance to the next page if this page was not empty.
		if ( !$this->valid() && count( $this->bufferIter ) ) {
			$this->bufferIter = $this->pageFromList(
				$this->container, $this->dir, $this->bufferAfter, self::PAGE_SIZE, $this->params
			); // updates $this->bufferAfter
		}
	}
	/**
	 * @see Iterator::rewind()
	 * @return void
	 */
	public function rewind() {
		$this->pos = 0;
		$this->bufferAfter = null;
		$this->bufferIter = $this->pageFromList(
			$this->container, $this->dir, $this->bufferAfter, self::PAGE_SIZE, $this->params
		); // updates $this->bufferAfter
	}
	/**
	 * @see Iterator::valid()
	 * @return bool
	 */
	public function valid() {
		if ( $this->bufferIter === null ) {
			return false; // some failure?
		} else {
			return ( current( $this->bufferIter ) !== false ); // no paths can have this value
		}
	}
	/**
	 * Get the given list portion (page)
	 *
	 * @param $container string Resolved container name
	 * @param $dir string Resolved path relative to container
	 * @param $after string|null
	 * @param $limit integer
	 * @param $params Array
	 * @return Traversable|array|null Returns null on failure
	 */
	abstract protected function pageFromList( $container, $dir, &$after, $limit, array $params );
}
/**
 * Iterator for listing directories
 */
class OssFileBackendDirList extends OssFileBackendList {
	/**
	 * @see Iterator::current()
	 * @return string|bool String (relative path) or false
	 */
	public function current() {
		return substr( current( $this->bufferIter ), $this->suffixStart, -1 );
	}
	/**
	 * @see OssFileBackendList::pageFromList()
	 * @return Array|null
	 */
	public function pageFromList( $container, $dir, &$after, $limit, array $params ) {
		return $this->backend->getDirListPageInternal( $container, $dir, $after, $limit, $params );
	}
}
/**
 * Iterator for listing regular files
 */
class OssFileBackendFileList extends OssFileBackendList {
	/**
	 * @see Iterator::current()
	 * @return string|bool String (relative path) or false
	 */
	public function current() {
		return substr( current( $this->bufferIter ), $this->suffixStart );
	}
	/**
	 * @see OssFileBackendList::pageFromList()
	 * @return Array|null
	 */
	public function pageFromList( $container, $dir, &$after, $limit, array $params ) {
		return $this->backend->getFileListPageInternal( $container, $dir, $after, $limit, $params );
	}
}