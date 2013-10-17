<?php
/*****************************************************************************
 *
 * CoreBackendMgmt.php - class for handling all backends
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
class CoreBackendMgmt {
    protected $CORE;
    public $BACKENDS = Array();
    private $aInitialized = Array();
    private $aQueue = Array();
    private $aError = Array();
    private $countQueries = Array(
        'serviceState' => '',
        'hostState' => '',
        'hostMemberState' => '',
        'hostgroupMemberState' => '',
        'servicegroupMemberState' => ''
    );


    /**
     * Constructor
     *
     * Initializes all backends
     *
     * @param   config  $MAINCFG
     * @author  Lars Michelsen <lars@vertical-visions.de>
     */
    public function __construct($CORE) {
        $this->CORE = $CORE;

        $this->loadBackends();

        return 0;
    }

    public function getBackend($id) {
        // Only try to initialize once per request
        if(!isset($this->aInitialized[$id]) && !isset($this->aError[$id]))
            $this->initializeBackend($id);

        // Re-throw the stored backend exception for this request
        if(isset($this->aError[$id]))
            throw $this->aError[$id];

        return $this->BACKENDS[$id];
    }

    /**
     * PUBLIC queue()
     *
     * Add a backend query to the queue
     *
     * @param   Array   Queries to be added to the queue
     * @param   Object  Map object to fetch the informations for
     * @author  Lars Michelsen <lars@vertical-visions.de>
     */
    public function queue($query, $OBJ) {
        $backendId = $OBJ->getBackendId();
        if(!isset($this->aQueue[$backendId]))
            $this->aQueue[$backendId] = Array();

        foreach($query AS $query => $_unused) {
            if(!isset($this->aQueue[$backendId][$query]))
                $this->aQueue[$backendId][$query] = Array();

            // Gather the object name
            if($query == 'serviceState')
                $name = $OBJ->getName().'~~'.$OBJ->getServiceDescription();
            else
                $name = $OBJ->getName();

            // Options is a mask which tells the backend how to handle this object
            $options = $this->parseOptions($OBJ);

            // Each object can have individual filter options. For example the
            // member filters
            $objFilters = $this->parseObjFilters($query, $OBJ);

            // Only query the backend once per object+options+filter
            // If the object is queued several times with the same options+filters
            // add it to the list of objects. The backend result will be added to
            // all objects in that list later
            if(!isset($this->aQueue[$backendId][$query][$options][$objFilters]))
                $this->aQueue[$backendId][$query][$options][$objFilters] = Array($name => Array($OBJ));
            elseif(!isset($this->aQueue[$backendId][$query][$options][$objFilters][$name]))
                $this->aQueue[$backendId][$query][$options][$objFilters][$name] = Array($OBJ);
            else
                $this->aQueue[$backendId][$query][$options][$objFilters][$name][] = $OBJ;
        }
    }

    private function parseObjFilters($query, $OBJ) {
        $isMemberQuery = $query != 'serviceState' && $query != 'hostState';
        $isCountQuery = isset($this->countQueries[$query]);

        if(!$isMemberQuery || !$OBJ->hasExcludeFilters($isCountQuery))
            return '';

        return $OBJ->getExcludeFilterKey($isCountQuery).'~~'.$OBJ->getExcludeFilter($isCountQuery);
    }

    private function parseOptions($OBJ) {
        $options = 0;
        if($OBJ->getOnlyHardStates())
            $options |= 1;
        if(!$OBJ->getRecognizeServices())
            $options |= 2;
        /*FIXME: Implement as optional filter: "Filter: in_notification_period = 1\n" .*/

        return $options;
    }

    /**
     * PUBLIC clearQueue()
     *
     * Resets the backend queue
     *
     * @author  Lars Michelsen <lars@vertical-visions.de>
     */
    public function clearQueue() {
        $this->aQueue = Array();
    }

    /**
     * PUBLIC execute()
     *
     * Executes all backend queries and assigns the gathered information
     * to the objects
     *
     * @author  Lars Michelsen <lars@vertical-visions.de>
     */
    public function execute() {
        // Loop all backends
        foreach($this->aQueue AS $backendId => $types) {
            // Loop all different query types
            foreach($types AS $type => $options) {
                // Now loop the different options (Splitting by only_hard_state options etc.)
                foreach($options AS $option => $filters) {
                    foreach($filters AS $filter => $aObjs) {
                        switch($type) {
                            case 'serviceState':
                            case 'hostState':
                            case 'hostMemberState':
                            case 'hostgroupMemberState':
                            case 'servicegroupMemberState':
                                $this->fetchStateCounts($backendId, $type, $option, $aObjs);
                            break;
                            case 'hostMemberDetails':
                                $this->fetchHostMemberDetails($backendId, $option, $aObjs);
                            break;
                            case 'hostgroupMemberDetails':
                                $this->fetchHostgroupMemberDetails($backendId, $option, $aObjs);
                            break;
                            case 'servicegroupMemberDetails':
                                $this->fetchServicegroupMemberDetails($backendId, $option, $aObjs);
                            break;
                        }
                    }
                }
            }
        }

        // Clear the queue after processing
        $this->clearQueue();
    }

    /**
     * PRIVATE fetchServicegroupMemberDetails()
     *
     * Loops all queued servicegroups and executes the queries for each group.
     * Gets all services of the servicegroup and saves them to the members array
     *
     * This is trimmed to reduce the number of queries to the backend:
     * 1.) fetch states for all services
     * 2.) fetch state counts for all services
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    private function fetchServicegroupMemberDetails($backendId, $options, $aObjs) {
        foreach($aObjs AS $name => $OBJS)
            foreach($OBJS AS $OBJ) {
                // Fist get the host states for all the hostgroup members
                try {
                    $filters = Array(Array('key' => 'service_groups', 'op' => '>=', 'val' => 'name'));
                    $aServices = $this->getBackend($backendId)->getServiceState(Array($OBJ->getName() => Array($OBJ)), $options, $filters, MEMBER_QUERY);
                } catch(BackendException $e) {
                    $aServices = Array();
                    $OBJ->setBackendProblem(l('Connection Problem (Backend: [BACKENDID]): [MSG]',
                                                                   Array('BACKENDID' => $backendId, 'MSG' => $e->getMessage())));
                }

                // Regular member adding loop
                $members = Array();
                foreach($aServices AS $host => $serviceList) {
                    foreach($serviceList AS $aService) {
                        $SOBJ = new NagVisService($this->CORE, $this, $backendId, $host, $aService['service_description']);

                        // Append contents of the array to the object properties
                        $SOBJ->setObjectInformation($aService);

                        // Also get summary state
                        $aService['summary_state'] = $aService['state'];
                        $aService['summary_output'] = $aService['output'];

                        // The services have to know how they should handle hard/soft
                        // states. This is a little dirty but the simplest way to do this
                        // until the hard/soft state handling has moved from backend to the
                        // object classes.
                        $SOBJ->setConfiguration($OBJ->getObjectConfiguration());

                        // Add child object to the members array
                        $members[] = $SOBJ;
                    }
                    $OBJ->setMembers($members);
                }
            }
    }

    /**
     * PRIVATE fetchHostgroupMemberDetails()
     *
     * Loops all queued objects.
     * Gets all hosts of the hostgroup and saves them to the members array
     *
     * This is trimmed to reduce the number of queries to the backend:
     * 1.) fetch states for all hosts
     * 2.) fetch state counts for all hosts
     *
     * @author	Lars Michelsen <lars@vertical-visions.de>
     */
    private function fetchHostgroupMemberDetails($backendId, $options, $aObjs) {
        // And then apply them to the objects
        foreach($aObjs AS $name => $OBJS)
            foreach($OBJS AS $OBJ) {
                // First get the host states for all the hostgroup members
                try {
                    $filters = Array(Array('key' => 'host_groups', 'op' => '>=', 'val' => 'name'));
                    $aHosts = $this->getBackend($backendId)->getHostState(Array($OBJ->getName() => Array($OBJ)), $options, $filters, MEMBER_QUERY);
                } catch(BackendException $e) {
                    $aHosts = Array();
                    $OBJ->setBackendProblem(l('Connection Problem (Backend: [BACKENDID]): [MSG]',
                                                                  Array('BACKENDID' => $backendId, 'MSG' => $e->getMessage())));
                }

                // Now fetch the service state counts for all hostgroup members
                $aServiceState = Array();
                if($OBJ->getRecognizeServices()) {
                    try {
                        $filters = Array(Array('key' => 'host_groups', 'op' => '>=', 'val' => 'name'));
                        $aServiceStateCounts = $this->getBackend($backendId)->getHostStateCounts(
                                           Array($OBJ->getName() => Array($OBJ)), $options, $filters);
                    } catch(BackendException $e) {}
                }

                $members = Array();
                foreach($aHosts AS $name => $aHost) {
                    $HOBJ = new NagVisHost($this->CORE, $this, $backendId, $name);

                    // Append contents of the array to the object properties
                    $HOBJ->setObjectInformation($aHost);

                    // The services have to know how they should handle hard/soft
                    // states. This is a little dirty but the simplest way to do this
                    // until the hard/soft state handling has moved from backend to the
                    // object classes.
                    $HOBJ->setConfiguration($OBJ->getObjectConfiguration());

                    // Put state counts to the object
                    if(isset($aServiceStateCounts[$name]) && isset($aServiceStateCounts[$name]['counts'])) {
                        $HOBJ->setStateCounts($aServiceStateCounts[$name]['counts']);
                    }

                    // Fetch summary state and output
                    $HOBJ->fetchSummariesFromCounts();

                    $members[] = $HOBJ;
                }

                $OBJ->setMembers($members);
            }

    }

    private function fetchStateCounts($backendId, $type, $options, $aObjs) {
        try {
            switch($type) {
                case 'servicegroupMemberState':
                    $filters = Array(Array('key' => 'groups', 'op' => '>=', 'val' => 'name'));
                    $aResult = $this->getBackend($backendId)->getServicegroupStateCounts($aObjs, $options, $filters);
                break;
                case 'hostgroupMemberState':
                    $filters = Array(Array('key' => 'groups', 'op' => '>=', 'val' => 'name'));
                    $aResult = $this->getBackend($backendId)->getHostgroupStateCounts($aObjs, $options, $filters);
                break;
                case 'serviceState':
                    $filters = Array(
                        Array('key' => 'host_name', 'op' => '=', 'val' => 'name'),
                        Array('key' => 'service_description', 'op' => '=', 'service_description')
                    );
                    $aResult = $this->getBackend($backendId)->getServiceState($aObjs, $options, $filters);
                break;
                case 'hostState':
                    $filters = Array(Array('key' => 'host_name', 'op' => '=', 'val' => 'name'));
                    $aResult = $this->getBackend($backendId)->getHostState($aObjs, $options, $filters);
                break;
                case 'hostMemberState':
                    $filters = Array(Array('key' => 'host_name', 'op' => '=', 'val' => 'name'));
                    $aResult = $this->getBackend($backendId)->getHostStateCounts($aObjs, $options, $filters);
                break;
            }
        } catch(BackendException $e) {
            $aResult = Array();
            $msg = $e->getMessage();
        }

        foreach($aObjs AS $name => $OBJS)
            if(isset($aResult[$name]))
                if($type == 'serviceState' || $type == 'hostState')
                    foreach($OBJS AS $OBJ)
                        $OBJ->setState($aResult[$name]);
                else
                    foreach($OBJS AS $OBJ) {
                        if(isset($aResult[$name]['details']))
                            $OBJ->setObjectInformation($aResult[$name]['details']);
                        if(isset($aResult[$name]['counts']))
                            $OBJ->setStateCounts($aResult[$name]['counts']);
                    }
            else
                if($type != 'hostMemberState')
                    foreach($OBJS AS $OBJ)
                        if(isset($msg))
                            $OBJ->setBackendProblem($msg);
                        else
                            $OBJ->setBackendProblem(l('The object "[OBJ]" does not exist ([TYPE]).',
                                                             Array('OBJ' => $name, 'TYPE' => $type)));
    }

    private function fetchHostMemberDetails($backendId, $options, $aObjs) {
        try {
            $filters = Array(Array('key' => 'host_name', 'op' => '=', 'val' => 'name'));
            $aMembers = $this->getBackend($backendId)->getServiceState($aObjs, $options, $filters, MEMBER_QUERY);
        } catch(BackendException $e) {
            $aMembers = Array();
        }

        foreach($aObjs AS $name => $OBJS) {
            if(isset($aMembers[$name])) {
                foreach($OBJS AS $OBJ) {
                    $members = Array();
                    foreach($aMembers[$name] AS $service => $details) {
                        $MOBJ = new NagVisService($this->CORE, $this, $backendId, $OBJ->getName(), $details['service_description']);
                        $MOBJ->setState($details);
                        $members[] = $MOBJ;
                    }

                    $OBJ->setMembers($members);
                }
            }
        }
    }

    /**
     * Loads all backends and prints an error when no backend defined
     *
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function loadBackends() {
        $aBackends = $this->CORE->getDefinedBackends();

        if(!count($aBackends))
            throw new NagVisException(l('noBackendDefined'));
    }

    /**
     * Checks for existing backend file
     *
     * @param	Boolean $printErr
     * @return	Boolean	Is Successful?
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function checkBackendExists($backendId, $printErr) {
        if($this->CORE->checkExisting(cfg('paths','class').'GlobalBackend'.cfg('backend_'.$backendId,'backendtype').'.php', false))
            return true;

        if($printErr == 1)
            throw new NagVisException(l('backendNotExists', Array('BACKENDID'   => $backendId,
                                                                  'BACKENDTYPE' => cfg('backend_'.$backendId,'backendtype'))));
        return false;
    }

    /**
     * Checks if a backend host is status using status
   * information from another backend
     *
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function backendAlive($backendId, $statusHost) {
        list($statusBackend, $statusHost) = explode(':', $statusHost, 2);

        if($statusBackend == $backendId)
            $this->aError[$backendId] = new BackendConnectionProblem(l('Configuration Error: The statusHost ([STATUSHOST]) is in same backend as the one to check.', Array('STATUSHOST' => $statusHost)));

        try {
            $filters = Array(Array('key' => 'host_name', 'op' => '=', 'val' => 'name'));
            $aObjs = Array($statusHost => Array(new NagVisHost($this->CORE, $this, $statusBackend, $statusHost)));
            $aCounts = $this->getBackend($statusBackend)->getHostState($aObjs, 1, $filters);
        } catch(BackendException $e) {
            return true;
        }

        if($aCounts[$statusHost]['state'] == 'UP')
            return true;
        else
            return false;
    }

    /**
     * Initializes a backend
     *
     * @return	Boolean	Is Successful?
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    private function initializeBackend($backendId) {
        if(!$this->checkBackendExists($backendId, false)) {
            $this->aError[$backendId] = new BackendConnectionProblem(l('backendNotDefined',
                                            Array('BACKENDID'   => $backendId)));
            return false;
        }
        /**
         * The status host can be used to prevent annoying timeouts when a backend is not
         * reachable. This is only useful in multi backend setups.
         *
         * It works as follows: The assumption is that there is a "local" backend which
         * monitors the host of the "remote" backend. When the remote backend host is
         * reported as UP the backend is queried as normal.
         * When the remote backend host is reported as "DOWN" or "UNREACHABLE" NagVis won't
         * try to connect to the backend anymore until the backend host gets available again.
         */
        $statusHost = cfg('backend_' . $backendId, 'statushost');
        if($statusHost != '' && !$this->backendAlive($backendId, $statusHost)) {
            $this->aError[$backendId] = new BackendConnectionProblem(l('The backend is reported as dead by the statusHost ([STATUSHOST]).', Array('STATUSHOST' => $statusHost)));
            return false;
        }

        try {
            $backendClass = 'GlobalBackend' . cfg('backend_' . $backendId, 'backendtype');
            $this->BACKENDS[$backendId] = new $backendClass($this->CORE, $backendId);

            // Mark backend as initialized
            $this->aInitialized[$backendId] = true;

            return true;
        } catch(BackendException $e) {
            $this->aError[$backendId] = $e;
            return false;
        }
    }

    /**
     * Checks for an initialized backend
     *
     * @param	Boolean $printErr
     * @return	Boolean	Is Successful?
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     * @deprecated Please don't use this function anymore
     */
    public function checkBackendInitialized($backendId, $printErr) {
        if(isset($this->aInitialized[$backendId])) {
            return true;
        } else {
            if($printErr == 1) {
                throw new NagVisException(l('backendNotInitialized', Array('BACKENDID' => $backendId,
                    'BACKENDTYPE' => cfg('backend_'.$backendId,'backendtype'))));
            }
            return false;
        }
    }

    /**
     * Checks if the given feature is provided by the given backend
     *
     * @param	Boolean $printErr
     * @return	Boolean	Is Successful?
     * @author 	Lars Michelsen <lars@vertical-visions.de>
     */
    public function checkBackendFeature($backendId, $feature, $printErr = 1) {
        $backendClass = 'GlobalBackend'.cfg('backend_'.$backendId,'backendtype');
        if(method_exists($backendClass, $feature)) {
            return true;
        } else {
            if($printErr == 1) {
                throw new NagVisException(l('The requested feature [FEATURE] is not provided by the backend (Backend-ID: [BACKENDID], Backend-Type: [BACKENDTYPE]). The requested view may not be available using this backend.',
                                          Array('FEATURE'     => htmlentities($feature),
                                                'BACKENDID'   => $backendId,
                                                'BACKENDTYPE' => cfg('backend_'.$backendId,'backendtype'))));
            }
            return false;
        }
    }
}
?>
