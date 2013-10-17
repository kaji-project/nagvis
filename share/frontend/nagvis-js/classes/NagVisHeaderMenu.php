<?php
/*****************************************************************************
 *
 * NagVisHeaderMenu.php - Class for handling the header menu
 *
 * Copyright (c) 2004-2011 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

/**
 * @author	Lars Michelsen <lars@vertical-visions.de>
 */
class NagVisHeaderMenu {
    private $CORE;
    private $UHANDLER;
    private $OBJ;
    private $TMPL;
    private $TMPLSYS;

    private $templateName;
    private $pathHtmlBase;
    private $pathTemplateFile;

    private $aMacros = Array();
    private $bRotation = false;

    /**
     * Class Constructor
     *
     * @param 	GlobalCore 	$CORE
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function __construct($CORE, CoreUriHandler $UHANDLER, $templateName, $OBJ = null) {
        $this->CORE = $CORE;
        $this->UHANDLER = $UHANDLER;
        $this->OBJ = $OBJ;
        $this->templateName = $templateName;

        $this->pathHtmlBase = cfg('paths','htmlbase');
        $this->pathTemplateFile = $this->CORE->getMainCfg()->getPath('sys', '', 'templates', $this->templateName.'.header.html');

        // Initialize template system
        $this->TMPL = New FrontendTemplateSystem($this->CORE);
        $this->TMPLSYS = $this->TMPL->getTmplSys();

        // Read the contents of the template file
        $this->checkTemplateReadable(1);
    }

    /**
     * PUBLIC setRotationEnabled()
     *
     * Tells the header menu that the current view is rotating
     *
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function setRotationEnabled() {
        $this->bRotation = true;
    }

    /**
     * Print the HTML code
     *
     * return   String  HTML Code
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function __toString() {
        // Get all macros
        $this->getMacros();

        // Build page based on the template file and the data array
        return $this->TMPLSYS->get($this->TMPL->getTmplFile($this->templateName, 'header'), $this->aMacros);
    }

    /**
     * Returns a list of available languages for the header menus macro list
     *
     * return   Array
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function getLangList() {
        // Build language list
        $aLang = $this->CORE->getAvailableAndEnabledLanguages();
        $numLang = count($aLang);
        foreach($aLang AS $lang) {
            $aLangs[$lang] = Array();
            $aLangs[$lang]['language'] = $lang;

            // Get translated language name
            switch($lang) {
                case 'en_US':
                    $languageLocated = l('en_US');
                break;
                case 'de_DE':
                    $languageLocated = l('de_DE');
                break;
                case 'es_ES':
                    $languageLocated = l('es_ES');
                break;
                case 'fr_FR':
                    $languageLocated = l('fr_FR');
                break;
                case 'pt_BR':
                    $languageLocated = l('pt_BR');
                break;
                case 'ru_RU':
                    $languageLocated = l('ru_RU');
                break;
                default:
                    $languageLocated = l($lang);
                break;
            }

            $aLangs[$lang]['langLanguageLocated'] = $languageLocated;
        }
        return $aLangs;
    }

    /**
     * Returns a list of maps/automaps for the header menus macro list
     *
     * return   Array
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function getMapList($type, $maps) {
        global $AUTHORISATION;
        $permEditAnyMap = false;
        $aMaps = Array();
        $childMaps = Array();
        foreach($maps AS $mapName) {
            $map = Array();

            if($type == 'maps')
                $MAPCFG1 = new NagVisMapCfg($this->CORE, $mapName);
            else
                $MAPCFG1 = new NagVisAutomapCfg($this->CORE, $mapName);
            try {
                $MAPCFG1->readMapConfig(ONLY_GLOBAL);
            } catch(MapCfgInvalid $e) {
                $map['configError'] = true;
            }

            // Only show maps which should be shown
            if($MAPCFG1->getValue(0, 'show_in_lists') != 1)
                continue;

            // Only proceed permited objects
            if(($type == 'maps' && !$AUTHORISATION->isPermitted('Map', 'view', $mapName))
               || ($type == 'automaps' && !$AUTHORISATION->isPermitted('AutoMap', 'view', $mapName)))
                continue;

            $map['mapName'] = $MAPCFG1->getName();
            $map['mapAlias'] = $MAPCFG1->getValue(0, 'alias');
            $map['childs'] = Array();
            if($type == 'maps') {
                $map['urlParams'] = '';
                $map['permittedEdit'] = $AUTHORISATION->isPermitted('Map', 'edit', $mapName);

                $permEditAnyMap |= $map['permittedEdit'];
            } else
                $map['urlParams'] = str_replace('&', '&amp;', $MAPCFG1->getValue(0, 'default_params'));

            // auto select current map and apply map specific optins to the header menu
            if($this->OBJ !== null && ($this->aMacros['mod'] == 'Map' || $this->aMacros['mod'] == 'AutoMap') && $mapName == $this->OBJ->getName()) {
                $map['selected'] = True;

                // Override header fade option with map config
                $this->aMacros['bEnableFade'] = $MAPCFG1->getValue(0, 'header_fade');
            }

            $map['parent'] = $MAPCFG1->getValue(0, 'parent_map');

            if($map['parent'] === '')
                $aMaps[$map['mapName']] = $map;
            else {
                if(!isset($childMaps[$map['parent']]))
                    $childMaps[$map['parent']] = Array();
                $childMaps[$map['parent']][$map['mapName']] = $map;
            }
        }

        return Array($this->mapListToTree($aMaps, $childMaps), $permEditAnyMap);
    }

    private function mapListToTree($maps, $childMaps) {
        foreach($maps AS $map) {
            $freeParent = $map['mapName'];
            if(isset($childMaps[$freeParent])) {
                $maps[$freeParent]['childs'] = $this->mapListToTree($childMaps[$freeParent], $childMaps);
            }
        }
        usort($maps, Array('GlobalCore', 'cmpMapAlias'));
        return $maps;
    }

    /**
     * PRIVATE getMacros()
     *
     * Returns all macros for the header template
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    private function getMacros() {
        global $AUTH, $AUTHORISATION;
        // First get all static macros
        $this->aMacros = $this->getStaticMacros();

        // Save the page
        $this->aMacros['mod'] = $this->UHANDLER->get('mod');
        $this->aMacros['act'] = $this->UHANDLER->get('act');

        // In rotation?
        $this->aMacros['bRotation'] = $this->bRotation;

        $this->aMacros['permittedOverview'] = $AUTHORISATION->isPermitted('Overview', 'view', '*');

        // Check if the user is permitted to edit the current map/automap
        $this->aMacros['permittedView'] = $AUTHORISATION->isPermitted($this->aMacros['mod'], 'view', $this->UHANDLER->get('show'));
        $this->aMacros['permittedEdit'] = $AUTHORISATION->isPermitted($this->aMacros['mod'], 'edit', $this->UHANDLER->get('show'));

        // Permissions for the option menu
        $this->aMacros['permittedSearch']            = $AUTHORISATION->isPermitted('Search', 'view', '*');
        $this->aMacros['permittedEditMainCfg']       = $AUTHORISATION->isPermitted('MainCfg', 'edit', '*');
        $this->aMacros['permittedManageShapes']      = $AUTHORISATION->isPermitted('ManageShapes', 'manage', '*');
        $this->aMacros['permittedManageBackgrounds'] = $AUTHORISATION->isPermitted('ManageBackgrounds', 'manage', '*');
        $this->aMacros['permittedManageBackgrounds'] = $AUTHORISATION->isPermitted('ManageBackgrounds', 'manage', '*');
        $this->aMacros['permittedManageMaps']        = $AUTHORISATION->isPermitted('Map', 'add', '*') && $AUTHORISATION->isPermitted('Map', 'edit', '*');

        $this->aMacros['currentUser'] = $AUTH->getUser();

        $this->aMacros['permittedChangePassword'] = $AUTHORISATION->isPermitted('ChangePassword', 'change', '*');

        $this->aMacros['permittedLogout'] = $AUTH->logoutSupported()
                                        & $AUTHORISATION->isPermitted('Auth', 'logout', '*');

        // Replace some special macros
        if($this->OBJ !== null && ($this->aMacros['mod'] == 'Map' || $this->aMacros['mod'] == 'AutoMap')) {
            $this->aMacros['currentMap'] = $this->OBJ->getName();
            $this->aMacros['currentMapAlias'] = $this->OBJ->getValue(0, 'alias');
        } else {
            $this->aMacros['currentMap'] = '';
            $this->aMacros['currentMapAlias'] = '';
        }

        // Initialize the enable fade option. Is overridden by the current map or left as is
        $this->aMacros['bEnableFade'] = cfg('defaults', 'headerfade');

        list($this->aMacros['maps'], $this->aMacros['permittedEditAnyMap']) = $this->getMapList('maps', $this->CORE->getAvailableMaps());
        list($this->aMacros['automaps'], $_not_used) = $this->getMapList('automaps', $this->CORE->getAvailableAutomaps());
        $this->aMacros['langs'] = $this->getLangList();
    }

    /**
     * Checks if a documentation is available for the current language.
     * It either returns the language tag for the current language when a
     * documentation exists or en_US as fallback when no docs exist
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    private function getDocLanguage() {
        if(in_array(curLang(), $this->CORE->getAvailableDocs()))
	    return curLang();
	else
	    return 'en_US';
    }

    /**
     * PRIVATE getStaticMacros()
     *
     * Get all static macros for the template code
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    private function getStaticMacros() {
        global $SHANDLER, $AUTH, $AUTHORISATION;

        // Replace paths and language macros
        $aReturn = Array('pathBase' => $this->pathHtmlBase,
            'currentUri'         => preg_replace('/[&?]lang=[a-z]{2}_[A-Z]{2}/', '', $this->UHANDLER->getRequestUri()),
            'pathImages'         => cfg('paths','htmlimages'),
            'pathHeaderJs'       => $this->CORE->getMainCfg()->getPath('html', 'global', 'templates', $this->templateName.'.header.js'),
            'pathTemplates'      => $this->CORE->getMainCfg()->getPath('html', 'global', 'templates'),
            'pathTemplateImages' => $this->CORE->getMainCfg()->getPath('html', 'global', 'templateimages'),
            'langSearch'         => l('Search'),
            'langUserMgmt'       => l('Manage Users'),
            'langManageRoles'    => l('Manage Roles'),
            'currentLanguage'    => curLang(),
	    'docLanguage'        => $this->getDocLanguage(),
            'langChooseLanguage' => l('Choose Language'),
            'langUser' => l('User menu'),
            'langActions' => l('Actions'),
            'langLoggedIn' => l('Logged in'),
            'langChangePassword' => l('Change password'),
            'langOpen' => l('Open'),
            'langMap' => l('Map'),
            'langMapOptions' => l('Map Options'),
            'langMapManageTmpl' => l('Manage Templates'),
            'langMapAddIcon' => l('Add Icon'),
            'langMapAddLine' => l('Add Line'),
            'langLine' => l('Line'),
            'langMapAddSpecial' => l('Add Special'),
            'langHost' => l('host'),
            'langService' => l('service'),
            'langHostgroup' => l('hostgroup'),
            'langServicegroup' => l('servicegroup'),
            'langMapEdit' => l('Edit Map'),
            'langMaps' => l('Maps'),
            'langAutomaps' => l('Automaps'),
            'langTextbox' => l('textbox'),
            'langShape' => l('shape'),
            'langStateless' => l('Stateless'),
            'langSpecial' => l('special'),
            'langLockUnlockAll' => l('Lock/Unlock all'),
            'langViewMap' => l('View current map'),
            'langOptions' => l('Options'),
            'langEditMainCfg' => l('General Configuration'),
            'langMgmtBackends' => l('Manage Backends'),
            'langMgmtBackgrounds' => l('Manage Backgrounds'),
            'langMgmtMaps' => l('Manage Maps'),
            'langMgmtShapes' => l('Manage Shapes'),
            'langNeedHelp' => l('needHelp'),
            'langOnlineDoc' => l('onlineDoc'),
            'langForum' => l('forum'),
            'langSupportInfo' => l('supportInfo'),
            'langOverview' => l('overview'),
            'langInstance' => l('instance'),
            'langLogout' => l('Logout'),
            'langRotationStart' => l('rotationStart'),
            'langRotationStop' => l('rotationStop'),
            'langToggleGrid' => l('Show/Hide Grid'),
            'langAutomapToMap' => l('Export to Map'),
            'langModifyAutomapParams' => l('Modify Automap view'),
            // Supported by backend and not using trusted auth
            'supportedChangePassword' => $AUTH->checkFeature('changePassword') && !$AUTH->authedTrusted(),
            'permittedUserMgmt' => $AUTHORISATION->isPermitted('UserMgmt', 'manage'),
            'permittedRoleMgmt' => $AUTHORISATION->isPermitted('RoleMgmt', 'manage'));

        return $aReturn;
    }

    /**
     * Checks for readable header template
     *
     * @param 	Boolean	$printErr
     * @return	Boolean	Is Check Successful?
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function checkTemplateReadable($printErr) {
        return GlobalCore::getInstance()->checkReadable($this->pathTemplateFile, $printErr);
    }
}
?>
