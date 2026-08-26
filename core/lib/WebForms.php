<?php

// handles Webforms from the server side, retrieving forms from the database
// presenting to the user and handling the results


class formsClass {
    static function getForm($formname): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['form_name' => $formname]);
        // echopre("Searching for form `$formname`");
        // echopre(print_r($dbForm, 1));
        if(!$dbForm)return null;

        return ($dbForm[0]);
    }
    static function getFormByGUID($guid): null|webFormsClass {
        // retrieve form from database
        $dbForm = webFormsClass::sgetAllFilter('webforms', ['guid' => $guid]);
        // echopre("Searching for form by guid `$guid`");
        // echopre(print_r($dbForm, 1));

        // Same "not found is a normal outcome, not a crash" fix as
        // getForm() above -- a guid with no matching row used to be an
        // uncaught "undefined array key 0" warning.
        if(!$dbForm)return null;

        return ($dbForm[0]);
    }


    static function renderForm($formname) {
        $form = self::getForm($formname);
        if($form) {
            $data = $form->getdata();
            $formArray = json_decode($data, true);
            
            // echopre( print_r($formArray, 1));
            $formArray['attributes']['action'] = rel_url("/webform/processform/".$form->getguid());
            return generateHTMLForm( $formArray );
        } else {
            return "Form '$formname' not found!";
        }
    }

    static function getFormRenderArray($formname):array|null {
        $form = self::getForm( $formname );
        if($form) {
                $data = $form->getdata();
                $formArray = json_decode($data, true);
                $formArray['attributes']['action'] = rel_url("/webform/processform/".$form->getguid());
                
                return $formArray;

        } else {
            return null;
        }
    }

    static function renderFormArray($formArray) {
        return generateHTMLForm( $formArray );
    } 

    // $formname string form name, $results array of results
    static function storeFormResults($form, $results) {
        
        $formArray = json_decode($form->getdata(), true);
        $formClass = $form->getform_class();
        // echopre("store form results, formClass: $formClass");
        $classData = new $formClass( $results );
        // echopre(print_r($classData, 1));
        
        // $classData->setguid( guid() );
        // $classData->setcdate( getDBtime() );
        
        // global $kernel;
        // $classData->setcuser( $kernel->getUserName() );

        $classData->insert();
    }

    static function processform($params) {
        // These used to be unconditional echopre() calls -- meaning every
        // POST to this endpoint (this app's admin-only clinics/doctors
        // forms among them, now that they're actually wired up) dumped
        // the raw $_POST body (including the CSRF token) and, further
        // down, the entire $_SESSION array straight into the page's HTML
        // output. Left commented out, matching how every other debug
        // echopre() in this file is already handled, rather than deleted
        // outright, since they're genuinely useful when debugging a form
        // by hand.
        // echopre("Processing form: " . $params['guid']);

        // echopre("post:" . print_r($_POST, 1));

        // Opt-in only -- see csrfClass::$enforceWebforms's docblock
        // (core/lib/Csrf.php). An app that hasn't called
        // enableWebformProtection() reaches this unchanged.
        if(csrfClass::$enforceWebforms && !csrfClass::verifyRequest()) {
            return csrfClass::requireValid();
        }

        if(isset($_POST) && isset($_POST['submit'])) {
            $form = self::getFormByGUID($params['guid']);

            // echopre("Found form " . print_r($form, 1));
            // echopre(print_r($_POST, 1));

            // echopre(print_r($_SESSION, 1));

            global $kernel;
            $_POST['cuser'] = $kernel->getUserName();
            $_POST['pdob'] = getDBtime();

            if(isset($_SESSION['handler'])) {
                $form_handler = $_SESSION['handler'];
                // echopre("form_handler: " . print_r($form_handler, 1));
                $guid = $form_handler['guid'];
                $handler = $form_handler['handler'];
                // echopre("form $guid handler $handler");


                
                if($handler) {
                    if(function_exists( $handler )) {
                        $result = $handler($params);
                        if($result) {
                            // handler returned succesfully
                            unset( $_SESSION['handler'] );
                            return $result;
                        } else {
                            return "ERROR: form handler `$handler` failed to execute";
                        }
                    } else {
                        return "ERROR: form handler `$handler` could not be found";
                    }
                } else {
                    return "ERROR: no form handler is defined";
                }
            }
            // self::storeFormResults($form, $_POST);

            // echopre("session:" . print_r($_SESSION, 1));


        }


        return "Form: {$params['guid']}";
    }

    static function viewform($params) {
        // echopre("Viewing form: " . $params['formname']);

        return self::renderFormResults($params['formname']);
    }


    static function renderFormResults($formname, $filter_fields = array()) {
        $form = self::getForm($formname);
        // getForm() returns null when no `webforms` row exists for this
        // form name yet (e.g. `maker.php form:load` was never run for it)
        // -- every other call site here already treats that as a normal,
        // non-fatal outcome (renderForm() below returns a "not found"
        // string instead of crashing); this one used to call ->getdata()
        // on that null unconditionally, a hard fatal error rather than a
        // page that simply has nothing to show yet.
        if(!$form) return '';

        $formArray = json_decode($form->getdata(), true);
        // echopre(print_r($formArray, 1));

        $formClass = $form->getform_class();
        // echopre("Form class: " . $formClass);
        // echopre("Table name: " . $formArray['name']);
        $results = $formClass::sgetAllFilter($formArray['name']);
        // echopre("Results: " . print_r($results, 1));

        // unset($formArray['buttons']);

        $output = [$formClass . ' table results'];
        // Was a bare `return $output` -- $output is an array, so every
        // {{$var}} call site (a plain zetem echo, not a foreach) printed
        // PHP's "Array to string conversion" warning followed by the
        // literal word "Array" whenever a form had zero rows yet (e.g. a
        // fresh install with no clinics/doctors entered). imploding here
        // matches the string this function already returns below when
        // there ARE results.
        if(!count($results))return implode('<br>', $output);
        
        $html = [];
        $formArray['inputs_list'] = [];

        $input_row = [];
        $out = [];

        // get fields from first result
        $fields = $results[0]->getFields();

        $out[] = "<thead>";
        // setup headers
        foreach($formArray['inputs'] as $key => $input) {
            // echopre("Input: " . $input['name']);
            // echopre("Filter field: " . print_r(array_values($filter_fields), 1));
            // echopre("in filter: " . in_array($input['name'], array_values($filter_fields)));
            if(key_exists($input['name'], $fields) &&
            (empty($filter_fields) || (!empty($filter_fields) && in_array($input['name'], array_values($filter_fields))))) {
                // $out[] = "{{ " . $fields[ $input['name' ] ] . " }} ";
                $out[] = "<td> " . $input['name' ] . "</td>";

                // $formArray['inputs'][$key]['default'] = $fields[ $input['name'] ];
                // $formArray['inputs'][$key]['attributes']['disabled'] = "disabled";
                
                // $input_row[] = $formArray['inputs'][$key];
            }
        }
        $out[] = "</thead>";
        $output[] = implode(',', $out);

        foreach($results as $entry) {
            $out = [];
            $input_row = [];
            $fields = $entry->getFields();
            // echopre("Fields: " . print_r($fields, 1));
            foreach($formArray['inputs'] as $key => $input) {
                // echopre("Input: " . $input['name']);
                // echopre("Filter field: " . print_r(array_values($filter_fields), 1));
                // echopre("in filter: " . in_array($input['name'], array_values($filter_fields)));
                if(key_exists($input['name'], $fields) &&
                (empty($filter_fields) || (!empty($filter_fields) && in_array($input['name'], array_values($filter_fields))))) {
                    $out[] = "{{ " . $fields[ $input['name' ] ] . " }} ";

                    $formArray['inputs'][$key]['default'] = $fields[ $input['name'] ];
                    $formArray['inputs'][$key]['attributes']['disabled'] = "disabled";
                    
                    $input_row[] = $formArray['inputs'][$key];
                }
            }
            $formArray['inputs_list'][] = $input_row;

            // $output[] = implode(',', $out);
        }
        // echopre("input_lists: " . print_r($formArray, 1));
        $html[] = generateHTMLFormTable($formArray);


        $htmloutput = implode('', $html);
        $output[] = $htmloutput;

        $output = implode('<br>', $output);

        return $output;
    }
}


function setupFormActionHandler($form, $handler) {
    $guid = formsClass::getForm($form['name'])->getguid();
    $handler = [
        'handler' => $handler,
        'time' => time(),
        'guid' => $guid
    ];

    $_SESSION['handler'] = $handler;
    // echopre("setupFormActionHandler: " . print_r($handler, 1));
    // $handlerElement = new HiddenElement($form['name'], ['type' => 'hidden', 'name' => 'handler', 'value' => $handler] );
    // $formArray['handler'] = $handler;
}
