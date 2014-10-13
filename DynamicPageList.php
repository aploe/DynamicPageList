<?php
/**
 * DynamicPageList
 * DynamicPageList Master File
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
**/

if (!defined('MEDIAWIKI')) {
    die('This is not a valid entry point to MediaWiki.');
}

/******************************************/
/* Credits								  */
/******************************************/
define('DPL_VERSION', '3.0');

$credits = [
	'path' 				=> __FILE__,
	'name' 				=> 'DynamicPageList (third party)',
	'author' 			=> '[http://de.wikipedia.org/wiki/Benutzer:Algorithmix Gero Scholz], Alexia E. Smith',
	'url' 				=> 'https://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)',
	'descriptionmsg' 	=> 'dpl-desc',
  	'version' 			=> $DPLVersion
];
$wgExtensionCredits['parserhook'][] = $credits;

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__;

$wgMessagesDirs['DynamicPageList']					= "{$extDir}/i18n";
$wgExtensionMessagesFiles['DynamicPageList']		= "{$extDir}/DynamicPageList.i18n.php";
$wgExtensionMessagesFiles['DynamicPageListMagic']	= "{$extDir}/DynamicPageList.i18n.magic.php";

$wgAutoloadClasses['DPLMain']						= "{$extDir}/DPLMain.php";
$wgAutoloadClasses['ExtDynamicPageList']			= "{$extDir}/DPLSetup.php";
$wgAutoloadClasses['DPL']							= "{$extDir}/DPL.php";
$wgAutoloadClasses['DPLArticle']					= "{$extDir}/DPLArticle.php";
$wgAutoloadClasses['DPLListMode']					= "{$extDir}/DPLListMode.php";
$wgAutoloadClasses['DPLLogger']						= "{$extDir}/DPLLogger.php";
$wgAutoloadClasses['DPLVariables']					= "{$extDir}/DPLVariables.php";

if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	$wgHooks['ParserFirstCallInit'][]					= 'ExtDynamicPageList::setupMigration';
} else {
	$wgHooks['ParserFirstCallInit'][]					= 'ExtDynamicPageList::onParserFirstCallInit';
}

/******************************************/
/* Final Setup                            */
/******************************************/
if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	//Use full functionality by default.
	ExtDynamicPageList::setFunctionalRichness(0);
} else {
	ExtDynamicPageList::setFunctionalRichness(4);
}
?>