<?php
//Database management via Propel

class Aptostat
{

    function pingSave($pingResult)
    {

        foreach ($pingResult as $report) {

            //Checks the database for matching reports.
            $match = ReportQuery::create()
                ->useServiceQuery()
                    ->filterByName($report["hostname"])
                ->endUse()
                ->filterByIdSource('2')
                ->useGroupsQuery()
                    ->filterByProposedFlag('2')
                ->endUse()
                ->filterByCheckType($report["type"])
                ->filterByErrorMessage($report["status"])
                ->findOne();

            if (is_null($match)) {

                $group = new Groups();
                $group->setProposedFlag('2');
                $group->setTimestamp(time());

                $serv = ServiceQuery::create()->findByName($report["hostname"]);

                foreach ($serv as $tmp) {

                    $servId = $tmp->getIdService();

                }

                $entry = new Report();
                $entry->setErrorMessage($report["status"]);
                $entry->setTimestamp($report["lasterrortime"]);
                $entry->setCheckType($report["type"]);
                $entry->setIdSource('2');
                $entry->setIdService($servId);
                $entry->addGroups($group);

                $entry->save();

            }
        }
    }
    
    function nagSave($nagResult)
    {
    
        foreach ($nagResult as $name => $report) {

            foreach ($report as $service) {

                //Checks the database for matching invisible reports.
                $matchInvis = GroupsQuery::create()
                    ->useReportQuery()
                        ->filterByTimestamp($service["statechange"])
                        ->filterByIdSource('1')
                        ->filterByCheckType($service["type"])
                        ->useServiceQuery()
                            ->filterByName($name)
                        ->endUse()
                    ->endUse()
                    ->filterByProposedFlag('4')
                    ->findOne();

                //Checks the database for matching visible reports.
                $matchVis = ReportQuery::create()
                    ->filterByTimestamp($service["statechange"])
                    ->useServiceQuery()
                        ->filterByName($name)
                    ->endUse()
                    ->filterByIdSource('1')
                    ->filterByCheckType($service["type"])
                    ->findOne();

                //If a matching report is found as invisible, make visible.
                if (!is_null($matchInvis)) {

                    $group = GroupsQuery::create()->findPK(array($matchInvis->getIdGroup(),$matchInvis->getIdReport()));
                    $group->setProposedFlag($service["state"]);
                    $group->setTimestamp(time());
                    $group->save();

                //If no matching report is found, create as invisible.
                } elseif (is_null($matchVis)) {

                    $group = new Groups();
                    $group->setProposedFlag('4');
                    $group->setTimestamp(time());

                    $serv = ServiceQuery::create()->findByName($name);

                    foreach ($serv as $tmp) {

                        $servId = $tmp->getIdService();
    
                    }

                    $entry = new Report();
                    $entry->setErrorMessage($service["output"]);
                    $entry->setTimestamp($service["statechange"]);
                    $entry->setCheckType($service["type"]);
                    $entry->setIdSource('1');
                    $entry->setIdService($servId);
                    $entry->addGroups($group);

                    $entry->save();

                }
            }
        }
    }
    
    function flagResolved()
    {
    
        
    }
    
    function groupReports()
    {
    
        $list = array();
    
        $reports = ReportQuery::create()
            ->join('Report.Groups')
            ->where('Report.IdReport = ?', 'Groups.IdReport')
            ->useGroupsQuery()
                ->filterByProposedFlag(array(1,2,4))
            ->endUse()
            ->orderByIdService()
            ->find();

        foreach ($reports as $report) {
        
            $list[$report->getIdService()][$report->getIdGroup()] = $report->getIdReport();
        
        }
        
        foreach ($list as $group) {

            if (count($group) >= 2) {
            
                $groupMaster = key($group);
            
                foreach ($group as $key => $value) {
            
                    if ($key != $groupMaster) {
            
                        $groupUpdate = GroupsQuery::create()->findOneByIdReport($value);
                        $groupUpdate->setIdGroup($groupMaster);
                    
                        $reportUpdate = ReportQuery::create()->findOneByIdGroup($key);
                        $reportUpdate->setIdGroup($groupMaster);
                    
                        $reportUpdate->save();

                    } 
                }
            }
        }
    }
}