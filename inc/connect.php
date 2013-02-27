<?php
//
//Gathering from Pingdom/Nagios
//

class Connect
{

    public function ping_fetch($user,$pass,$key)
    {

        $errors = array();

        //Init curl
        $curl = curl_init();

        //Setup curl
        $options = array(
            CURLOPT_URL => "https://api.pingdom.com/api/2.0/checks",
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERPWD => "$user:$pass",
            CURLOPT_HTTPHEADER => array("App-Key: $key"),
            CURLOPT_RETURNTRANSFER => true

        );

        curl_setopt_array($curl,$options);

        //Excecute and save result as an assoc-array
        $response = json_decode(curl_exec($curl),true);

        if (isset($response["error"])) {
            Log::writeLog("pingdom","Pingdom: ".$response["error"]["errormessage"]);
            exit();
        }

        //Filter out all checks where status == "up"
        $checkList = $response["checks"];

        foreach ($checkList as $check) {

            if ($check["status"]  != "up") {

                    $errors[] = $check;
            }
        }
    return $errors;
    }

    public function nag_fetch()
    {
        $errors = array();
        $unixTime = array();

        //Setup curl
        $curl = curl_init();

        $options = array(
            CURLOPT_URL => "http://nagios.lon.aptoma.no:8080/log",
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true
        );

        curl_setopt_array($curl,$options);

        $response = json_decode(curl_exec($curl),true);
        $checkList = $response["content"];

        foreach ($checkList as $check) {

            $unixTime = substr($check,1,11);
            $alert = substr($check,13,strpos($check,":")-13);

            //Parsing for service alerts
            if ($alert == "SERVICE ALERT") {

                $typeSplit = strpos($check,";");
                $typeEnd = strpos($check,";",$typeSplit+1);
                $host = substr($check,strpos($check,":")+2,$typeSplit-strpos($check,":")-2);
                $type = substr($check,$typeSplit+1,$typeEnd-$typeSplit-1);

                $flag = substr($check,$typeEnd+1,strpos($check,";",$typeEnd+1)-$typeEnd-1);

                $message = substr($check,strripos($check,";")+1);

                if ($flag != "OK") {
                    $errors[] = array(
                                "lasterrortime" => $unixTime,
                                "type" => $type,
                                "name" => $host,
                                "status" => $flag,
                                "message" => $message
                                );
                }
            }

            //External commands have an additional sub-parameter
            if ($alert == "EXTERNAL COMMAND") {

            }

        }

    return $errors;
    }

    function nag_state()
    {
        //Init
        $curl = curl_init();
        $tmpError = 0;
        $tmpErrors = array();
        $errors = array();

        //Setting curl options
        $options = array(
            CURLOPT_URL => "http://nagios.lon.aptoma.no:8080/state",
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($curl,$options);

        //Executing curl, decoding JSON into an associative array
        $response = json_decode(curl_exec($curl),true);
        $checkList = $response["content"];

        //Go through each host/server/entity in sequence
        foreach ($checkList as $checkName => $check) {

            //Check every service (ping, SSH, free disk space, free memory etc) on a given host for errors
            foreach ($check["services"] as $name => $service) {

                //If an error is found, add information about it to a temporary array
                if ($service["current_state"] != "0") {

                    $tmpError = 1;
                    $tmpErrors[] = array("output" => $service["plugin_output"],
                                            "service" => $name,
                                            "lastcheck" => $service["last_check"],
                                            "state" => $service["current_state"],
                                            "statechange" => $service["last_state_change"]);

                }
            }

            //If an error was found, dump the temporary array into another array named after the host
            if ($tmpError == 1) {
                    foreach ($tmpErrors as $tmp) {
                        $errors[$checkName][] = $tmp;
                    }
                }

                //Reset for next host
                $tmpError = 0;
                $tmpErrors = array();

        }
        //Return the array containing all hosts with errors, grouped with their respective errors.
        return $errors;
    }
}
