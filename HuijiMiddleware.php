<?php
/**
 * Example extension - based on the BoilerPlate
 *
 * For more info see mediawiki.org/wiki/Extension:Example
 *
 * You should add a brief comment explaining what the file contains and
 * what it is for. MediaWiki core uses the doxygen documentation syntax,
 * you're recommended to use those tags to complement your comment.
 * See the online documentation at:
 * http://www.stack.nl/~dimitri/doxygen/manual.html
 *
 * Here are commonly used tags, most of them are optional, though:
 *
 * First we tag this document block as describing the entire file (as opposed
 * to a variable, class or function):
 * @file
 *
 * We regroup all extensions documentation in the group named "Extensions":
 * @ingroup Extensions
 *
 * The author would let everyone know who wrote the code, if there is more
 * than one author, add multiple author annotations:
 * @author Jane Doe
 * @author George Foo
 *
 * To mention the file version in the documentation:
 * @version 1.0
 *
 * The license governing the extension code:
 * @license GNU General Public Licence 2.0 or later
 */

/**
 * MediaWiki has several global variables which can be reused or even altered
 * by your extension. The very first one is the $wgExtensionCredits which will
 * expose to MediaWiki metadata about your extension. For additional
 * documentation, see its documentation block in includes/DefaultSettings.php
 */
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,

	// Name of your Extension:
	'name' => 'HuijiMiddleware',

	// Sometime other patches contributors and minor authors are not worth
	// mentionning, you can use the special case '...' to output a localised
	// message 'and others...'.
	'author' => array(
		'Gu Xi',
		'Sun Lixin',
	),

	'version'  => '0.1.1',

	// The extension homepage. www.mediawiki.org will be happy to host
	// your extension homepage.
	'url' => 'https://www.mediawiki.org/wiki/Extension:Example',


	# Key name of the message containing the description.
	'descriptionmsg' => 'example-desc',
);

/* Setup */

// Initialize an easy to use shortcut:
$dir = dirname( __FILE__ );
$dirbasename = basename( $dir );
require_once "/var/www/html/Confidential.php";
require_once __DIR__."/EventEmitter/EventEmitterClass.php";

// Register files
// MediaWiki need to know which PHP files contains your class. It has a
// registering mecanism to append to the internal autoloader. Simply use
// $wgAutoLoadClasses as below:
$wgAutoloadClasses['HuijiFunctions'] = $dir . '/HuijiFunctions.php';
$wgAutoloadClasses['HuijiPrefix'] = $dir . '/HuijiPrefix.php';
$wgAutoloadClasses['HuijiHooks'] = $dir . '/HuijiMiddleware.hooks.php';
$wgAutoloadClasses['SpecialInvitationCode'] = $dir . '/specials/SpecialInvitationCode.php';
$wgAutoloadClasses['ApiQueryExample'] = $dir . '/api/ApiQueryExample.php';
$wgAutoloadClasses['WikiSite'] = $dir . '/WikiSiteClass.php';
$wgAutoloadClasses['Site'] = $dir . '/SiteClass.php';
$wgAutoloadClasses['Huiji'] = $dir . '/HuijiClass.php';
$wgAutoloadClasses['HuijiUser'] = $dir . '/HuijiUserClass.php';
$wgAutoloadClasses['SpecialCreateWiki'] = __DIR__ . '/CreateWikiForm/SpecialCreateWiki.php';
$wgAutoloadClasses['DiskFS'] = $dir . '/HuijiFS/DiskFS.php';
$wgAutoloadClasses['HuijiFS'] = $dir . '/HuijiFS/HuijiFS.php';
$wgAutoloadClasses['OssFS'] = $dir . '/HuijiFS/OssFS.php';
$wgAutoloadClasses['ApiQueryHuijiUserInfo'] = __DIR__. '/api/ApiQueryHuijiUserInfo.php';
$wgAutoloadClasses['ApiQueryHuijiUsers'] = __DIR__. '/api/ApiQueryHuijiUsers.php';
$wgAutoloadClasses['ApiQueryAllHuijiUsers'] = __DIR__. '/api/ApiQueryAllHuijiUsers.php';
$wgAutoloadClasses['FeedbackApi'] = __DIR__. '/api/FeedbackApi.php';

