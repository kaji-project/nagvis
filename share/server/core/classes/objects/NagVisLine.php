<?php
/*****************************************************************************
 *
 * NagVisLine.php - Class of a Stateless line in NagVis with all necessary
 *                  information which belong to the object handling in NagVis
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
class NagVisLine extends NagVisStatelessObject {

    public function __construct($CORE) {
        $this->type = 'line';
        parent::__construct($CORE);
    }

    /**
     * PUBLIC parseJson()
     *
     * Parses the object in json format
     *
     * @return	String		JSON code of the object
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    public function parseJson() {
        return parent::parseJson();
    }

    /**
     * PUBLIC getHoverMenu()
     *
     *Gets the hover menu of a shape if it is requested by configuration
     *
     * @return	String	The Link
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function getHoverMenu() {
        if(isset($this->hover_url) && $this->hover_url != '') {
            parent::getHoverMenu();
        }
    }

    /**
     * PUBLIC fetchIcon()
     *
     * Just a dummy here (Shape won't need an icon)
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    public function fetchIcon() {
        // Nothing to do here, icon is set in constructor
    }

    # End public methods
    # #########################################################################
}
?>
