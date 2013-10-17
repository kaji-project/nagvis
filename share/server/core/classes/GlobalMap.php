<?php
/*****************************************************************************
 *
 * GlobalMap.php - Class for parsing the NagVis maps
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
class GlobalMap {
    protected $CORE;
    protected $MAPCFG;

    private $linkedMaps = Array();

    /**
     * Class Constructor
     *
     * @param 	GlobalCore 	$CORE
     * @param 	GlobalMapCfg 	$MAPCFG
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function __construct($CORE,$MAPCFG) {
        $this->CORE = $CORE;
        $this->MAPCFG = $MAPCFG;
    }

    /**
     * Parses the objects of the map. Can be called in different modes
     *   complete: first object is the summary of the map and all map objects
     *   summary:  only the summary state of the map
     *   state:    the state of all map objects
     *
     * @param   String  The type of request. Can be complete|summary|state
     * @return	String  Json Code
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function parseObjectsJson($type = 'complete') {
        $arrRet = Array();

        // First parse the map object itselfs for having the
        // summary information in the frontend
        if($type === 'complete' || $type === 'summary')
            $arrRet[] = $this->MAPOBJ->parseJson();

        // In summary mode only return the map object state
        if($type === 'summary')
            return json_encode($arrRet);

        foreach($this->MAPOBJ->getMembers() AS $OBJ) {
            switch(get_class($OBJ)) {
                case 'NagVisHost':
                case 'NagVisService':
                case 'NagVisHostgroup':
                case 'NagVisServicegroup':
                case 'NagVisMapObj':
                    if($type == 'state') {
                        $arr = $OBJ->getObjectStateInformations();
                        $arr['object_id'] = $OBJ->getObjectId();
                        $arr['icon'] = $OBJ->get('icon');
                        $arrRet[] = $arr;
                    } else {
                        $arrRet[] = $OBJ->parseJson();
                    }
                break;
                case 'NagVisShape':
                case 'NagVisLine':
                case 'NagVisTextbox':
                    if($type == 'complete')
                        $arrRet[] = $OBJ->parseJson();
                break;
            }
        }

        return json_encode($arrRet);
    }
}
?>