$wgAutoloadClasses['QQLogin\\Auth\\QQPrimaryAuthenticationProvider'] = __DIR__. '/Auth/QQPrimaryAuthenticationProvider.php';
$wgAutoloadClasses['QQLogin\\Auth\\QQAuthenticationRequest'] = __DIR__. '/Auth/QQAuthenticationRequest.php';
$wgAutoloadClasses['QQLogin\\Auth\\QQRemoveAuthenticationRequest'] = __DIR__. '/Auth/QQRemoveAuthenticationRequest.php';
$wgAutoloadClasses['QQLogin\\Auth\\QQServerAuthenticationRequest'] = __DIR__. '/Auth/QQServerAuthenticationRequest.php';
$wgAutoloadClasses['QQLogin\\Auth\\QQUserInfoAuthenticationRequest'] = __DIR__. '/Auth/QQUserInfoAuthenticationRequest.php';

$wgAutoloadClasses['WeiboLogin\\Auth\\WeiboPrimaryAuthenticationProvider'] = __DIR__. '/Auth/WeiboPrimaryAuthenticationProvider.php';
$wgAutoloadClasses['WeiboLogin\\Auth\\WeiboAuthenticationRequest'] = __DIR__. '/Auth/WeiboAuthenticationRequest.php';
$wgAutoloadClasses['WeiboLogin\\Auth\\WeiboRemoveAuthenticationRequest'] = __DIR__. '/Auth/WeiboRemoveAuthenticationRequest.php';
$wgAutoloadClasses['WeiboLogin\\Auth\\WeiboServerAuthenticationRequest'] = __DIR__. '/Auth/WeiboServerAuthenticationRequest.php';
$wgAutoloadClasses['WeiboLogin\\Auth\\WeiboUserInfoAuthenticationRequest'] = __DIR__. '/Auth/WeiboUserInfoAuthenticationRequest.php';

$wgAutoloadClasses['OssFileBackend'] = __DIR__ . '/HuijiFS/OssFileBackend.php';
$wgAutoloadClasses['OssFileBackendList'] = $dir . '/HuijiFS/OssFileBackend.php';
$wgAutoloadClasses['AzureFileBackendDirList'] = $dir . '/HuijiFS/OssFileBackend.php';
$wgAutoloadClasses['AzureFileBackendFileList'] = $dir . '/HuijiFS/OssFileBackend.php';
//$wgAutoloadClasses['EventEmitter'] = __DIR__. '/EventEmitter/EventEmitterClass.php';
$wgAutoloadClasses['HuijiPageInfo'] = $dir. '/HuijiPageInfo.php';

$wgAuthManagerAutoConfig['primaryauth']["WeiboLogin\\Auth\\GooglePrimaryAuthenticationProvider"] =  [
		'class' => 	"WeiboLogin\\Auth\\WeiboPrimaryAuthenticationProvider",
		'sort' => 2,
	];

$wgAuthManagerAutoConfig['primaryauth']["QQLogin\\Auth\\GooglePrimaryAuthenticationProvider"] =  [
		'class' => 	"QQLogin\\Auth\\QQPrimaryAuthenticationProvider",
		'sort' => 1,
	];
require_once( "$IP/extensions/HuijiMiddleware/vendor/autoload.php");

if (!class_exists("PageProps")){
	$wgAutoloadClasses['PageProps'] = $dir . '/PageProps.php';
}

