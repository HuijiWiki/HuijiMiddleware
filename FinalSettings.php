<?php
# This file was automatically generated by the MediaWiki 1.24.1
# installer. If you make manual changes, please keep track in case you
# need to recreate them later.
#
# See includes/DefaultSettings.php for all configurable settings
# and their default values, but don't forget to make changes in _this_
# file, not there.
#
# Further documentation for configuration settings may be found at:
# https://www.mediawiki.org/wiki/Manual:Configuration_settings

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
    exit;
}

$site = WikiSite::newFromPrefix($wgHuijiPrefix);
if ($site->getProperty('enable-voteny') == 1){
        require_once "$IP/extensions/VoteNY/VoteNY.php";
}
if ($site->getProperty('enable-pollny') == 1){
        require_once "$IP/extensions/PollNY/PollNY.php";
}

?>
~             