$wgMessagesDirs['HuijiMiddleware'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['HuijiMiddlewareAlias'] = $dir . '/HuijiMiddleware.i18n.alias.php';
$wgExtensionMessagesFiles['HuijiMiddlewareMagic'] = $dir . '/HuijiMiddleware.i18n.magic.php';

//API modules
$wgAPIListModules['HuijiMiddleware'] = 'ApiQueryExample';
$wgAPIListModules['huijiusers'] = 'ApiQueryHuijiUsers';
$wgAPIMetaModules['huijiuserinfo'] = 'ApiQueryHuijiUserInfo';
$wgAPIListModules['allhuijiusers'] = 'ApiQueryAllHuijiUsers';
$wgAPIModules['feedback'] = 'FeedbackApi';

global $wgAvailableRights, $wgGroupPermissions;
$wgAvailableRights[] = 'getinvitationcode';
$wgGroupPermissions['staff']['getinvitationcode'] = true;

// Register hooks
// See also http://www.mediawiki.org/wiki/Manual:Hooks
#$wgHooks['BeforePageDisplay'][] = 'ExampleHooks::onBeforePageDisplay';
#$wgHooks['ResourceLoaderGetConfigVars'][] = 'ExampleHooks::onResourceLoaderGetConfigVars';
#$wgHooks['ParserFirstCallInit'][] = 'ExampleHooks::onParserFirstCallInit';
#$wgHooks['MagicWordwgVariableIDs'][] = 'ExampleHooks::onRegisterMagicWords';
#$wgHooks['ParserGetVariableValueSwitch'][] = 'ExampleHooks::onParserGetVariableValueSwitch';
#$wgHooks['LoadExtensionSchemaUpdates'][] = 'ExampleHooks::onLoadExtensionSchemaUpdates';

// Register special pages
// See also http://www.mediawiki.org/wiki/Manual:Special_pages
$wgSpecialPages['invitationcode'] = 'SpecialInvitationCode';
$wgSpecialPages['CreateWiki'] = 'SpecialCreateWiki';

// Hooks
$wgHooks['LoadExtensionSchemaUpdates'][] = 'HuijiHooks::addTables';
$wgHooks['GetDoubleUnderscoreIDs'][] = 'HuijiHooks::onGetDoubleUnderscoreIDs';
$wgHooks['OpenSearchUrls'][] = 'HuijiHooks::onOpenSearchUrls';
$wgHooks['MagicWordwgVariableIDs'][] = 'HuijiHooks::onRegisterMagicWords';
$wgHooks['ParserGetVariableValueSwitch'][] = 'HuijiHooks::onParserGetVariableValueSwitch';
$wgHooks['APIQuerySiteInfoStatisticsInfo'][] = 'HuijiHooks::onAPIQuerySiteInfoStatisticsInfo';
$wgHooks['APIQuerySiteInfoGeneralInfo'][] = 'HuijiHooks::onAPIQuerySiteInfoGeneralInfo'; 
$wgHooks['GetPreferences'][] = 'HuijiHooks::onGetPreferences';
$wgHooks['ParserFirstCallInit'][] = 'HuijiHooks::onSetupParserFunction';
$wgHooks['SpecialPageBeforeExecute'][] = 'HuijiHooks::onSpecialPageBeforeExecute';
$wgHooks['EditPageCopyrightWarning'][] = 'HuijiHooks::onEditPageCopyrightWarning';
$wgHooks['InfoAction'][] = 'HuijiHooks::onInfoAction';
// Register modules
// See also http://www.mediawiki.org/wiki/Manual:$wgResourceModules
// ResourceLoader modules are the de facto standard way to easily
// load JavaScript and CSS files to the client.
$wgResourceModules['ext.HuijiMiddleware.flash'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.flash.js',
	'dependencies' => array(
	),

	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);

//create wiki
$wgResourceModules['ext.HuijiMiddleware.createwiki.js'] = array(
	'scripts' => 'CreateWikiForm/createwiki.js',
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.createwiki.css'] = array(
	'styles' => 'CreateWikiForm/createwiki.css',
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/',
	'position' => 'top',
);

$wgResourceModules['ext.HuijiMiddleware.LightBox'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.LightBox.js',
	'dependencies' => array(
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.querystring'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.querystring.js',
	'dependencies' => array(
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.video.init'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.video.init.js',
	'dependencies' => array(
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.feedback'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.feedback.js',
	'styles' => 'modules/ext.HuijiMiddleware.feedback.css',
	'dependencies' => array(
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.callbackqq.js'] = array(
	'scripts' => 'modules/ext.HuijiMiddleware.callbackqq.js',
	'dependencies' => array(
	),
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',
);
$wgResourceModules['ext.HuijiMiddleware.eventemitter'] = array(
	'scripts' => 'EventEmitter/EventEmitter.js',
	'localBasePath' => $dir,
	'remoteExtPath' => 'HuijiMiddleware/' . $dirbasename,
	'position' => 'bottom',	
);

/* Configuration */


/** Your extension configuration settings. Since they are going to be global
 * always use a "wg" prefix + your extension name + your setting key.
 * The entire variable name should use "lowerCamelCase".
 */

// Enable Welcome
// Example of a configuration setting to enable the 'Welcome' feature:
$wgExampleEnableWelcome = true;

// Color map for the Welcome feature
$wgExampleWelcomeColorDefault = '#eee';
// Days not defined here fall back to the default
$wgExampleWelcomeColorDays = array(
	'Monday' => 'orange',
	'Tuesday' => 'blue',
	'Wednesday' => 'green',
	'Thursday' => 'red',
	'Friday' => 'yellow',
);

// Value of {{MYWORD}} constant
$wgExampleMyWord = 'Awesome';

//Use Aliyun oss to store files
$wgUseOss = true;
$wgOssAvatarPath = "http://av.huijiwiki.com";
$wgOssStylePath = "";
$wgOssEndpoint = "oss-cn-qingdao-internal.aliyuncs.com";
$wgOssPath = "huijistatic.com";
$wgOssFSBucket = "huiji-fs";

$wgFileBackends[] = array(
    'name'         => 'localOss',
    'class'        => 'OssFileBackend',
    'lockManager'  => 'nullLockManager',
);

$wgLocalFileRepo = array (
    'class'             => 'LocalRepo',
    'name'              => 'local',
    'backend'           => 'localOss',
    'scriptDirUrl'      => $wgScriptPath,
    'scriptExtension'   => $wgScriptExtension,
    'hashLevels'        => 2,
    'deletedHashLevels' => 2,
    'zones'             => array(
        'public'  => array( 
        	'container' => 'huiji-public',
        	'url'       => 'http://huiji-public.'.$wgOssPath.'/'.$wgHuijiPrefix.'/uploads',
        	'directory' => $wgHuijiPrefix.'/uploads',
         ),
        'thumb'   => array( 
        	'container' => 'huiji-thumb',
        	'url'       => 'http://huiji-thumb.'.$wgOssPath.'/'.$wgHuijiPrefix.'/uploads/thumb',
        	'directory' => $wgHuijiPrefix.'/uploads/thumb',
         ),
        'temp'    => array( 
        	'container' => 'huiji-temp',
        	'url'       => 'http://huiji-temp.'.$wgOssPath.'/'.$wgHuijiPrefix.'/uploads/temp',
        	'directory' => $wgHuijiPrefix.'/uploads/temp',
        ),
        'deleted' => array( 
        	'container' => 'huiji-deleted',
        	'url'       => 'http://huiji-deleted.'.$wgOssPath.'/'.$wgHuijiPrefix.'/uploads/deleted',
        	'directory' => $wgHuijiPrefix.'/uploads/deleted',
        ),
    )
);

$wgImgAuthPublicTest = false;

const FS_DISK = 0;
const FS_OSS = 1;
const FS_ANYTHING = 3;
function wfGetFS($which = FS_ANY){
	global $wgUseOss;
	if ($which == FS_DISK){
		return DiskFS::getInstance();
	}
	if ($which == FS_OSS){
		return OssFS::getInstance();
	}
	if ($which == FS_ANYTHING){
		if ($wgUseOss){
			return OssFS::getInstance();
		} else {
			return DiskFS::getInstance();
		}
	}
}
$wgDefaultUserOptions['showeditjs'] = 0;
$wgDefaultUserOptions['showeditcss'] = 0;

// Default Site Options
$wgDefaultSiteProperty['enable-voteny'] = 0;
$wgDefaultSiteProperty['enable-pollny'] = 0;
$wgDefaultSiteProperty['hide-bots-in-concile'] = 0;
$wgDefaultSiteProperty['enable-semantic-mediawiki'] = 0;
$wgDefaultSiteProperty['enable-blogpage'] = 0;
//Settings
$wgSiteSettings['enable-voteny']['level'] = 'D';
$wgSiteSettings['enable-pollny']['level'] = 'C';
$wgSiteSettings['hide-bots-in-concile']['level'] = 'NA';
$wgSiteSettings['enable-semantic-mediawiki']['level'] = 'NA';
$wgSiteSettings['enable-blogpage'] = array(
	'title' => '博客组件',
	'description' => '允许用户在博客命名空间下(NS_BLOG)撰写不开放编辑的文章。',
	'value' => "<span class='toggle' data-value='false' data-state='true'></span>",
	'level' => 'B',
);

